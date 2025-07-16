<?php
/**
 * Wordsurf Tool Manager
 *
 * Handles tool discovery, loading, and execution for the AI agent.
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Wordsurf_Tool_Manager {
    
    /**
     * Available tools
     *
     * @var array
     */
    private $tools = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_tools();
    }
    
    /**
     * Load all available tools
     */
    private function load_tools() {
        // Load the read_post tool
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/read_post.php';
        
        $this->tools['read_post'] = new Wordsurf_ReadPostTool();
        
        // Load the edit_post tool
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/edit_post.php';
        
        $this->tools['edit_post'] = new Wordsurf_EditPostTool();
        
        // Load the insert_content tool
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/insert_content.php';
        
        $this->tools['insert_content'] = new Wordsurf_InsertContentTool();
        
        // Load the write_to_post tool
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/write_to_post.php';
        
        $this->tools['write_to_post'] = new Wordsurf_WriteToPostTool();
        
        // Future tools will be loaded here
    }
    
    /**
     * Get all available tools
     *
     * @return array
     */
    public function get_tools() {
        return $this->tools;
    }
    
    /**
     * Get tool schemas for function calling
     *
     * @return array
     */
    public function get_tool_schemas() {
        $schemas = [];
        
        foreach ($this->tools as $tool) {
            if (method_exists($tool, 'get_schema')) {
                $schemas[] = $tool->get_schema();
            }
        }
        
        return $schemas;
    }
    
    /**
     * Execute a tool
     *
     * @param string $tool_name
     * @param array $parameters
     * @param string $call_id Optional call ID to pass to the tool
     * @return array
     */
    public function execute_tool($tool_name, $parameters = [], $call_id = null) {
        if (!isset($this->tools[$tool_name])) {
            return [
                'success' => false,
                'error' => 'Tool not found: ' . $tool_name
            ];
        }
        
        $tool = $this->tools[$tool_name];
        
        try {
            // Add the original call ID to the context if provided
            if ($call_id) {
                $parameters['_original_call_id'] = $call_id;
            }
            
            $result = $tool->execute($parameters);
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Tool execution failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get tool descriptions for the prompt
     *
     * @return string
     */
    public function get_tool_descriptions() {
        $descriptions = [];
        
        foreach ($this->tools as $tool) {
            if (method_exists($tool, 'get_description')) {
                $descriptions[] = $tool->get_name() . ': ' . $tool->get_description();
            }
        }
        
        return implode("\n", $descriptions);
    }

    /**
     * Parses a full API response, finds, executes, and returns completed tool calls.
     *
     * @param string $full_response The complete raw response from the API.
     * @return array A list of pending tool calls with their results.
     */
    public function process_and_execute_tool_calls($full_response) {
        error_log('Wordsurf DEBUG (ToolManager): Processing tool calls from response');
        $pending_tool_calls = [];
        // SSE events are separated by double newlines.
        $event_blocks = explode("\n\n", trim($full_response));
        error_log('Wordsurf DEBUG (ToolManager): Found ' . count($event_blocks) . ' event blocks');
    
        foreach ($event_blocks as $block_index => $block) {
            $lines = explode("\n", $block);
            $event_type = null;
            $data_json = '';
    
            // First, parse the event type and data from the current block.
            foreach ($lines as $line) {
                if (strpos($line, 'event: ') === 0) {
                    $event_type = substr($line, 7);
                } elseif (strpos($line, 'data: ') === 0) {
                    // This handles cases where the data payload itself might be split into multiple "data: " lines.
                    $data_json .= substr($line, 6);
                }
            }
            
            error_log("Wordsurf DEBUG (ToolManager): Block {$block_index} - Event type: {$event_type}");
            
            // We only care about the 'response.completed' event for finding the final, authoritative list of tool calls.
            if ($event_type === 'response.completed' && !empty($data_json)) {
                error_log('Wordsurf DEBUG (ToolManager): Found response.completed event');
                $decoded = json_decode($data_json, true);
                error_log('Wordsurf DEBUG (ToolManager): Decoded response structure: ' . json_encode($decoded, JSON_PRETTY_PRINT));
                
                if (isset($decoded['response']['output'])) {
                    $output_items = $decoded['response']['output'];
                    error_log('Wordsurf DEBUG (ToolManager): Found ' . count($output_items) . ' output items');
                    
                    foreach ($output_items as $item_index => $item) {
                        error_log("Wordsurf DEBUG (ToolManager): Output item {$item_index}: " . json_encode($item));
                        
                        if (isset($item['type']) && $item['type'] === 'function_call' && isset($item['status']) && $item['status'] === 'completed') {
                            $tool_name = $item['name'];
                            // Arguments might not be present if the call fails, so provide a default.
                            $arguments = isset($item['arguments']) ? json_decode($item['arguments'], true) : [];
    
                            // Ensure arguments are always an array, even if json_decode fails on an empty string.
                            if ($arguments === null) {
                                $arguments = [];
                            }
    
                            error_log("Wordsurf DEBUG (ToolManager): Executing tool '{$tool_name}' with ID '{$item['call_id']}'.");
                            $result = $this->execute_tool($tool_name, $arguments, $item['call_id']);
                            error_log("Wordsurf DEBUG (ToolManager): Tool '{$tool_name}' executed. Result: " . json_encode($result));
    
                            $pending_tool_calls[] = [
                                'tool_call_object' => $item,
                                'result' => $result,
                            ];
                        }
                    }
                    // We found and processed the 'response.completed' event, so we can stop searching.
                    break;
                } else {
                    error_log('Wordsurf DEBUG (ToolManager): No output found in response.completed event');
                }
            }
        }
        
        error_log('Wordsurf DEBUG (ToolManager): Returning ' . count($pending_tool_calls) . ' pending tool calls');
        return $pending_tool_calls;
    }
} 