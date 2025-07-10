<?php
/**
 * REST API Handler for Wordsurf
 * 
 * Handles chat messages and communicates with OpenAI API
 * 
 * @package Wordsurf
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Wordsurf_REST_API {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('wordsurf/v1', '/chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_chat_message'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'messages' => array(
                    'required' => false,
                    'type' => 'array',
                ),
                'message' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'post_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'context_window' => array(
                    'required' => false,
                    'type' => 'object',
                    'sanitize_callback' => array($this, 'sanitize_context'),
                ),
            ),
        ));
        
        register_rest_route('wordsurf/v1', '/post-context', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_context'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
    
    /**
     * Check if user has permission to use the API
     */
    public function check_permissions($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Sanitize context data
     */
    public function sanitize_context($context) {
        if (!is_array($context)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_keys = array('post_type', 'post_status', 'categories', 'tags', 'excerpt');
        
        foreach ($context as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Handle chat message from frontend (routes to Chat Handler)
     */
    public function handle_chat_message($request) {
        $params = $request->get_json_params();
        
        // The primary entry point is now the streaming ajax handler.
        // This REST endpoint is for non-streaming, which is deprecated.
        $chat_handler = new Wordsurf_Chat_Handler();
        return $chat_handler->handle_chat($params);
    }
    
    /**
     * Get post context for the agent
     */
    public function get_post_context($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('invalid_post', 'Invalid post ID', array('status' => 400));
        }
        
        $post = get_post($post_id);
        $context = array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_status' => $post->post_status,
            'post_type' => $post->post_type,
            'categories' => wp_get_post_categories($post_id, array('fields' => 'names')),
            'tags' => wp_get_post_tags($post_id, array('fields' => 'names')),
            'excerpt' => $post->post_excerpt,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'date' => $post->post_date,
        );
        
        return array(
            'success' => true,
            'context' => $context,
        );
    }
    

} 