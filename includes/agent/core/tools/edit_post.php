<?php
/**
 * Wordsurf Edit Post Tool
 *
 * Allows surgical editing of post content using regex search and replace
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/basetool.php';

class Wordsurf_EditPostTool extends Wordsurf_BaseTool {
    
    /**
     * Get the tool name
     */
    public function get_name() {
        return 'edit_post';
    }
    
    /**
     * Get the tool description for the AI
     */
    public function get_description() {
        return 'Edit specific parts of a WordPress post using regex search and replace. Shows proposed changes that the user can accept or reject. Perfect for surgical edits like fixing typos, modifying sentences, or adding content to specific locations without affecting the rest of the post.';
    }
    
    /**
     * Define the tool parameters declaratively
     * 
     * @return array
     */
    protected function define_parameters() {
        return [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The ID of the post to edit',
                'required' => true
            ],
            'search_pattern' => [
                'type' => 'string',
                'description' => 'The text to search for in the post content',
                'required' => true
            ],
            'replacement_text' => [
                'type' => 'string',
                'description' => 'The text to replace the matched pattern with',
                'required' => true
            ],
            'edit_type' => [
                'type' => 'string',
                'enum' => ['content', 'title', 'excerpt'],
                'description' => 'Which part of the post to edit',
                'default' => 'content',
                'required' => true
            ],
            'case_sensitive' => [
                'type' => 'boolean',
                'description' => 'Whether the search should be case sensitive',
                'default' => false,
                'required' => true
            ]
        ];
    }
    
    /**
     * Execute the edit_post tool
     */
    public function execute($context = []) {
        // Debug: Log the parameters being passed to edit_post tool
        error_log('Wordsurf DEBUG: edit_post tool called with parameters: ' . json_encode($context));
        
        $post_id = $context['post_id'] ?? null;
        $search_pattern = $context['search_pattern'] ?? null;
        $replacement_text = $context['replacement_text'] ?? null;
        $edit_type = $context['edit_type'] ?? 'content';
        $case_sensitive = $context['case_sensitive'] ?? false;
        
        // Debug: Log tool execution 
        error_log('Wordsurf DEBUG: edit_post tool executed - returning diff data for user approval');
        
        // Validate required parameters
        if (!$post_id || !$search_pattern || !$replacement_text) {
            return [
                'success' => false,
                'error' => 'Missing required parameters: post_id, search_pattern, and replacement_text are required'
            ];
        }
        
        // Check if post exists and user has permission to edit
        $post = get_post($post_id);
        if (!$post) {
            return [
                'success' => false,
                'error' => "Post with ID {$post_id} not found"
            ];
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to edit this post'
            ];
        }
        
        // Get the current content based on edit_type
        $current_content = '';
        switch ($edit_type) {
            case 'title':
                $current_content = $post->post_title;
                break;
            case 'excerpt':
                $current_content = $post->post_excerpt;
                break;
            case 'content':
            default:
                $current_content = $post->post_content;
                break;
        }
        
        // Prepare regex flags
        $flags = $case_sensitive ? '' : 'i';
        
        // Escape the search pattern for regex use (since users provide plain text)
        $escaped_pattern = preg_quote($search_pattern, '/');
        
        // Perform the search and replace
        $new_content = preg_replace("/{$escaped_pattern}/{$flags}", $replacement_text, $current_content);
        
        // Check if any replacements were made
        if ($new_content === $current_content) {
            return [
                'success' => false,
                'error' => "No matches found for pattern: {$search_pattern}",
                'suggestion' => 'Try using a shorter, more specific phrase instead of a long sentence. For example, use just a few words that uniquely identify the location you want to edit.',
                'original_content' => $current_content,
                'search_pattern' => $search_pattern,
                'debug_info' => [
                    'pattern_length' => strlen($search_pattern),
                    'content_preview' => substr($current_content, 0, 200) . '...'
                ]
            ];
        }
        
        // Return diff data for user approval - this is how the tool works
        return [
            'success' => true,
            'preview' => true,
            'message' => "Successfully prepared changes for post {$post_id}. The user will see '{$replacement_text}' highlighted in green in the content and can choose to accept or reject this change.",
            'post_id' => $post_id,
            'edit_type' => $edit_type,
            'search_pattern' => $search_pattern,
            'replacement_text' => $replacement_text,
            'original_content' => $current_content,
            'new_content' => $new_content,
            'changes_found' => true,
            'action_required' => 'User must accept or reject the highlighted changes in the editor'
        ];
    }
} 