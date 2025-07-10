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
     * Handle user feedback on AI suggestions
     */
    public function handle_user_feedback() {
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

        $user_action = $_POST['user_action'] ?? '';
        $tool_type = $_POST['tool_type'] ?? '';
        $post_id = intval($_POST['post_id'] ?? 0);
        $tool_data = isset($_POST['tool_data']) ? json_decode(stripslashes($_POST['tool_data']), true) : [];

        // Log the feedback for context management
        error_log("Wordsurf DEBUG: User feedback received - Action: {$user_action}, Tool: {$tool_type}, Post: {$post_id}");

        // Store this feedback in tool execution history for future AI awareness
        $this->store_tool_feedback($post_id, $tool_type, $user_action, $tool_data);

        // Send success response
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Feedback recorded']);
        exit;
    }

    /**
     * Store tool execution feedback for future AI context
     * 
     * @param int $post_id
     * @param string $tool_type
     * @param string $user_action ('accepted' or 'rejected')
     * @param array $tool_data Additional tool execution data
     */
    private function store_tool_feedback($post_id, $tool_type, $user_action, $tool_data = []) {
        $user_id = get_current_user_id();
        $history_key = "wordsurf_tool_history_{$user_id}_{$post_id}";
        $pending_key = "wordsurf_pending_tools_{$user_id}_{$post_id}";
        
        // Get existing history and pending tools
        $history = get_transient($history_key) ?: [];
        $pending_tools = get_transient($pending_key) ?: [];
        
        // Find the most recent matching pending tool execution
        $matching_tool = null;
        $matching_index = null;
        
        // Look for the most recent tool of this type that's still pending
        for ($i = count($pending_tools) - 1; $i >= 0; $i--) {
            if ($pending_tools[$i]['tool_name'] === $tool_type && 
                $pending_tools[$i]['status'] === 'pending_user_feedback') {
                $matching_tool = $pending_tools[$i];
                $matching_index = $i;
                break;
            }
        }
        
        // Create comprehensive feedback entry
        $feedback_entry = [
            'timestamp' => current_time('mysql'),
            'tool_type' => $tool_type,
            'user_action' => $user_action,
            'tool_data' => $tool_data,
            'post_id' => $post_id
        ];
        
        // If we found a matching tool execution, include its details
        if ($matching_tool) {
            $feedback_entry['tool_execution'] = [
                'arguments' => $matching_tool['arguments'],
                'result' => $matching_tool['result'],
                'executed_at' => $matching_tool['timestamp']
            ];
            
            // Remove the tool from pending list since it's now resolved
            array_splice($pending_tools, $matching_index, 1);
            set_transient($pending_key, $pending_tools, 2 * HOUR_IN_SECONDS);
        }
        
        // Add to history (keep last 20 entries to prevent unlimited growth)
        $history[] = $feedback_entry;
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        
        // Store for 24 hours (can be adjusted based on needs)
        set_transient($history_key, $history, 24 * HOUR_IN_SECONDS);
        
        error_log("Wordsurf DEBUG: Stored tool feedback - Key: {$history_key}, Total entries: " . count($history) . ", Pending tools remaining: " . count($pending_tools));
    }

    /**
     * Get tool execution history for a specific post and user
     * 
     * @param int $post_id
     * @param int|null $user_id Optional user ID, defaults to current user
     * @return array Tool execution history
     */
    public function get_tool_history($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $history_key = "wordsurf_tool_history_{$user_id}_{$post_id}";
        return get_transient($history_key) ?: [];
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