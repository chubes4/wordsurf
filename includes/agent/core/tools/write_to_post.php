<?php
/**
 * Wordsurf Write to Post Tool
 *
 * Allows complete replacement of post content, perfect for writing from scratch or total rewrites
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/basetool.php';

class Wordsurf_WriteToPostTool extends Wordsurf_BaseTool {
    
    /**
     * Get the tool name
     */
    public function get_name() {
        return 'write_to_post';
    }
    
    /**
     * Get the tool description for the AI
     */
    public function get_description() {
        return 'Replace the entire content of a WordPress post with new content. Perfect for writing posts from scratch, complete rewrites, or starting over with a blank slate. Shows a full post preview that the user can accept or reject.';
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
                'description' => 'The ID of the post to write content to',
                'required' => true
            ],
            'content' => [
                'type' => 'string',
                'description' => 'The new content for the post (will be formatted with proper WordPress paragraph blocks)',
                'required' => true
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Optional new title for the post (leave empty to keep existing title)',
                'required' => false
            ],
            'excerpt' => [
                'type' => 'string',
                'description' => 'Optional new excerpt for the post (leave empty to keep existing excerpt)',
                'required' => false
            ],
            'format_as_blocks' => [
                'type' => 'boolean',
                'description' => 'Whether to automatically format content as WordPress blocks (recommended)',
                'default' => true,
                'required' => false
            ]
        ];
    }
    
    /**
     * Execute the write_to_post tool
     */
    public function execute($context = []) {
        // Debug: Log the parameters
        error_log('Wordsurf DEBUG: write_to_post tool called with parameters: ' . json_encode($context));
        
        $post_id = $context['post_id'] ?? null;
        $content = $context['content'] ?? null;
        $title = $context['title'] ?? null;
        $excerpt = $context['excerpt'] ?? null;
        $format_as_blocks = $context['format_as_blocks'] ?? true;
        
        // Validate required parameters
        if (!$post_id || !$content) {
            return [
                'success' => false,
                'error' => 'Missing required parameters: post_id and content are required'
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
        
        // Get current post data
        $current_title = $post->post_title;
        $current_content = $post->post_content;
        $current_excerpt = $post->post_excerpt;
        
        // Prepare new content
        $new_content = $this->format_content($content, $format_as_blocks);
        $new_title = $title !== null ? $title : $current_title;
        $new_excerpt = $excerpt !== null ? $excerpt : $current_excerpt;
        
        // Determine what's changing
        $changes = [];
        if ($new_title !== $current_title) {
            $changes[] = 'title';
        }
        if ($new_content !== $current_content) {
            $changes[] = 'content';
        }
        if ($new_excerpt !== $current_excerpt) {
            $changes[] = 'excerpt';
        }
        
        if (empty($changes)) {
            return [
                'success' => false,
                'error' => 'No changes detected. The new content is identical to the existing content.'
            ];
        }
        
        // Calculate content statistics
        $original_stats = $this->calculate_content_stats($current_content);
        $new_stats = $this->calculate_content_stats($new_content);
        
        // Return standardized diff data for inline highlighting (matches edit_post format)
        return [
            'success' => true,
            'preview' => true,
            'message' => "Successfully prepared to replace entire content for post {$post_id}. The user will see the new content highlighted in the editor and can choose to accept or reject this replacement.",
            'post_id' => $post_id,
            'edit_type' => 'content',
            'search_pattern' => $current_content, // The entire current content
            'replacement_text' => $new_content, // The entire new content
            'original_content' => $current_content,
            'new_content' => $new_content,
            'tool_type' => 'write_to_post',
            'changes' => $changes,
            'original_title' => $current_title,
            'new_title' => $new_title,
            'original_excerpt' => $current_excerpt,
            'new_excerpt' => $new_excerpt,
            'statistics' => [
                'original' => $original_stats,
                'new' => $new_stats,
                'change' => [
                    'words' => $new_stats['words'] - $original_stats['words'],
                    'characters' => $new_stats['characters'] - $original_stats['characters'],
                    'paragraphs' => $new_stats['paragraphs'] - $original_stats['paragraphs']
                ]
            ],
            'changes_found' => true,
            'action_required' => 'User must accept or reject the complete content replacement in the editor'
        ];
    }
    
    /**
     * Format content with proper WordPress blocks
     */
    private function format_content($content, $format_as_blocks = true) {
        if (!$format_as_blocks) {
            return $content;
        }
        
        // If content already has block markup, return as-is
        if (strpos($content, '<!-- wp:') !== false) {
            return $content;
        }
        
        // Split content into paragraphs and format as blocks
        $paragraphs = preg_split('/\n\s*\n/', trim($content));
        $formatted_content = '';
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            // Check if it looks like a heading
            if (preg_match('/^#{1,6}\s+(.+)$/', $paragraph, $matches)) {
                $level = strlen(explode(' ', $paragraph)[0]);
                $heading_text = $matches[1];
                $formatted_content .= "<!-- wp:heading {\"level\":{$level}} -->\n";
                $formatted_content .= "<h{$level}>{$heading_text}</h{$level}>\n";
                $formatted_content .= "<!-- /wp:heading -->\n\n";
            } else {
                // Regular paragraph
                $formatted_content .= "<!-- wp:paragraph -->\n";
                $formatted_content .= "<p>{$paragraph}</p>\n";
                $formatted_content .= "<!-- /wp:paragraph -->\n\n";
            }
        }
        
        return trim($formatted_content);
    }
    
    /**
     * Calculate content statistics
     */
    private function calculate_content_stats($content) {
        // Strip HTML and block markup for accurate word count
        $text_content = strip_tags($content);
        $text_content = preg_replace('/<!--.*?-->/s', '', $text_content);
        $text_content = trim($text_content);
        
        // Count words (split by whitespace, filter empty)
        $words = empty($text_content) ? 0 : count(array_filter(preg_split('/\s+/', $text_content)));
        
        // Count characters
        $characters = strlen($text_content);
        
        // Count paragraphs (look for paragraph blocks)
        $paragraphs = substr_count($content, '<!-- wp:paragraph -->');
        if ($paragraphs === 0 && !empty($text_content)) {
            // Fallback: count by double line breaks
            $paragraphs = count(array_filter(preg_split('/\n\s*\n/', $text_content)));
        }
        
        return [
            'words' => $words,
            'characters' => $characters,
            'paragraphs' => max($paragraphs, 0)
        ];
    }
} 