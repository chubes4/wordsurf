<?php
/**
 * Wordsurf Admin
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin Class.
 *
 * Handles all admin-related functionality.
 *
 * @class   Wordsurf_Admin
 * @version 0.1.0
 * @since   0.1.0
 */
class Wordsurf_Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    public function includes() {
        require_once WORDSURF_PLUGIN_DIR . 'includes/admin/class-settings.php';
        require_once WORDSURF_PLUGIN_DIR . 'includes/admin/editor/class-editor-interface.php';
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        // Initialize settings page.
        if ( class_exists( 'Wordsurf_Settings' ) ) {
            new Wordsurf_Settings();
        }

        // Initialize editor interface.
        if ( class_exists( 'Wordsurf_Editor_Interface' ) ) {
            new Wordsurf_Editor_Interface();
        }
    }
} 