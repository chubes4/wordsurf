<?php
/**
 * Render callback for the Wordsurf Diff block
 * 
 * @param array $attributes Block attributes
 * @param string $content Block content
 * @return string Rendered HTML
 */
function wordsurf_diff_block_render($attributes, $content) {
    $diff_id = isset($attributes['diffId']) ? sanitize_text_field($attributes['diffId']) : '';
    $diff_type = isset($attributes['diffType']) ? sanitize_text_field($attributes['diffType']) : 'edit';
    $original_content = isset($attributes['originalContent']) ? wp_kses_post($attributes['originalContent']) : '';
    $replacement_content = isset($attributes['replacementContent']) ? wp_kses_post($attributes['replacementContent']) : '';
    $status = isset($attributes['status']) ? sanitize_text_field($attributes['status']) : 'pending';
    $tool_call_id = isset($attributes['toolCallId']) ? sanitize_text_field($attributes['toolCallId']) : '';
    $search_pattern = isset($attributes['searchPattern']) ? sanitize_text_field($attributes['searchPattern']) : '';
    
    $block_classes = array(
        'wp-block-wordsurf-diff',
        'wordsurf-diff-block',
        'wordsurf-diff-' . esc_attr($diff_type),
        'wordsurf-diff-' . esc_attr($status)
    );
    
    $block_attrs = array(
        'data-diff-id' => esc_attr($diff_id),
        'data-tool-call-id' => esc_attr($tool_call_id),
    );
    
    $class_attr = implode(' ', $block_classes);
    $data_attrs = '';
    foreach ($block_attrs as $key => $value) {
        $data_attrs .= ' ' . $key . '="' . $value . '"';
    }
    
    // Revolutionary architecture: render inner block content with diff overlays
    $content_with_diff = $content; // Use actual inner block content
    
    if ($diff_type === 'edit' && !empty($original_content) && !empty($replacement_content)) {
        $search_text = !empty($search_pattern) ? $search_pattern : $original_content;
        
        if (strpos($content_with_diff, $search_text) !== false) {
            $diff_html = '<span class="wordsurf-diff-container" data-diff-id="' . esc_attr($diff_id) . '">' .
                       '<del class="wordsurf-diff-removed">' . esc_html($original_content) . '</del>' .
                       '<ins class="wordsurf-diff-added">' . esc_html($replacement_content) . '</ins>' .
                       '<span class="wordsurf-diff-controls">' .
                       '<button class="wordsurf-accept-btn" data-diff-id="' . esc_attr($diff_id) . '">✓</button>' .
                       '<button class="wordsurf-reject-btn" data-diff-id="' . esc_attr($diff_id) . '">✗</button>' .
                       '</span></span>';
            
            $content_with_diff = str_replace($search_text, $diff_html, $content_with_diff);
        }
    }
    
    return '<div class="' . esc_attr($class_attr) . '"' . $data_attrs . '>' . $content_with_diff . '</div>';
} 