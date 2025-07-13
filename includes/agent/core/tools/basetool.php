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
     * Define the tool parameters declaratively
     * 
     * @return array Parameters definition with required flags
     */
    abstract protected function define_parameters();

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
     * with automatic strict mode handling based on optional parameters
     * 
     * @return array
     */
    public function get_schema() {
        $parameters_definition = $this->define_parameters();
        
        // Process parameters and determine if we have optional parameters
        $properties = [];
        $required = [];
        
        foreach ($parameters_definition as $param_name => $param_config) {
            // Copy parameter config for properties
            $properties[$param_name] = $param_config;
            
            // Check if this parameter is required
            if (isset($param_config['required']) && $param_config['required']) {
                $required[] = $param_name;
            }
            
            // Remove the 'required' field from properties (OpenAI doesn't want it there)
            unset($properties[$param_name]['required']);
        }
        
        $parameters = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $parameters['required'] = $required;
        }
        
        return [
            'type' => 'function',
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'parameters' => $parameters
        ];
    }


} 