<?php
/**
 * Wordsurf Read Post Tool
 *
 * @package Wordsurf
 * @since   0.1.0
 */

require_once __DIR__ . '/basetool.php';

class Wordsurf_ReadPostTool extends Wordsurf_BaseTool {
    
    /**
     * Get the tool name (unique identifier)
     */
    public function get_name() {
        return 'read_post';
    }

    /**
     * Get the tool description for the AI
     */
    public function get_description() {
        return 'Read the content of the current WordPress post being edited. Use this to understand what content is currently in the post. This tool provides comprehensive post information including title, content, excerpt, metadata, and statistics. Always works on the post currently being edited.';
    }
    
    /**
     * Define the tool parameters declaratively
     * 
     * @return array
     */
    protected function define_parameters() {
        return [
            // No parameters needed - always reads the current post being edited
        ];
    }

    /**
     * Execute the tool logic
     * 
     * @param array $context
     * @return array
     */
    public function execute($context = []) {
        // Get current post ID from WordPress context (MVP: current post only)
        $post_id = get_the_ID();
        if (!$post_id) {
            // Fallback for admin context
            global $post;
            $post_id = $post ? $post->ID : null;
        }
        
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No current post found. This tool works on the post currently being edited.'
            ];
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            return [
                'success' => false,
                'error' => 'Post not found with ID: ' . $post_id
            ];
        }
        
        // Get additional post data
        $categories = wp_get_post_categories($post_id, ['fields' => 'names']);
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
        $author = get_the_author_meta('display_name', $post->post_author);
        
        // Calculate content statistics
        $content = $post->post_content;
        $word_count = str_word_count(strip_tags($content));
        $character_count = strlen($content);
        $paragraph_count = substr_count($content, "\n\n") + 1;
        
        return [
            'success' => true,
            'data' => [
                'id' => $post_id,
                'title' => $post->post_title,
                'content' => $content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'author' => $author,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'categories' => $categories,
                'tags' => $tags,
                'statistics' => [
                    'word_count' => $word_count,
                    'character_count' => $character_count,
                    'paragraph_count' => $paragraph_count
                ]
            ]
        ];
    }
} 