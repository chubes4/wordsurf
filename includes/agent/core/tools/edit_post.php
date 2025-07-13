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
        
        // Generate a unique diff ID
        $diff_id = 'diff_' . uniqid();
        
        // Find target blocks and create wrapped versions
        $target_blocks_info = $this->find_and_wrap_target_blocks($current_content, $search_pattern, $diff_id, $replacement_text, $edit_type, $case_sensitive);
        
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
            'preview' => true,
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
    private function find_and_wrap_target_blocks($content, $search_pattern, $diff_id, $replacement_text, $edit_type, $case_sensitive) {
        // Parse content into blocks
        $blocks = parse_blocks($content);
        $target_blocks_info = [];
        
        foreach ($blocks as $block_index => $block) {
            // Check if this block contains the search pattern
            if ($this->block_contains_pattern($block, $search_pattern, $case_sensitive)) {
                // Create diff wrapper block for this target
                $wrapped_diff_block = $this->create_diff_wrapper_block($block, $diff_id, $search_pattern, $replacement_text, $edit_type, $case_sensitive);
                
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
     * Legacy method - kept for backward compatibility but now unused
     * @deprecated Use find_and_wrap_target_blocks instead
     */
    private function wrap_target_blocks_with_diff($content, $search_pattern, $diff_id, $replacement_text, $edit_type, $case_sensitive) {
        $target_blocks_info = $this->find_and_wrap_target_blocks($content, $search_pattern, $diff_id, $replacement_text, $edit_type, $case_sensitive);
        
        if (empty($target_blocks_info)) {
            return '';
        }
        
        // Build complete content with diff blocks for backward compatibility
        $blocks = parse_blocks($content);
        $modified_content = '';
        
        foreach ($blocks as $block_index => $block) {
            $found_target = false;
            foreach ($target_blocks_info as $target_info) {
                if ($target_info['block_index'] === $block_index) {
                    $modified_content .= $target_info['diff_wrapper_block'] . "\n\n";
                    $found_target = true;
                    break;
                }
            }
            
            if (!$found_target) {
                $modified_content .= serialize_block($block) . "\n\n";
            }
        }
        
        return trim($modified_content);
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
    private function create_diff_wrapper_block($target_block, $diff_id, $search_pattern, $replacement_text, $edit_type, $case_sensitive) {
        // Create diff block attributes
        $diff_attributes = [
            'diffId' => $diff_id,
            'diffType' => 'edit',
            'originalContent' => $search_pattern,
            'replacementContent' => $replacement_text,
            'status' => 'pending',
            'toolCallId' => 'tool_call_' . uniqid(),
            'editType' => $edit_type,
            'searchPattern' => $search_pattern,
            'caseSensitive' => $case_sensitive,
            'isPreview' => true,
            'originalBlockContent' => serialize_block($target_block),
            'originalBlockType' => $target_block['blockName'] ?: 'core/paragraph'
        ];
        
        // Convert attributes to JSON for the block
        $attributes_json = json_encode($diff_attributes);
        
        // Get the serialized target block content
        $target_block_content = serialize_block($target_block);
        
        // Return the complete diff block with wrapped content
        return "<!-- wp:wordsurf/diff {$attributes_json} -->\n{$target_block_content}\n<!-- /wp:wordsurf/diff -->";
    }
} 