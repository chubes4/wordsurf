<?php
/**
 * AI HTTP Client - API Key Input Component
 * 
 * Single Responsibility: Render API key input field
 * Core component that handles API key entry with visibility toggle
 *
 * @package AIHttpClient\Components\Core
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Core_ApiKeyInput implements AI_HTTP_Component_Interface {
    
    /**
     * Render the API key input component
     *
     * @param string $unique_id Unique component identifier
     * @param array $config Component configuration
     * @param array $current_values Current saved values
     * @return string Rendered HTML
     */
    public static function render($unique_id, $config = [], $current_values = []) {
        $config = array_merge(self::get_defaults(), $config);
        
        $provider = $current_values['provider'] ?? 'openai';
        $api_key = $current_values['api_key'] ?? '';
        
        $html = '<div class="ai-field-group ai-api-key-input">';
        $html .= '<label for="' . esc_attr($unique_id) . '_api_key">' . esc_html($config['label']) . ':</label>';
        $html .= '<div class="ai-input-with-button">';
        $html .= '<input type="password" ';
        $html .= 'id="' . esc_attr($unique_id) . '_api_key" ';
        $html .= 'name="ai_api_key" ';
        $html .= 'value="' . esc_attr($api_key) . '" ';
        $html .= 'placeholder="' . esc_attr($config['placeholder_template']) . '" ';
        $html .= 'data-component-id="' . esc_attr($unique_id) . '" ';
        $html .= 'data-component-type="api_key_input" ';
        $html .= 'data-provider="' . esc_attr($provider) . '" ';
        $html .= 'class="ai-api-key-field" />';
        
        if ($config['show_toggle']) {
            $html .= '<button type="button" class="ai-toggle-key-visibility" ';
            $html .= 'onclick="aiHttpToggleKeyVisibility(\'' . esc_attr($unique_id) . '_api_key\')" ';
            $html .= 'title="Toggle API key visibility">';
            $html .= $config['toggle_icon'];
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        if ($config['show_help']) {
            $html .= '<p class="ai-field-help">' . esc_html($config['help_text']) . '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get component configuration schema
     *
     * @return array Configuration schema
     */
    public static function get_config_schema() {
        return [
            'label' => [
                'type' => 'string',
                'default' => 'API Key',
                'description' => 'Label for the API key input'
            ],
            'placeholder_template' => [
                'type' => 'string',
                'default' => 'Enter your API key',
                'description' => 'Placeholder text template'
            ],
            'show_toggle' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show visibility toggle button'
            ],
            'toggle_icon' => [
                'type' => 'string',
                'default' => '👁',
                'description' => 'Icon for visibility toggle'
            ],
            'show_help' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Show help text'
            ],
            'help_text' => [
                'type' => 'string',
                'default' => 'Your API key is stored securely and only used for AI requests.',
                'description' => 'Help text displayed below input'
            ]
        ];
    }
    
    /**
     * Get component default values
     *
     * @return array Default values
     */
    public static function get_defaults() {
        return [
            'label' => 'API Key',
            'placeholder_template' => 'Enter your API key',
            'show_toggle' => true,
            'toggle_icon' => '👁',
            'show_help' => true,
            'help_text' => 'Your API key is stored securely and only used for AI requests.'
        ];
    }
    
    /**
     * Validate component configuration
     *
     * @param array $config Configuration to validate
     * @return bool True if valid
     */
    public static function validate_config($config) {
        $schema = self::get_config_schema();
        
        foreach ($config as $key => $value) {
            if (!isset($schema[$key])) {
                return false;
            }
            
            $expected_type = $schema[$key]['type'];
            
            if ($expected_type === 'string' && !is_string($value)) {
                return false;
            }
            
            if ($expected_type === 'boolean' && !is_bool($value)) {
                return false;
            }
        }
        
        return true;
    }
}