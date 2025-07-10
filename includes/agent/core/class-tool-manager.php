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
} 