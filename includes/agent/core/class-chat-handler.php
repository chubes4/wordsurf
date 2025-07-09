<?php
/**
 * Wordsurf Chat Handler
 *
 * Handles WordPress hooks for chat functionality and routes to Agent Core
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Wordsurf_Chat_Handler {
    
    /**
     * Agent Core instance
     *
     * @var Wordsurf_Agent_Core
     */
    private $agent_core;

    /**
     * Constructor
     */
    public function __construct() {
        $this->agent_core = new Wordsurf_Agent_Core();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Add direct streaming endpoint that bypasses REST API framework
        add_action('wp_ajax_wordsurf_stream_chat', array($this, 'handle_stream_chat'));
        add_action('wp_ajax_nopriv_wordsurf_stream_chat', array($this, 'handle_stream_chat'));
    }

    /**
     * Handle streaming chat message (bypasses REST API framework)
     */
    public function handle_stream_chat() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wordsurf_nonce')) {
            http_response_code(403);
            echo 'Invalid nonce';
            exit;
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        // Get form data
        $messages = isset($_POST['messages']) ? json_decode(stripslashes($_POST['messages']), true) : null;
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        $context_window = isset($_POST['context_window']) ? json_decode(stripslashes($_POST['context_window']), true) : null;
        
        $request_data = [
            'messages' => $messages,
            'post_id' => $post_id,
            'context_window' => $context_window,
        ];

        // Debug: Log the incoming streaming request
        error_log('Wordsurf DEBUG: Chat Handler stream endpoint called. Request data: ' . json_encode($request_data));

        if (empty($messages) || !is_array($messages)) {
            http_response_code(400);
            echo 'Message history cannot be empty';
            exit;
        }

        // Set up streaming response headers - BEFORE any output
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable nginx buffering
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Create a callback function that the Agent Core can use to stream data.
        $stream_callback = function($data) {
            echo $data;
            flush();
        };

        // Pass the request to the Agent Core to handle the entire chat lifecycle.
        $this->agent_core->handle_chat_request($request_data, $stream_callback);

        wp_die(); // End the AJAX request
    }

    /**
     * Handle non-streaming chat (for REST API or other uses)
     */
    public function handle_chat($request_data) {
        // This is now a legacy path. The new architecture is fully streaming.
        // We will return an error to indicate this method should not be used.
        return new WP_Error(
            'not_supported',
            __('Non-streaming chat is not supported in the current architecture.', 'wordsurf'),
            ['status' => 400]
        );
    }
} 