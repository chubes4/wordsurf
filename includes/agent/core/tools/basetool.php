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
        $has_optional_params = false;
        
        foreach ($parameters_definition as $param_name => $param_config) {
            // Copy parameter config for properties
            $properties[$param_name] = $param_config;
            
            // Check if this parameter is required
            if (isset($param_config['required']) && $param_config['required']) {
                $required[] = $param_name;
            } else {
                $has_optional_params = true;
            }
            
            // Remove the 'required' field from properties (OpenAI doesn't want it there)
            unset($properties[$param_name]['required']);
        }
        
        // Automatically determine strict mode:
        // - Use strict mode only if ALL parameters are required
        // - Use non-strict mode if ANY parameters are optional
        $strict_mode = !$has_optional_params;
        
        return [
            'type' => 'function',
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'strict' => $strict_mode,
            'parameters' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false
            ]
        ];
    }


} 