<?php
/**
 * Wordsurf Insert Content Tool
 *
 * Allows inserting new content into posts at specific positions
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once WORDSURF_PLUGIN_DIR . 'includes/agent/core/tools/basetool.php';

class Wordsurf_InsertContentTool extends Wordsurf_BaseTool {
    
    /**
     * Get the tool name
     */
    public function get_name() {
        return 'insert_content';
    }
    
    /**
     * Get the tool description for the AI
     */
    public function get_description() {
        return 'Insert new content into the current WordPress post at specific positions without modifying existing content. Perfect for adding new paragraphs, sections, or content blocks to the beginning, end, or middle of posts. Always works on the post currently being edited.';
    }
    
    /**
     * Define the tool parameters declaratively
     * 
     * @return array
     */
    protected function define_parameters() {
        return [
            'content' => [
                'type' => 'string',
                'description' => 'The new content to insert (will be wrapped in WordPress paragraph blocks automatically)',
                'required' => true
            ],
            'position' => [
                'type' => 'string',
                'enum' => ['beginning', 'end', 'after_paragraph'],
                'description' => 'Where to insert the content: beginning (start of post), end (end of post), or after_paragraph (after a specific paragraph)',
                'default' => 'end',
                'required' => true
            ],
            'target_paragraph_text' => [
                'type' => 'string',
                'description' => 'Required if position is "after_paragraph". A short phrase from the paragraph after which to insert content (3-8 words max)',
                'required' => false
            ],
            'content_type' => [
                'type' => 'string',
                'enum' => ['content', 'title', 'excerpt'],
                'description' => 'Which part of the post to insert into',
                'default' => 'content',
                'required' => true
            ]
        ];
    }
    
    /**
     * Execute the insert_content tool
     */
    public function execute($context = []) {
        // Debug: Log the parameters
        error_log('Wordsurf DEBUG: insert_content tool called with parameters: ' . json_encode($context));
        
        // Get current post ID from WordPress context (MVP: current post only)
        $post_id = get_the_ID();
        if (!$post_id) {
            // Fallback for admin context
            global $post;
            $post_id = $post ? $post->ID : null;
        }
        
        $content = $context['content'] ?? null;
        $position = $context['position'] ?? 'end';
        $target_paragraph_text = $context['target_paragraph_text'] ?? null;
        $content_type = $context['content_type'] ?? 'content';
        
        // Validate required parameters
        if (!$post_id || !$content) {
            return [
                'success' => false,
                'error' => 'Missing required parameter: content is required. Current post ID: ' . ($post_id ?: 'none')
            ];
        }
        
        if ($position === 'after_paragraph' && !$target_paragraph_text) {
            return [
                'success' => false,
                'error' => 'target_paragraph_text is required when position is "after_paragraph"'
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
        
        // Get the current content based on content_type
        $current_content = '';
        switch ($content_type) {
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
        
        // Prepare the content to insert (wrap in WordPress paragraph block)
        $content_to_insert = "\n\n<!-- wp:paragraph -->\n<p>" . $content . "</p>\n<!-- /wp:paragraph -->";
        
        // Determine where to insert based on position
        $new_content = '';
        $insertion_point = '';
        
        switch ($position) {
            case 'beginning':
                $new_content = $content_to_insert . "\n\n" . $current_content;
                $insertion_point = 'at the beginning of the post';
                break;
                
            case 'end':
                $new_content = $current_content . $content_to_insert;
                $insertion_point = 'at the end of the post';
                break;
                
            case 'after_paragraph':
                // Find the target paragraph and insert after it
                // Use a simpler approach: find the paragraph, then insert after its closing tag
                $search_text = '<!-- /wp:paragraph -->';
                $paragraphs = explode($search_text, $current_content);
                $found = false;
                $target_paragraph_index = -1;
                
                // Find which paragraph contains our target text
                foreach ($paragraphs as $index => $paragraph) {
                    if (strpos($paragraph, $target_paragraph_text) !== false) {
                        $target_paragraph_index = $index;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    return [
                        'success' => false,
                        'error' => "Could not find paragraph containing '{$target_paragraph_text}' in the post content",
                        'suggestion' => 'Try using a shorter, more specific phrase from the target paragraph',
                        'available_paragraphs' => $this->extract_paragraph_previews($current_content)
                    ];
                }
                
                // Reconstruct content with insertion
                $new_paragraphs = [];
                foreach ($paragraphs as $index => $paragraph) {
                    $new_paragraphs[] = $paragraph;
                    
                    // Add the closing tag back (except for the last paragraph)
                    if ($index < count($paragraphs) - 1) {
                        $new_paragraphs[] = $search_text;
                    }
                    
                    // Insert new content after the target paragraph
                    if ($index === $target_paragraph_index) {
                        $new_paragraphs[] = $content_to_insert;
                    }
                }
                
                $new_content = implode('', $new_paragraphs);
                $insertion_point = "after the paragraph containing '{$target_paragraph_text}'";
                break;
                
            default:
                return [
                    'success' => false,
                    'error' => "Invalid position: {$position}. Must be 'beginning', 'end', or 'after_paragraph'"
                ];
        }
        
        // Generate a unique diff ID
        $diff_id = 'diff_' . uniqid();
        
        // Create diff block content for insertion
        $diff_block_content = $this->create_insert_diff_block_content($diff_id, $content, $position, $insertion_point, $content_type);
        
        // Return diff data for user approval with diff block content
        return [
            'success' => true,
            'message' => "Successfully prepared to insert new content {$insertion_point}. The user will see a diff block with the proposed insertion that can be accepted or rejected.",
            'post_id' => $post_id,
            'edit_type' => $content_type,
            'search_pattern' => '', // Not applicable for insertions
            'replacement_text' => $content,
            'original_content' => $current_content,
            'new_content' => $new_content,
            'tool_type' => 'insert_content',
            'position' => $position,
            'insertion_point' => $insertion_point,
            'inserted_content' => $content,
            'changes_found' => true,
            'action_required' => 'User must accept or reject the diff block in the editor',
            'diff_block_content' => $diff_block_content,
            'diff_id' => $diff_id
        ];
    }
    
    /**
     * Extract first N words from content for anchor positioning
     */
    private function extract_first_words($content, $word_count = 10) {
        $text = strip_tags($content);
        $text = trim($text);
        $words = explode(' ', $text);
        $first_words = array_slice($words, 0, $word_count);
        return implode(' ', $first_words);
    }
    
    /**
     * Extract last N words from content for anchor positioning
     */
    private function extract_last_words($content, $word_count = 10) {
        $text = strip_tags($content);
        $text = trim($text);
        $words = explode(' ', $text);
        $last_words = array_slice($words, -$word_count);
        return implode(' ', $last_words);
    }
    
    /**
     * Extract preview text from paragraphs to help with debugging
     */
    private function extract_paragraph_previews($content) {
        $previews = [];
        $paragraphs = explode('<!-- wp:paragraph -->', $content);
        
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph)) {
                // Extract just the text content from the HTML
                $text = strip_tags($paragraph);
                $text = trim($text);
                if ($text) {
                    $previews[] = substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '');
                }
            }
        }
        
        return $previews;
    }
    
    /**
     * Create diff block content for insertions
     *
     * @param string $diff_id
     * @param string $content
     * @param string $position
     * @param string $insertion_point
     * @param string $content_type
     * @return string
     */
    private function create_insert_diff_block_content($diff_id, $content, $position, $insertion_point, $content_type) {
        // Create the diff block with all necessary attributes
        $block_attributes = [
            'diffId' => $diff_id,
            'diffType' => 'insert',
            'originalContent' => '',
            'replacementContent' => $content,
            'status' => 'pending',
            'toolCallId' => $context['_original_call_id'] ?? 'unknown',
            'editType' => $content_type,
            'searchPattern' => '',
            'caseSensitive' => false,
            'isPreview' => true,
            'position' => $position,
            'insertionPoint' => $insertion_point,
        ];
        
        // Convert attributes to JSON for the block
        $attributes_json = json_encode($block_attributes);
        
        // Create the diff block content - let WordPress handle the rendering
        $diff_block = "<!-- wp:wordsurf/diff {$attributes_json} -->\n<!-- /wp:wordsurf/diff -->";
        
        return $diff_block;
    }
} 