<?php
/**
 * Wordsurf SSE Parser
 *
 * Handles Server-Sent Events (SSE) parsing and streaming to the frontend.
 * Responsible for parsing raw SSE data, cleaning JSON, and streaming events.
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SSE Parser Class.
 *
 * @class   Wordsurf_SSE_Parser
 * @version 0.1.0
 * @since   0.1.0
 */
class Wordsurf_SSE_Parser {

    /**
     * Parse raw SSE response and stream events to frontend
     *
     * @param string $raw_response The raw response from OpenAI
     * @param array|null $tool_results Optional tool results to send before completion
     */
    public function parse_and_stream($raw_response) {
        error_log("Wordsurf DEBUG: Starting SSE parsing. Raw response length: " . strlen($raw_response));
        
        // Use a more efficient approach: split by double newlines to get event blocks
        $event_blocks = explode("\n\n", trim($raw_response));
        error_log("Wordsurf DEBUG: Split into " . count($event_blocks) . " event blocks to process");
        
        foreach ($event_blocks as $block_number => $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            error_log("Wordsurf DEBUG: Processing event block " . ($block_number + 1) . " of " . count($event_blocks));
            
            $current_event = '';
            $current_data = '';
            $lines = explode("\n", $block);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                // Parse SSE format: "event: type" or "data: json"
                if (preg_match('/^event: (.+)$/', $line, $matches)) {
                    $current_event = trim($matches[1]);
                    error_log("Wordsurf DEBUG: Found event: '{$current_event}'");
                } elseif (preg_match('/^data: (.+)$/', $line, $matches)) {
                    // Handle multi-line data blocks by appending
                    $data_chunk = trim($matches[1]);
                    $current_data .= $data_chunk;
                }
            }
            
            // Send the event if we have complete data
            if (!empty($current_event) && !empty($current_data)) {
                error_log("Wordsurf DEBUG: Processing event '{$current_event}' in SSE parser");
                $this->send_event($current_event, $current_data);
            }
        }
        
        error_log("Wordsurf DEBUG: SSE parsing completed successfully");
    }

    /**
     * Send a single event to the frontend
     *
     * @param string $event_type The event type
     * @param string $event_data The event data
     * @param array|null $tool_results Optional tool results to send before completion
     */
    private function send_event($event_type, $event_data) {
        // Clean the data to remove control characters that might cause JSON decode issues
        $cleaned_data = preg_replace('/[\x00-\x1F\x7F]/', '', $event_data);
        
        // Log raw data for debugging
        if ($event_type === 'response.completed') {
            error_log("Wordsurf DEBUG: Raw response.completed data: " . substr($cleaned_data, 0, 500) . "...");
        }
        
        // Ensure the data is properly formatted JSON
        $decoded = json_decode($cleaned_data, true);
        if ($decoded !== null) {
            // Only log important events, not every delta
            if (!strpos($event_type, 'delta')) {
                error_log("Wordsurf DEBUG: Sending event '{$event_type}' to frontend");
            }
            echo "event: {$event_type}\n";
            echo "data: " . json_encode($decoded) . "\n\n";
            
            // Flush immediately
            $this->flush_output();
            
        } else {
            // Only log JSON errors for important events, not all events
            if (in_array($event_type, ['response.completed', 'response.created'])) {
                error_log("Wordsurf DEBUG: Failed to decode JSON for event '{$event_type}': " . substr($cleaned_data, 0, 200) . "... (truncated, total length: " . strlen($cleaned_data) . ")");
                error_log("Wordsurf DEBUG: JSON error: " . json_last_error_msg());
                
                // Try to send a basic completion event instead
                if ($event_type === 'response.completed') {
                    error_log("Wordsurf DEBUG: Sending fallback completion event");
                    echo "event: response.completed\n";
                    echo "data: " . json_encode(['type' => 'response.completed', 'status' => 'completed']) . "\n\n";
                    $this->flush_output();
                }
            }
        }
    }

    /**
     * Send tool results to the frontend
     *
     * @param array $tool_results Array of tool results to send
     */
    private function send_tool_results($tool_results) {
        error_log('Wordsurf DEBUG: Sending ' . count($tool_results) . ' tool results to frontend');
        foreach ($tool_results as $tool_result) {
            error_log('Wordsurf DEBUG: Sending tool_result event with data: ' . json_encode($tool_result));
            echo "event: tool_result\n";
            echo "data: " . json_encode($tool_result) . "\n\n";
            $this->flush_output();
        }
        error_log('Wordsurf DEBUG: Finished sending tool results');
    }

    /**
     * Validate large JSON payloads incrementally
     *
     * @param string $event_type The event type
     * @param string $data The JSON data
     */
    private function validate_large_json($event_type, $data) {
        $decoded = json_decode($data, true);
        if ($decoded !== null) {
            error_log("Wordsurf DEBUG: Successfully decoded large JSON for '{$event_type}' (length: " . strlen($data) . ")");
        } else {
            $json_error = json_last_error_msg();
            error_log("Wordsurf DEBUG: JSON decode error for '{$event_type}': " . $json_error);
        }
    }

    /**
     * Flush output buffer
     */
    private function flush_output() {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
} 