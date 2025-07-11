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
     * @return array
     */
    public function execute_tool($tool_name, $parameters = []) {
        if (!isset($this->tools[$tool_name])) {
            return [
                'success' => false,
                'error' => 'Tool not found: ' . $tool_name
            ];
        }
        
        $tool = $this->tools[$tool_name];
        
        try {
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
        $pending_tool_calls = [];
        $lines = explode("\n", $full_response);
        
        foreach ($lines as $line) {
            // The final event block starts with 'event: response.completed'.
            // The actual data is on the following 'data: ' line.
            if (strpos($line, 'data: ') === 0) {
                $json_data = substr($line, 6);
                $decoded = json_decode($json_data, true);
                
                // Check for the final 'response' block from a 'response.completed' event.
                if (isset($decoded['response']['output'])) {
                    $output_items = $decoded['response']['output'];
                    foreach ($output_items as $item) {
                        if (isset($item['type']) && $item['type'] === 'function_call' && isset($item['status']) && $item['status'] === 'completed') {
                            $tool_name = $item['name'];
                            $arguments = json_decode($item['arguments'], true);

                            error_log("Wordsurf DEBUG (ToolManager): Executing tool '{$tool_name}' with ID '{$item['call_id']}'.");
                            $result = $this->execute_tool($tool_name, $arguments);
                            error_log("Wordsurf DEBUG (ToolManager): Tool '{$tool_name}' executed. Result: " . json_encode($result));

                            // Store the tool call object and its result for the follow-up.
                            $pending_tool_calls[] = [
                                'tool_call_object' => $item,
                                'result' => $result,
                            ];
                        }
                    }
                    // Once we've processed the 'response.completed' data, we can stop.
                    break;
                }
            }
        }
        return $pending_tool_calls;
    }
} 