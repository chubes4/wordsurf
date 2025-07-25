<?php
/**
 * AI HTTP Client - Provider Manager Component
 * 
 * Single Responsibility: Render complete AI provider configuration interface
 * Self-contained component that handles provider selection, API keys, models, and instructions
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_ProviderManager_Component {

    private static $instance_count = 0;
    private $options_manager;
    private $client;

    public function __construct() {
        $this->options_manager = new AI_HTTP_Options_Manager();
        $this->client = new AI_HTTP_Client();
        self::$instance_count++;
    }

    /**
     * Static render method for easy usage
     *
     * @param array $args Component configuration
     * @return string Rendered HTML
     */
    public static function render($args = array()) {
        $component = new self();
        return $component->render_component($args);
    }

    /**
     * Render the complete provider manager interface
     *
     * @param array $args Component configuration
     * @return string Rendered HTML
     */
    public function render_component($args = array()) {
        $defaults = array(
            'title' => 'AI Provider Configuration',
            'components' => array(
                'core' => array('provider_selector', 'api_key_input', 'model_selector'),
                'extended' => array()
            ),
            'show_test_connection' => true,
            'allowed_providers' => array(), // Empty = all providers
            'default_provider' => 'openai',
            'wrapper_class' => 'ai-http-provider-manager',
            'component_configs' => array()
        );

        $args = array_merge($defaults, $args);
        $unique_id = 'ai-provider-manager-' . uniqid();
        
        $current_settings = $this->options_manager->get_all_providers();
        $selected_provider = isset($current_settings['selected_provider']) 
            ? $current_settings['selected_provider'] 
            : $args['default_provider'];

        $current_values = array_merge(
            $this->options_manager->get_provider_settings($selected_provider),
            array('provider' => $selected_provider)
        );

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['wrapper_class']); ?>" id="<?php echo esc_attr($unique_id); ?>">
            
            <?php if ($args['title']): ?>
                <h3><?php echo esc_html($args['title']); ?></h3>
            <?php endif; ?>

            <div class="ai-provider-form">
                
                <?php
                // Render core components
                foreach ($args['components']['core'] as $component_name) {
                    $component_config = isset($args['component_configs'][$component_name]) 
                        ? $args['component_configs'][$component_name] 
                        : array();
                    
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            $component_name,
                            $unique_id,
                            $component_config,
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering component ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . ' -->';
                    }
                }
                
                // Render extended components
                foreach ($args['components']['extended'] as $component_name) {
                    $component_config = isset($args['component_configs'][$component_name]) 
                        ? $args['component_configs'][$component_name] 
                        : array();
                    
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            $component_name,
                            $unique_id,
                            $component_config,
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering component ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . ' -->';
                    }
                }
                
                // Allow plugins to add custom components via filter
                $custom_components = apply_filters('ai_http_client_custom_components', array(), $args, $current_values);
                foreach ($custom_components as $component_name) {
                    $component_config = isset($args['component_configs'][$component_name]) 
                        ? $args['component_configs'][$component_name] 
                        : array();
                    
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            $component_name,
                            $unique_id,
                            $component_config,
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering custom component ' . esc_html($component_name) . ': ' . esc_html($e->getMessage()) . ' -->';
                    }
                }
                ?>

                <!-- Save Button -->
                <div class="ai-field-group">
                    <button type="button" class="ai-save-settings" 
                            onclick="aiHttpSaveSettings('<?php echo esc_attr($unique_id); ?>')">
                        Save Settings
                    </button>
                    <span class="ai-save-result" id="<?php echo esc_attr($unique_id); ?>_save_result"></span>
                </div>

                <?php if ($args['show_test_connection']): ?>
                    <?php
                    // Render TestConnection component
                    try {
                        echo AI_HTTP_Component_Registry::render_component(
                            'test_connection',
                            $unique_id,
                            [],
                            $current_values
                        );
                    } catch (Exception $e) {
                        echo '<!-- Error rendering test connection component: ' . esc_html($e->getMessage()) . ' -->';
                    }
                    ?>
                <?php endif; ?>

            </div>
        </div>

        <script>
        <?php echo $this->render_javascript($unique_id); ?>
        </script>

        
        <?php
        // Ensure global JavaScript functions are available
        $this->render_required_javascript();
        ?>
        <?php

        return ob_get_clean();
    }

    /**
     * Get available providers
     */
    private function get_available_providers($allowed_providers) {
        $all_providers = array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'grok' => 'Grok',
            'openrouter' => 'OpenRouter'
        );

        if (empty($allowed_providers)) {
            return $all_providers;
        }

        $filtered = array();
        foreach ($allowed_providers as $provider) {
            if (isset($all_providers[$provider])) {
                $filtered[$provider] = $all_providers[$provider];
            }
        }

        return $filtered;
    }

    /**
     * Get provider setting value
     */
    private function get_provider_setting($provider, $key, $default = '') {
        $settings = $this->options_manager->get_provider_settings($provider);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Get provider status
     */
    private function get_provider_status($provider) {
        $api_key = $this->get_provider_setting($provider, 'api_key');
        
        if (empty($api_key)) {
            return '<span style="color: #d63638;">⚠ Not configured</span>';
        }

        return '<span style="color: #00a32a;">✓ Configured</span>';
    }

    /**
     * Render model options for provider
     */
    private function render_model_options($provider) {
        $current_model = $this->get_provider_setting($provider, 'model');
        
        try {
            $models = $this->client->get_models($provider);
            $html = '';
            
            foreach ($models as $model_id => $model_name) {
                $selected = ($current_model === $model_id) ? 'selected' : '';
                $html .= sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($model_id),
                    $selected,
                    esc_html($model_name)
                );
            }
            
            return $html;
            
        } catch (Exception $e) {
            return '<option value="">Error loading models</option>';
        }
    }

    /**
     * Render minimal JavaScript for functionality
     */
    private function render_javascript($unique_id) {
        return "
        // Provider change handler - just refresh models, no auto-save
        const providerSelect = document.getElementById('{$unique_id}_provider');
        if (providerSelect) {
            providerSelect.addEventListener('change', function() {
                aiHttpProviderChanged('$unique_id', this.value);
            });
        }

        ";
    }

    /**
     * Ensure required JavaScript functions are available
     * This ensures the component works even if admin_footer hook doesn't run
     */
    private function render_required_javascript() {
        static $js_rendered = false;
        if ($js_rendered) return;
        $js_rendered = true;
        
        $nonce = wp_create_nonce('ai_http_nonce');
        ?>
        <script>
        // Global AI HTTP Client JavaScript functions
        function aiHttpSaveSettings(componentId) {
            const component = document.getElementById(componentId);
            const formData = new FormData();
            
            formData.append('action', 'ai_http_save_settings');
            formData.append('nonce', '<?php echo esc_js($nonce); ?>');
            
            component.querySelectorAll('input, select, textarea').forEach(function(input) {
                if (input.name) {
                    formData.append(input.name, input.value);
                }
            });

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(data => {
                const resultSpan = document.getElementById(componentId + '_save_result');
                if (resultSpan) {
                    if (data.success) {
                        resultSpan.textContent = '✓ Settings saved';
                        resultSpan.style.color = '#00a32a';
                        
                        // Update provider status after successful save
                        const apiKeyInput = document.getElementById(componentId + '_api_key');
                        const providerStatusSpan = document.getElementById(componentId + '_provider_status');
                        if (providerStatusSpan && apiKeyInput) {
                            const apiKeyValue = apiKeyInput.value.trim();
                            if (apiKeyValue) {
                                providerStatusSpan.innerHTML = '<span style="color: #00a32a;">✓ Configured</span>';
                            } else {
                                providerStatusSpan.innerHTML = '<span style="color: #d63638;">⚠ Not configured</span>';
                            }
                        }
                    } else {
                        resultSpan.textContent = '✗ Save failed: ' + (data.message || 'Unknown error');
                        resultSpan.style.color = '#d63638';
                    }
                    setTimeout(() => resultSpan.textContent = '', 3000);
                }
            }).catch(error => {
                console.error('AI HTTP Client: Save failed', error);
                const resultSpan = document.getElementById(componentId + '_save_result');
                if (resultSpan) {
                    resultSpan.textContent = '✗ Save failed';
                    resultSpan.style.color = '#d63638';
                    setTimeout(() => resultSpan.textContent = '', 3000);
                }
            });
        }

        function aiHttpProviderChanged(componentId, provider) {
            // Load provider settings and update all fields
            aiHttpLoadProviderSettings(componentId, provider);
            // Update models when provider changes - NO auto-save
            aiHttpRefreshModels(componentId, provider);
        }

        function aiHttpToggleKeyVisibility(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.type = input.type === 'password' ? 'text' : 'password';
            }
        }

        function aiHttpRefreshModels(componentId, provider) {
            const modelSelect = document.getElementById(componentId + '_model');
            if (!modelSelect) return;
            
            modelSelect.innerHTML = '<option value="">Loading models...</option>';
            
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=ai_http_get_models&provider=' + encodeURIComponent(provider) + '&nonce=<?php echo esc_js($nonce); ?>'
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    modelSelect.innerHTML = '';
                    const selectedModel = modelSelect.getAttribute('data-selected-model') || '';
                    
                    Object.entries(data.data).forEach(([key, value]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = value;
                        option.selected = (key === selectedModel);
                        modelSelect.appendChild(option);
                    });
                } else {
                    modelSelect.innerHTML = '<option value="">Error loading models</option>';
                }
            }).catch(error => {
                console.error('AI HTTP Client: Model fetch failed', error);
                modelSelect.innerHTML = '<option value="">Error loading models</option>';
            });
        }

        function aiHttpTestConnection(componentId) {
            const providerElement = document.getElementById(componentId + '_provider');
            const resultSpan = document.getElementById(componentId + '_test_result');
            
            if (!providerElement || !resultSpan) return;
            
            const provider = providerElement.value;
            resultSpan.textContent = 'Testing...';
            resultSpan.style.color = '#666';
            
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=ai_http_test_connection&provider=' + encodeURIComponent(provider) + '&nonce=<?php echo esc_js($nonce); ?>'
            }).then(response => response.json()).then(data => {
                resultSpan.textContent = data.success ? '✓ Connected' : '✗ ' + (data.message || 'Connection failed');
                resultSpan.style.color = data.success ? '#00a32a' : '#d63638';
            }).catch(error => {
                console.error('AI HTTP Client: Connection test failed', error);
                resultSpan.textContent = '✗ Test failed';
                resultSpan.style.color = '#d63638';
            });
        }

        function aiHttpUpdateTemperatureValue(componentId, value) {
            const valueDisplay = document.getElementById(componentId + '_temperature_value');
            if (valueDisplay) {
                valueDisplay.textContent = value;
            }
        }
        
        function aiHttpLoadProviderSettings(componentId, provider) {
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=ai_http_load_provider_settings&provider=' + encodeURIComponent(provider) + '&nonce=<?php echo esc_js($nonce); ?>'
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    const settings = data.data;
                    
                    // Update API key field
                    const apiKeyInput = document.getElementById(componentId + '_api_key');
                    if (apiKeyInput) {
                        apiKeyInput.value = settings.api_key || '';
                    }
                    
                    // Update model field (will be populated by aiHttpRefreshModels)
                    const modelSelect = document.getElementById(componentId + '_model');
                    if (modelSelect) {
                        modelSelect.setAttribute('data-selected-model', settings.model || '');
                    }
                    
                    // Update temperature field
                    const temperatureInput = document.getElementById(componentId + '_temperature');
                    if (temperatureInput) {
                        temperatureInput.value = settings.temperature || '0.7';
                        aiHttpUpdateTemperatureValue(componentId, settings.temperature || '0.7');
                    }
                    
                    // Update system prompt field
                    const systemPromptTextarea = document.getElementById(componentId + '_system_prompt');
                    if (systemPromptTextarea) {
                        systemPromptTextarea.value = settings.system_prompt || '';
                    }
                    
                    // Update instructions field
                    const instructionsTextarea = document.getElementById(componentId + '_instructions');
                    if (instructionsTextarea) {
                        instructionsTextarea.value = settings.instructions || '';
                    }
                    
                    // Update provider status
                    const providerStatusSpan = document.getElementById(componentId + '_provider_status');
                    if (providerStatusSpan) {
                        const apiKeyValue = settings.api_key || '';
                        if (apiKeyValue.trim()) {
                            providerStatusSpan.innerHTML = '<span style="color: #00a32a;">✓ Configured</span>';
                        } else {
                            providerStatusSpan.innerHTML = '<span style="color: #d63638;">⚠ Not configured</span>';
                        }
                    }
                    
                    // Handle custom fields
                    Object.keys(settings).forEach(key => {
                        if (key.startsWith('custom_')) {
                            const customInput = document.getElementById(componentId + '_' + key);
                            if (customInput) {
                                customInput.value = settings[key] || '';
                            }
                        }
                    });
                    
                } else {
                    console.error('AI HTTP Client: Failed to load provider settings', data.message);
                }
            }).catch(error => {
                console.error('AI HTTP Client: Provider settings load failed', error);
            });
        }
        </script>
        <?php
    }


}

// Global JavaScript functions for component functionality
if (!function_exists('ai_http_render_global_js')):
function ai_http_render_global_js() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    ?>
    <script>
    function aiHttpAutoSave(componentId) {
        // Auto-save functionality
        const component = document.getElementById(componentId);
        const formData = new FormData();
        
        formData.append('action', 'ai_http_save_settings');
        formData.append('nonce', '<?php echo wp_create_nonce('ai_http_nonce'); ?>');
        
        component.querySelectorAll('input, select, textarea').forEach(function(input) {
            formData.append(input.name, input.value);
        });

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        }).then(response => response.json()).then(data => {
            if (data.success) {
                const status = document.getElementById(componentId + '_save_status');
                status.style.display = 'block';
                setTimeout(() => status.style.display = 'none', 2000);
            }
        });
    }


    function aiHttpToggleKeyVisibility(inputId) {
        const input = document.getElementById(inputId);
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function aiHttpRefreshModels(componentId, provider) {
        // Refresh models for provider
        const modelSelect = document.getElementById(componentId + '_model');
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=ai_http_get_models&provider=' + provider + '&nonce=<?php echo wp_create_nonce('ai_http_nonce'); ?>'
        }).then(response => response.json()).then(data => {
            if (data.success) {
                modelSelect.innerHTML = '';
                Object.entries(data.data).forEach(([key, value]) => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = value;
                    modelSelect.appendChild(option);
                });
            }
        });
    }

    function aiHttpTestConnection(componentId) {
        const provider = document.getElementById(componentId + '_provider').value;
        const resultSpan = document.getElementById(componentId + '_test_result');
        
        resultSpan.textContent = 'Testing...';
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=ai_http_test_connection&provider=' + provider + '&nonce=<?php echo wp_create_nonce('ai_http_nonce'); ?>'
        }).then(response => response.json()).then(data => {
            resultSpan.textContent = data.success ? '✓ Connected' : '✗ ' + data.message;
            resultSpan.style.color = data.success ? '#00a32a' : '#d63638';
        });
    }

    function aiHttpUpdateTemperatureValue(componentId, value) {
        const valueDisplay = document.getElementById(componentId + '_temperature_value');
        if (valueDisplay) {
            valueDisplay.textContent = value;
        }
    }
    </script>
    <?php
}
add_action('admin_footer', 'ai_http_render_global_js');
endif;