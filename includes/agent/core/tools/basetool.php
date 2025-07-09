<?php
/**
 * Wordsurf Base Tool Class
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class Wordsurf_BaseTool {
    
    /**
     * Get the tool name (unique identifier)
     */
    abstract public function get_name();

    /**
     * Get the tool description for the AI
     */
    abstract public function get_description();

    /**
     * Get the tool parameters schema
     * 
     * @return array Parameters schema following OpenAI function calling standards
     */
    abstract public function get_parameters_schema();

    /**
     * Execute the tool logic
     * 
     * @param array $context
     * @return array
     */
    abstract public function execute($context = []);

    /**
     * Get the complete tool schema for function calling
     * 
     * This builds the standardized OpenAI function calling schema
     * with the tool's specific details
     * 
     * @return array
     */
    public function get_schema() {
        $parameters_schema = $this->get_parameters_schema();
        
        // Remove 'required' from individual parameters and build required array
        $properties = [];
        $required = [];
        
        foreach ($parameters_schema as $param_name => $param_config) {
            $properties[$param_name] = $param_config;
            if (isset($param_config['required']) && $param_config['required']) {
                $required[] = $param_name;
                // Remove the 'required' field from individual parameters
                unset($properties[$param_name]['required']);
            }
        }
        
        return [
            'type' => 'function',
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false
            ]
        ];
    }
} 