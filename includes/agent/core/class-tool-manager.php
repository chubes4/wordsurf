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
    public function load_tool($tool_name) {
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
     * Register all Wordsurf tools with the new filter-based architecture
     * 
     * Called once at plugin initialization, not per-instance
     */
    public static function register_tools_with_library() {
        // Register tools via the new ai_tools filter
        add_filter('ai_tools', [__CLASS__, 'register_wordsurf_tools']);
    }
    
    /**
     * Register Wordsurf tools with the ai_tools filter
     *
     * @param array $tools Existing tools array
     * @return array Updated tools array with Wordsurf tools
     */
    public static function register_wordsurf_tools($tools) {
        $wordsurf_tools = [
            'edit_post' => [
                'class' => 'Wordsurf_EditPostTool',
                'category' => 'content_editing',
                'description' => 'Make specific edits to the current post content',
                'method' => 'execute',
                'parameters' => [
                    'search_text' => ['type' => 'string', 'required' => true],
                    'replacement_text' => ['type' => 'string', 'required' => true]
                ]
            ],
            'insert_content' => [
                'class' => 'Wordsurf_InsertContentTool',
                'category' => 'content_editing',
                'description' => 'Add new content at specific locations within the post',
                'method' => 'execute',
                'parameters' => [
                    'position' => ['type' => 'string', 'required' => true],
                    'content' => ['type' => 'string', 'required' => true],
                    'target_text' => ['type' => 'string', 'required' => false]
                ]
            ],
            'write_to_post' => [
                'class' => 'Wordsurf_WriteToPostTool',
                'category' => 'content_editing',
                'description' => 'Completely rewrite the entire post content',
                'method' => 'execute',
                'parameters' => [
                    'new_content' => ['type' => 'string', 'required' => true]
                ]
            ],
            'read_post' => [
                'class' => 'Wordsurf_ReadPostTool',
                'category' => 'content_reading',
                'description' => 'Read content from posts or pages',
                'method' => 'execute',
                'parameters' => [
                    'post_id' => ['type' => 'integer', 'required' => false]
                ]
            ]
        ];
        
        return array_merge($tools, $wordsurf_tools);
    }
} 