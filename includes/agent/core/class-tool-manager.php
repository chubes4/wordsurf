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
     * Constructor - no longer pre-loads tools
     */
    public function __construct() {
        // Tools will be loaded on-demand when requested
    }
    
    /**
     * Load a specific tool on-demand
     */
    private function load_tool($tool_name) {
        // Prevent loading the same tool twice
        if (isset($this->tools[$tool_name])) {
            return;
        }
        
        $tool_file_map = [
            'read_post' => 'read_post.php',
            'edit_post' => 'edit_post.php', 
            'insert_content' => 'insert_content.php',
            'write_to_post' => 'write_to_post.php'
        ];
        
        $tool_class_map = [
            'read_post' => 'Wordsurf_ReadPostTool',
            'edit_post' => 'Wordsurf_EditPostTool',
            'insert_content' => 'Wordsurf_InsertContentTool', 
            'write_to_post' => 'Wordsurf_WriteToPostTool'
        ];
        
        if (isset($tool_file_map[$tool_name]) && isset($tool_class_map[$tool_name])) {
            require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/' . $tool_file_map[$tool_name];
            $class_name = $tool_class_map[$tool_name];
            $this->tools[$tool_name] = new $class_name();
        }
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
     * 
     * Called once at plugin initialization, not per-instance
     */
    public static function register_tools_with_library() {
        // Register tools via WordPress filters using static callbacks
        add_filter('ai_http_client_execute_tool', [__CLASS__, 'handle_tool_execution_filter'], 10, 4);
        add_filter('ai_http_client_get_tool_definition', [__CLASS__, 'get_tool_definition_filter'], 10, 2);
        add_filter('ai_http_client_get_all_tool_definitions', [__CLASS__, 'get_all_tool_definitions_filter'], 10, 1);
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
    public static function handle_tool_execution_filter($result, $tool_name, $arguments, $call_id) {
        // Only handle if no previous filter handled it
        if ($result !== null) {
            return $result;
        }
        
        // Create instance to access tools
        $tool_manager = new self();
        
        // Load tool on-demand if we support it
        $supported_tools = ['read_post', 'edit_post', 'insert_content', 'write_to_post'];
        if (in_array($tool_name, $supported_tools)) {
            $tool_manager->load_tool($tool_name);
        }
        
        // Only handle tools we have loaded
        if (!isset($tool_manager->tools[$tool_name])) {
            return null;
        }
        
        error_log("Wordsurf DEBUG (ToolManager): Executing tool '{$tool_name}' via AI HTTP Client library");
        $result = $tool_manager->execute_tool($tool_name, $arguments, $call_id);
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
    public static function get_tool_definition_filter($definition, $tool_name) {
        if ($definition !== null) {
            return $definition;
        }
        
        // Create instance to access tools
        $tool_manager = new self();
        
        // Load tool on-demand if we support it
        $supported_tools = ['read_post', 'edit_post', 'insert_content', 'write_to_post'];
        if (in_array($tool_name, $supported_tools)) {
            $tool_manager->load_tool($tool_name);
        }
        
        if (!isset($tool_manager->tools[$tool_name])) {
            return null;
        }
        
        $tool = $tool_manager->tools[$tool_name];
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
    public static function get_all_tool_definitions_filter($definitions) {
        // For getting all definitions, we need to load all supported tools
        $tool_manager = new self();
        $supported_tools = ['read_post', 'edit_post', 'insert_content', 'write_to_post'];
        
        foreach ($supported_tools as $tool_name) {
            $tool_manager->load_tool($tool_name);
        }
        
        foreach ($tool_manager->tools as $tool_name => $tool) {
            if (method_exists($tool, 'get_schema')) {
                $definitions[$tool_name] = $tool->get_schema();
            }
        }
        
        return $definitions;
    }
} 