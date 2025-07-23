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
            
            <?php
            // Render the AI HTTP Client provider management component
            echo AI_HTTP_ProviderManager_Component::render([
                'plugin_context' => 'wordsurf',
                'components' => [
                    'core' => ['provider_selector', 'api_key_input', 'model_selector'],
                    'extended' => ['temperature_slider', 'system_prompt_field']
                ],
                'component_configs' => [
                    'temperature_slider' => [
                        'min' => 0,
                        'max' => 1,
                        'step' => 0.1,
                        'default_value' => 0.7
                    ]
                ]
            ]);
            ?>
            
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
} 