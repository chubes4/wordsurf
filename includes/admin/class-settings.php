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
        register_setting( 'wordsurf_settings', 'wordsurf_openai_api_key', 'sanitize_text_field' );

        add_settings_section(
            'wordsurf_api_settings_section',
            'API Settings',
            '__return_false', // No callback needed for the section description.
            'wordsurf_settings'
        );

        add_settings_field(
            'wordsurf_openai_api_key',
            'OpenAI API Key',
            [ $this, 'render_api_key_field' ],
            'wordsurf_settings',
            'wordsurf_api_settings_section'
        );
    }

    /**
     * Render the API key field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'wordsurf_openai_api_key', '' );
        echo '<input type="password" name="wordsurf_openai_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text">';
    }
} 