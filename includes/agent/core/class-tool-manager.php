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
     * Register all Wordsurf tools with the AI HTTP Client library
     * This replaces manual SSE parsing with proper library integration
     */
    public function register_tools_with_library() {
        // Register tools via WordPress filters (cleaner approach)
        add_filter('ai_http_client_execute_tool', [$this, 'handle_tool_execution_filter'], 10, 4);
        add_filter('ai_http_client_get_tool_definition', [$this, 'get_tool_definition_filter'], 10, 2);
        add_filter('ai_http_client_get_all_tool_definitions', [$this, 'get_all_tool_definitions_filter'], 10, 1);
        
        error_log('Wordsurf DEBUG (ToolManager): All tools registered with AI HTTP Client library via WordPress filters');
    }
    
    /**
     * WordPress filter handler for tool execution
     * This is called by the AI HTTP Client library when tools need to be executed
     *
     * @param mixed $result Previous filter result
     * @param string $tool_name Tool name to execute
     * @param array $arguments Tool arguments
     * @param string $call_id Tool call ID
     * @return array|null Tool execution result or null if tool not handled
     */
    public function handle_tool_execution_filter($result, $tool_name, $arguments, $call_id) {
        // Only handle if no previous filter handled it
        if ($result !== null) {
            return $result;
        }
        
        // Only handle tools we know about
        if (!isset($this->tools[$tool_name])) {
            return null;
        }
        
        error_log("Wordsurf DEBUG (ToolManager): Executing tool '{$tool_name}' via AI HTTP Client library");
        $result = $this->execute_tool($tool_name, $arguments, $call_id);
        error_log("Wordsurf DEBUG (ToolManager): Tool '{$tool_name}' returned: " . json_encode($result));
        return $result;
    }
    
    /**
     * Get tool definition for AI HTTP Client library
     *
     * @param mixed $definition Previous filter result
     * @param string $tool_name Tool name
     * @return array|null Tool definition or null if not our tool
     */
    public function get_tool_definition_filter($definition, $tool_name) {
        if ($definition !== null) {
            return $definition;
        }
        
        if (!isset($this->tools[$tool_name])) {
            return null;
        }
        
        $tool = $this->tools[$tool_name];
        if (method_exists($tool, 'get_schema')) {
            return $tool->get_schema();
        }
        
        return null;
    }
    
    /**
     * Get all tool definitions for AI HTTP Client library
     *
     * @param array $definitions Existing definitions
     * @return array Updated definitions
     */
    public function get_all_tool_definitions_filter($definitions) {
        foreach ($this->tools as $tool_name => $tool) {
            if (method_exists($tool, 'get_schema')) {
                $definitions[$tool_name] = $tool->get_schema();
            }
        }
        
        return $definitions;
    }
} 