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
        return 'Edit specific parts of the current WordPress post using search and replace. Shows proposed changes that the user can accept or reject. Perfect for surgical edits like fixing typos, modifying sentences, or adding content to specific locations without affecting the rest of the post. Always works on the post currently being edited.';
    }
    
    /**
     * Define the tool parameters declaratively
     * 
     * @return array
     */
    protected function define_parameters() {
        return [
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
        
        // Get current post ID from WordPress context (MVP: current post only)
        $post_id = get_the_ID();
        if (!$post_id) {
            // Fallback for admin context
            global $post;
            $post_id = $post ? $post->ID : null;
        }
        
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
                'error' => 'Missing required parameters: search_pattern and replacement_text are required. Current post ID: ' . ($post_id ?: 'none')
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
        
        // Perform smart text replacement that preserves HTML attributes
        $new_content = $this->smart_text_replace($current_content, $search_pattern, $replacement_text, $case_sensitive);
        
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
        
        // Generate a unique diff ID
        $diff_id = 'diff_' . uniqid();
        
        // Find target blocks and create wrapped versions
        $target_blocks_info = $this->find_and_wrap_target_blocks($current_content, $search_pattern, $diff_id, $replacement_text, $edit_type, $case_sensitive, $context);
        
        if (empty($target_blocks_info)) {
            return [
                'success' => false,
                'error' => "No matches found for pattern: {$search_pattern}",
                'suggestion' => 'Try using a shorter, more specific phrase instead of a long sentence.',
                'original_content' => $current_content,
                'search_pattern' => $search_pattern,
            ];
        }
        
        // Return diff data for user approval with target block information
        return [
            'success' => true,
            'message' => "Successfully prepared changes for post {$post_id}. Found " . count($target_blocks_info) . " blocks to modify.",
            'post_id' => $post_id,
            'edit_type' => $edit_type,
            'search_pattern' => $search_pattern,
            'replacement_text' => $replacement_text,
            'original_content' => $current_content,
            'new_content' => $new_content,
            'changes_found' => true,
            'action_required' => 'User must accept or reject the diff blocks in the editor',
            'target_blocks' => $target_blocks_info,
            'diff_id' => $diff_id
        ];
    }
    
    
    /**
     * Find target blocks containing search pattern and create diff wrapper information
     *
     * @param string $content Full post content
     * @param string $search_pattern Text to search for
     * @param string $diff_id Unique diff identifier
     * @param string $replacement_text Text to replace with
     * @param string $edit_type Type of edit (content, title, excerpt)
     * @param bool $case_sensitive Whether search is case sensitive
     * @return array Array of target block information for frontend processing
     */
    private function find_and_wrap_target_blocks($content, $search_pattern, $diff_id, $replacement_text, $edit_type, $case_sensitive, $context) {
        // Parse content into blocks
        $all_blocks = parse_blocks($content);
        
        // Filter out empty blocks (those with null blockName)
        $blocks = array_values(array_filter($all_blocks, function($block) {
            return !empty($block['blockName']);
        }));
        
        $target_blocks_info = [];
        
        // Debug: Log total blocks and their content
        error_log('Wordsurf DEBUG: Total blocks parsed: ' . count($all_blocks) . ', non-empty blocks: ' . count($blocks));
        foreach ($blocks as $idx => $block) {
            $block_name = $block['blockName'] ?: 'null';
            $block_content = isset($block['innerHTML']) ? substr(strip_tags($block['innerHTML']), 0, 50) : 'no content';
            error_log("Wordsurf DEBUG: Block $idx: name=$block_name, content_preview=$block_content");
        }
        
        foreach ($blocks as $block_index => $block) {
            // Check if this block contains the search pattern
            if ($this->block_contains_pattern($block, $search_pattern, $case_sensitive)) {
                error_log("Wordsurf DEBUG: Block $block_index contains pattern '$search_pattern'");
                // Create diff wrapper block for this target
                $wrapped_diff_block = $this->create_diff_wrapper_block($block, $diff_id, $search_pattern, $replacement_text, $edit_type, $case_sensitive, $context['_original_call_id'] ?? 'unknown');
                
                $target_blocks_info[] = [
                    'block_index' => $block_index,
                    'original_block' => serialize_block($block),
                    'diff_wrapper_block' => $wrapped_diff_block,
                    'block_content_preview' => substr(strip_tags(render_block($block)), 0, 100) . '...',
                    'search_pattern' => $search_pattern,
                    'replacement_text' => $replacement_text,
                    'contains_match' => true
                ];
            }
        }
        
        return $target_blocks_info;
    }
    
    
    /**
     * Check if a block contains the search pattern
     */
    private function block_contains_pattern($block, $search_pattern, $case_sensitive) {
        // Get the block's rendered content
        $block_content = render_block($block);
        
        if ($case_sensitive) {
            return strpos($block_content, $search_pattern) !== false;
        } else {
            return stripos($block_content, $search_pattern) !== false;
        }
    }
    
    /**
     * Create a diff wrapper block around a target block
     */
    private function create_diff_wrapper_block($target_block, $diff_id, $search_pattern, $replacement_text, $edit_type, $case_sensitive, $tool_call_id) {
        // Create diff block attributes
        $diff_attributes = [
            'diffId' => $diff_id,
            'diffType' => 'edit',
            'originalContent' => $search_pattern,
            'replacementContent' => $replacement_text,
            'status' => 'pending',
            'toolCallId' => $tool_call_id,
            'editType' => $edit_type,
            'searchPattern' => $search_pattern,
            'caseSensitive' => $case_sensitive,
            'isPreview' => true,
            'originalBlockContent' => serialize_block($target_block),
            'originalBlockType' => $target_block['blockName'] ?: 'core/paragraph'
        ];
        
        // Convert attributes to JSON for the block
        $attributes_json = json_encode($diff_attributes);
        
        // Return the diff block as a replacement, not a wrapper
        // The diff block will render the target content using its render.php
        return "<!-- wp:wordsurf/diff {$attributes_json} -->\n<!-- /wp:wordsurf/diff -->";
    }
    
    /**
     * Smart text replacement that only replaces visible text content, not HTML attributes
     */
    private function smart_text_replace($content, $search_text, $replacement, $case_sensitive = false) {
        // If content doesn't contain HTML tags, do simple replacement
        if (strpos($content, '<') === false) {
            return $case_sensitive ? str_replace($search_text, $replacement, $content) : str_ireplace($search_text, $replacement, $content);
        }
        
        // For HTML content, we need to be more careful
        // Split content into HTML tags and text nodes
        $parts = preg_split('/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        $result = '';
        
        foreach ($parts as $part) {
            if (strpos($part, '<') === 0 && strpos($part, '>') === strlen($part) - 1) {
                // This is an HTML tag - don't modify it
                $result .= $part;
            } else {
                // This is text content - safe to modify
                if ($case_sensitive) {
                    $part = str_replace($search_text, $replacement, $part);
                } else {
                    $part = str_ireplace($search_text, $replacement, $part);
                }
                $result .= $part;
            }
        }
        
        return $result;
    }
    
} 