<?php
/**
 * Post Context Helper
 * 
 * Provides robust post ID detection across all WordPress contexts (frontend, admin, AJAX, CLI, etc.)
 * Solves the problem of get_the_ID() not working in AJAX context while maintaining multi-post support.
 *
 * @package Wordsurf
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Wordsurf_Post_Context_Helper {
    
    /**
     * @var int|null Cached current post ID for AJAX context
     */
    private static $current_post_id_cache = null;
    
    /**
     * Set the current post ID for AJAX context
     * 
     * @param int $post_id
     */
    public static function set_current_post_id($post_id) {
        self::$current_post_id_cache = intval($post_id);
    }
    
    /**
     * Get post ID with fallback logic for all WordPress contexts
     * 
     * @param int|null $explicit_post_id If provided, validates and returns this
     * @return int|null Post ID or null if none found
     */
    public static function get_post_id($explicit_post_id = null) {
        // If explicit post ID provided, validate and return it
        if ($explicit_post_id) {
            $post_id = intval($explicit_post_id);
            if ($post_id > 0 && get_post($post_id)) {
                return $post_id;
            }
        }
        
        // Try WordPress built-in current post detection (works in frontend/admin)
        $post_id = get_the_ID();
        if ($post_id) {
            return $post_id;
        }
        
        // Try global $post object (works in some contexts)
        global $post;
        if ($post && isset($post->ID) && $post->ID > 0) {
            return $post->ID;
        }
        
        // Try cached post ID (set during AJAX context setup)
        if (self::$current_post_id_cache) {
            return self::$current_post_id_cache;
        }
        
        // Try to get from $_GET/_POST (common in admin contexts)
        if (isset($_GET['post']) && is_numeric($_GET['post'])) {
            $post_id = intval($_GET['post']);
            if ($post_id > 0 && get_post($post_id)) {
                return $post_id;
            }
        }
        
        if (isset($_POST['post_ID']) && is_numeric($_POST['post_ID'])) {
            $post_id = intval($_POST['post_ID']);
            if ($post_id > 0 && get_post($post_id)) {
                return $post_id;
            }
        }
        
        // No post ID found
        return null;
    }
    
    /**
     * Get post ID for current post context specifically
     * This is the method tools should use when they need the "current" post
     * 
     * @return int|null Current post ID or null if not in post context
     */
    public static function get_current_post_id() {
        return self::get_post_id();
    }
    
    /**
     * Get post ID for a specific post (for cross-post operations)
     * 
     * @param int $post_id The specific post ID
     * @return int|null Post ID if valid, null otherwise
     */
    public static function get_specific_post_id($post_id) {
        return self::get_post_id($post_id);
    }
    
    /**
     * Check if we're in a valid post context
     * 
     * @return bool
     */
    public static function has_post_context() {
        return self::get_current_post_id() !== null;
    }
    
    /**
     * Clear the cached post ID (useful for testing)
     */
    public static function clear_cache() {
        self::$current_post_id_cache = null;
    }
}