<?php
/**
 * Wordsurf Context Manager
 *
 * Handles gathering and formatting context information for the AI agent.
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Wordsurf_Context_Manager {
    
    /**
     * Get comprehensive context for the AI agent
     *
     * @param int|null $post_id Optional post ID (deprecated - will auto-detect current post for MVP simplicity)
     * @param array $additional_context Additional context data
     * @return array Complete context array
     */
    public function get_context($post_id = null, $additional_context = []) {
        $context = array(
            'current_user' => wp_get_current_user()->display_name,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
        );
        
        // Use Post Context Helper for robust post ID detection across all contexts
        $resolved_post_id = Wordsurf_Post_Context_Helper::get_post_id($post_id);
        
        // Add post context if we have a post ID
        if ($resolved_post_id) {
            $context['current_post'] = $this->get_post_context($resolved_post_id);
        }
        
        // Merge any additional context
        if (!empty($additional_context)) {
            $context = array_merge($context, $additional_context);
        }
        
        return $context;
    }
    
    /**
     * Get context for a specific post
     *
     * @param int $post_id
     * @return array|null Post context or null if post doesn't exist
     */
    public function get_post_context($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return null;
        }
        
        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'categories' => wp_get_post_categories($post_id, array('fields' => 'names')),
            'tags' => wp_get_post_tags($post_id, array('fields' => 'names')),
            'excerpt' => $post->post_excerpt,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'date' => $post->post_date,
        );
    }
    
    /**
     * Get user context
     *
     * @return array User context information
     */
    public function get_user_context() {
        $user = wp_get_current_user();
        
        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'capabilities' => $user->allcaps,
        );
    }
    
    /**
     * Get site context
     *
     * @return array Site context information
     */
    public function get_site_context() {
        return array(
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => get_site_url(),
            'admin_url' => admin_url(),
            'version' => get_bloginfo('version'),
        );
    }
} 