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
        
        // Add endpoint for user feedback on AI suggestions
        add_action('wp_ajax_wordsurf_user_feedback', array($this, 'handle_user_feedback'));
        add_action('wp_ajax_nopriv_wordsurf_user_feedback', array($this, 'handle_user_feedback'));
        
        // Add endpoint for tool result continuation
        add_action('wp_ajax_wordsurf_continue_with_tool_result', array($this, 'handle_tool_result_continuation'));
        add_action('wp_ajax_nopriv_wordsurf_continue_with_tool_result', array($this, 'handle_tool_result_continuation'));
    }

    /**
     * Handle streaming chat message (bypasses REST API framework)
     */
    public function handle_stream_chat() {
        // We can now accept GET requests for EventSource compatibility.
        $request_data_source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

        // Verify nonce for security
        if (!wp_verify_nonce($request_data_source['nonce'] ?? '', 'wordsurf_nonce')) {
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
        $messages = isset($request_data_source['messages']) ? json_decode(stripslashes($request_data_source['messages']), true) : null;
        $post_id = isset($request_data_source['post_id']) ? intval($request_data_source['post_id']) : null;
        $context_window = isset($request_data_source['context_window']) ? json_decode(stripslashes($request_data_source['context_window']), true) : null;
        
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
        
        // Pass the request to the Agent Core to handle the entire chat lifecycle.
        $this->agent_core->handle_chat_request($request_data);
        
        wp_die(); // End the AJAX request
    }

    /**
     * Handle user feedback on AI suggestions
     */
    public function handle_user_feedback() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wordsurf_nonce')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid nonce']);
            exit;
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $user_action = $_POST['user_action'] ?? '';
        $tool_call_id = $_POST['tool_call_id'] ?? '';
        $diff_id = $_POST['diff_id'] ?? '';
        $post_id = intval($_POST['post_id'] ?? 0);

        // Log the feedback for context management
        error_log("Wordsurf DEBUG: Diff feedback received - Action: {$user_action}, Tool Call ID: {$tool_call_id}, Diff ID: {$diff_id}, Post: {$post_id}");

        // Store this feedback in context manager for AI awareness
        // The frontend has already updated the editor state
        // Backend only needs to track the decision for context/history
        
        // TODO: Implement context storage for tracking user preferences and decisions
        // This could include:
        // - Storing in post meta for persistence
        // - Tracking accept/reject patterns for learning
        // - Maintaining a history of changes

        // Send success response with chat continuation flag
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Diff feedback recorded',
            'continue_chat' => true,
            'tool_result' => [
                'tool_call_id' => $tool_call_id,
                'action' => $user_action
            ]
        ]);
        exit;
    }

    /**
     * Handle tool result continuation - send tool result to model for natural response
     */
    public function handle_tool_result_continuation() {
        // Handle both GET (EventSource) and POST requests
        $request_data_source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        
        // Verify nonce for security
        if (!wp_verify_nonce($request_data_source['nonce'] ?? '', 'wordsurf_nonce')) {
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

        $tool_call_id = $request_data_source['tool_call_id'] ?? '';
        $action = $request_data_source['user_action'] ?? '';
        $response_id = $request_data_source['response_id'] ?? '';

        if (!$tool_call_id || !$action || !$response_id) {
            http_response_code(400);
            echo 'Missing required parameters: tool_call_id, user_action, and response_id are required';
            exit;
        }

        // Create tool result in Responses API format
        $tool_results = [
            [
                'tool_call_id' => $tool_call_id,
                'content' => json_encode([
                    'success' => true,
                    'user_action' => $action,
                    'message' => $action === 'accepted' ? 
                        'The user accepted the proposed changes and they have been applied to the content.' :
                        'The user rejected the proposed changes and they have been reverted.'
                ])
            ]
        ];

        error_log('Wordsurf DEBUG: Tool result continuation using Responses API. Tool call ID: ' . $tool_call_id . ', Action: ' . $action . ', Response ID: ' . $response_id);

        // Set up streaming response headers
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Use Responses API continuation pattern instead of conversation history
        $this->agent_core->continue_with_tool_results($tool_results, $response_id);
        
        wp_die();
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