<?php
/**
 * Plugin Name:       Wordsurf
 * Plugin URI:        https://chubes.net/wordsurf
 * Description:       An AI agent for WordPress content creation and manipulation.
 * Version:           0.1.0
 * Author:            Chris Huber
 * Author URI:        https://chubes.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordsurf
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants.
 */
define( 'WORDSURF_VERSION', '0.1.0' );
define( 'WORDSURF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORDSURF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load AI HTTP Client library
 */
require_once WORDSURF_PLUGIN_DIR . 'lib/ai-http-client/ai-http-client.php';

/**
 * The code that runs during plugin activation.
 */
function activate_wordsurf() {
    // Activation logic here.
}
register_activation_hook( __FILE__, 'activate_wordsurf' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wordsurf() {
    // Deactivation logic here.
}
register_deactivation_hook( __FILE__, 'deactivate_wordsurf' );

/**
 * Enqueue scripts and styles for the editor
 */
function wordsurf_enqueue_editor_assets() {
    // Enqueue the main editor script
    wp_enqueue_script(
        'wordsurf-editor',
        WORDSURF_PLUGIN_URL . 'assets/js/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'),
        filemtime(WORDSURF_PLUGIN_DIR . 'assets/js/editor.js'),
        true
    );

    // Enqueue diff block script and styles
    wp_enqueue_script(
        'wordsurf-diff-block',
        WORDSURF_PLUGIN_URL . 'assets/js/diff-block.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'),
        filemtime(WORDSURF_PLUGIN_DIR . 'assets/js/diff-block.js'),
        true
    );

    // Enqueue tool call styles
    wp_enqueue_style(
        'wordsurf-tool-call',
        WORDSURF_PLUGIN_URL . 'assets/css/agent/tool-call.css',
        array(),
        filemtime(WORDSURF_PLUGIN_DIR . 'assets/css/agent/tool-call.css')
    );

    // Localize script with plugin data for both scripts
    $script_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wordsurf_nonce'),
        'pluginUrl' => WORDSURF_PLUGIN_URL,
    );
    
    wp_localize_script('wordsurf-editor', 'wordsurfData', $script_data);
    wp_localize_script('wordsurf-diff-block', 'wordsurfData', $script_data);
}
add_action('enqueue_block_editor_assets', 'wordsurf_enqueue_editor_assets');

/**
 * Register custom blocks
 */
function wordsurf_register_blocks() {
    // Register block styles
    wp_register_style(
        'wordsurf-diff-block',
        WORDSURF_PLUGIN_URL . 'includes/blocks/diff/src/style.css',
        array(),
        filemtime(WORDSURF_PLUGIN_DIR . 'includes/blocks/diff/src/style.css')
    );

    // Register the diff block using block.json
    register_block_type(WORDSURF_PLUGIN_DIR . 'includes/blocks/diff');
}
add_action('init', 'wordsurf_register_blocks');

/**
 * The core plugin class.
 */
final class Wordsurf {

    /**
     * The single instance of the class.
     *
     * @var Wordsurf
     */
    private static $instance;

    /**
     * Main Wordsurf Instance.
     *
     * Ensures only one instance of Wordsurf is loaded or can be loaded.
     *
     * @static
     * @return Wordsurf - Main instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Wordsurf Constructor.
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required core files.
     */
    private function includes() {
        // Core
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/class-agent-core.php';
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/class-chat-handler.php';
            require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/class-system-prompt.php';
            require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/class-tool-manager.php';
        require_once WORDSURF_PLUGIN_DIR . 'includes/agent/context/class-context-manager.php';

        // API
        require_once WORDSURF_PLUGIN_DIR . 'includes/api/class-rest-api.php';

        // Admin
            require_once WORDSURF_PLUGIN_DIR . 'includes/admin/class-admin.php';
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        // Initialize Chat Handler (this registers AJAX hooks)
        new Wordsurf_Chat_Handler();
        
        // Initialize REST API
        new Wordsurf_REST_API();
        
        if ( is_admin() ) {
            new Wordsurf_Admin();
        }
    }
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function wordsurf() {
    return Wordsurf::instance();
}

// Get Wordsurf running.
wordsurf(); 