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
        
        // Generate a unique diff ID
        $diff_id = 'diff_' . uniqid();
        
        // For write_to_post, we need to wrap ALL existing blocks in a single diff block
        // Parse the current content to get all existing blocks
        $current_blocks = parse_blocks($current_content);
        
        // Create target blocks info - replace all blocks with a single diff block
        $target_blocks_info = [];
        
        // We'll replace everything starting at block index 0
        $target_blocks_info[] = [
            'block_index' => 0,
            'diff_block_content' => $this->create_full_replacement_diff_block($current_content, $new_content, $diff_id),
            'is_full_replacement' => true,
            'replace_count' => count($current_blocks) // How many blocks to replace
        ];
        return [
            'success' => true,
            'preview' => true,
            'message' => "Successfully prepared to replace entire content for post {$post_id}. The entire post will be wrapped in a diff block for review.",
            'post_id' => $post_id,
            'edit_type' => 'content',
            'search_pattern' => $current_content, // The entire current content
            'replacement_text' => $new_content, // The entire new content
            'original_content' => $current_content,
            'new_content' => $new_content,
            'target_blocks' => $target_blocks_info, // Use target_blocks approach
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
            'action_required' => 'User must accept or reject the complete content replacement in the editor',
            'diff_id' => $diff_id
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
    
    /**
     * Create a diff block for full post replacement
     * 
     * @param string $current_content The current post content
     * @param string $new_content The new post content
     * @param string $diff_id The unique diff ID
     * @return string The serialized diff block
     */
    private function create_full_replacement_diff_block($current_content, $new_content, $diff_id) {
        // Create the diff block attributes
        $diff_attributes = [
            'diffId' => $diff_id,
            'diffType' => 'write', // Special type for full post replacement
            'originalContent' => $current_content, // The current post content
            'replacementContent' => $new_content,
            'status' => 'pending',
            'toolCallId' => $context['_original_call_id'] ?? 'unknown',
            'editType' => 'full_post', // Indicates this is a full post replacement
            'searchPattern' => '', // Not used for full replacement
            'caseSensitive' => false,
            'isPreview' => true,
            'originalBlockContent' => $current_content, // Store original content for revert
            'originalBlockType' => 'full_post'
        ];
        
        // Convert attributes to JSON for the block
        $attributes_json = json_encode($diff_attributes);
        
        // Create the diff block that will wrap the entire post content
        $diff_block = "<!-- wp:wordsurf/diff {$attributes_json} -->\n";
        $diff_block .= $new_content; // Include the new content inside the diff block
        $diff_block .= "\n<!-- /wp:wordsurf/diff -->";
        
        return $diff_block;
    }
} 