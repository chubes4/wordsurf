<?php
/**
 * Wordsurf Settings
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings Class.
 *
 * Handles the creation of the settings page and fields.
 *
 * @class   Wordsurf_Settings
 * @version 0.1.0
 * @since   0.1.0
 */
class Wordsurf_Settings {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_wordsurf_save_ai_settings', [ $this, 'save_ai_settings' ] );
    }

    /**
     * Add the settings page to the admin menu as a top-level menu.
     */
    public function add_settings_page() {
        add_menu_page(
            'Wordsurf Settings',
            'Wordsurf',
            'manage_options',
            'wordsurf',
            [ $this, 'render_settings_page' ],
            'dashicons-robot',
            60
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2>AI Provider Settings</h2>
            <p>Configure your AI provider and model settings below. Wordsurf supports multiple AI providers including OpenAI, Anthropic (Claude), Google Gemini, Grok, and OpenRouter.</p>
            
            <form id="wordsurf-ai-settings" method="post" action="">
                <?php wp_nonce_field('wordsurf_ai_settings', 'wordsurf_ai_nonce'); ?>
                <table class="form-table">
                    <?php
                    // Render AI components using new filter-based system
                    echo apply_filters('ai_render_component', '', [
                        'selected_provider' => get_option('wordsurf_ai_provider', 'openai'),
                        'selected_model' => get_option('wordsurf_ai_model', ''),
                        'temperature_value' => get_option('wordsurf_ai_temperature', 0.7),
                        'system_prompt_value' => get_option('wordsurf_ai_system_prompt', ''),
                        'temperature' => true,
                        'system_prompt' => true
                    ]);
                    ?>
                </table>
                <?php submit_button('Save AI Settings'); ?>
            </form>
            
            <script>
            // Handle AI settings form submission
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('wordsurf-ai-settings');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(form);
                        formData.append('action', 'wordsurf_save_ai_settings');
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Settings save failed: ' + (data.data || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            alert('Settings save failed: ' + error.message);
                        });
                    });
                }
            });
            </script>
            
            <?php
            
            <h2>Additional Settings</h2>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wordsurf_settings' );
                do_settings_sections( 'wordsurf_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        // AI provider settings are now handled by AI HTTP Client component
        // No additional settings needed at this time
    }

    /**
     * Handle AJAX request to save AI settings
     */
    public function save_ai_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['wordsurf_ai_nonce'] ?? '', 'wordsurf_ai_settings')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            // Save individual settings
            if (isset($_POST['ai_provider'])) {
                update_option('wordsurf_ai_provider', sanitize_text_field($_POST['ai_provider']));
            }
            
            if (isset($_POST['ai_model'])) {
                update_option('wordsurf_ai_model', sanitize_text_field($_POST['ai_model']));
            }
            
            if (isset($_POST['ai_temperature'])) {
                $temperature = floatval($_POST['ai_temperature']);
                $temperature = max(0, min(1, $temperature)); // Clamp between 0 and 1
                update_option('wordsurf_ai_temperature', $temperature);
            }
            
            if (isset($_POST['ai_system_prompt'])) {
                update_option('wordsurf_ai_system_prompt', wp_kses_post($_POST['ai_system_prompt']));
            }

            // Handle API keys via the shared filter system
            $shared_keys = apply_filters('ai_provider_api_keys', null);
            $keys_updated = false;
            
            // Check for API key updates in POST data
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'ai_api_key_') === 0) {
                    $provider = str_replace('ai_api_key_', '', $key);
                    $api_key = sanitize_text_field($value);
                    
                    if (!empty($api_key)) {
                        $shared_keys[$provider] = $api_key;
                        $keys_updated = true;
                    }
                }
            }
            
            // Update shared API keys if any were changed
            if ($keys_updated) {
                apply_filters('ai_provider_api_keys', $shared_keys);
            }

            wp_send_json_success('Settings saved successfully');
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to save settings: ' . $e->getMessage());
        }
    }
} 