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
     * @param int|null $post_id Optional post ID
     * @param array $additional_context Additional context data
     * @return array Complete context array
     */
    public function get_context($post_id = null, $additional_context = []) {
        $context = array(
            'current_user' => wp_get_current_user()->display_name,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
        );
        
        // Add post context if provided
        if ($post_id) {
            $context['current_post'] = $this->get_post_context($post_id);
            
            // Add tool execution history for this post
            $context['tool_history'] = $this->get_tool_execution_context($post_id);
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

    /**
     * Get tool execution history context for AI awareness
     * 
     * @param int $post_id
     * @param int|null $user_id Optional user ID, defaults to current user
     * @return string Formatted tool execution history
     */
    public function get_tool_execution_context($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Get completed tool history
        $history_key = "wordsurf_tool_history_{$user_id}_{$post_id}";
        $history = get_transient($history_key) ?: [];
        
        // Get pending tool executions (tools executed but not yet accepted/rejected)
        $pending_key = "wordsurf_pending_tools_{$user_id}_{$post_id}";
        $pending_tools = get_transient($pending_key) ?: [];
        
        $context_parts = [];
        
        // Add recent completed tools to context
        if (!empty($history)) {
            $context_parts[] = "## Recent Tool Execution History:";
            
            // Show last 5 tool executions for context
            $recent_history = array_slice($history, -5);
            foreach ($recent_history as $entry) {
                $timestamp = $entry['timestamp'];
                $tool_type = $entry['tool_type'];
                $user_action = $entry['user_action'];
                
                $action_text = $user_action === 'accepted' ? 'ACCEPTED' : 'REJECTED';
                
                if (isset($entry['tool_execution'])) {
                    $args = $entry['tool_execution']['arguments'];
                    $search_pattern = $args['search_pattern'] ?? 'unknown';
                    $replacement_text = $args['replacement_text'] ?? 'unknown';
                    
                    $context_parts[] = "- {$timestamp}: {$tool_type} tool executed - searched for \"{$search_pattern}\", proposed replacement \"{$replacement_text}\" - User {$action_text}";
                } else {
                    $context_parts[] = "- {$timestamp}: {$tool_type} tool - User {$action_text}";
                }
            }
        }
        
        // Add pending tools to context
        if (!empty($pending_tools)) {
            $context_parts[] = "\n## Pending Tool Results (awaiting user feedback):";
            
            foreach ($pending_tools as $tool) {
                $timestamp = $tool['timestamp'];
                $tool_name = $tool['tool_name'];
                $args = $tool['arguments'];
                
                $search_pattern = $args['search_pattern'] ?? 'unknown';
                $replacement_text = $args['replacement_text'] ?? 'unknown';
                
                $context_parts[] = "- {$timestamp}: {$tool_name} executed - searched for \"{$search_pattern}\", proposed replacement \"{$replacement_text}\" - AWAITING USER RESPONSE";
            }
        }
        
        // Provide guidance if no history
        if (empty($history) && empty($pending_tools)) {
            return "No previous tool executions for this post.";
        }
        
        $context_parts[] = "\n## Important Context Notes:";
        $context_parts[] = "- If a tool execution was ACCEPTED, those changes have been applied to the post content";
        $context_parts[] = "- If a tool execution was REJECTED, those changes were NOT applied";
        $context_parts[] = "- If a tool is AWAITING USER RESPONSE, do not repeat the same suggestion";
        $context_parts[] = "- Always check current post content with read_post before making new suggestions";
        
        return implode("\n", $context_parts);
    }
} 