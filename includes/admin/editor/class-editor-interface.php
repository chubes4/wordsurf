<?php
/**
 * Wordsurf Editor Interface
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Editor Interface Class.
 *
 * Handles the integration of the agent into the Gutenberg editor.
 *
 * @class   Wordsurf_Editor_Interface
 * @version 0.1.0
 * @since   0.1.0
 */
class Wordsurf_Editor_Interface {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
    }

    /**
     * Enqueue assets for the block editor.
     */
    public function enqueue_editor_assets() {
        $script_asset_path = WORDSURF_PLUGIN_DIR . 'assets/js/editor/index.asset.php';
        if ( ! file_exists( $script_asset_path ) ) {
            // This will happen if you haven't run `npm run build`
            // For now, we'll manually define dependencies. In a real build process, this file would be generated.
            $script_asset = [
                'dependencies' => [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ],
                'version'      => WORDSURF_VERSION,
            ];
        } else {
            $script_asset = require $script_asset_path;
        }

        wp_enqueue_script(
            'wordsurf-editor',
            WORDSURF_PLUGIN_URL . 'assets/js/editor.js',
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_enqueue_style(
            'wordsurf-editor-interface',
            WORDSURF_PLUGIN_URL . 'assets/css/editor/editor-interface.css',
            [],
            filemtime( WORDSURF_PLUGIN_DIR . 'assets/css/editor/editor-interface.css' )
        );

        wp_enqueue_style(
            'wordsurf-inline-diff-highlight',
            WORDSURF_PLUGIN_URL . 'assets/css/editor/inline-diff-highlight.css',
            [],
            filemtime( WORDSURF_PLUGIN_DIR . 'assets/css/editor/inline-diff-highlight.css' )
        );

        wp_enqueue_style(
            'wordsurf-diff-block',
            WORDSURF_PLUGIN_URL . 'assets/css/editor/diff-block.css',
            [],
            filemtime( WORDSURF_PLUGIN_DIR . 'assets/css/editor/diff-block.css' )
        );

        wp_localize_script(
            'wordsurf-editor',
            'wordsurfData',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wordsurf_nonce' ),
            ]
        );

        // Add WordPress REST API nonce for our API calls
        wp_localize_script(
            'wordsurf-editor',
            'wpApiSettings',
            [
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }
} 