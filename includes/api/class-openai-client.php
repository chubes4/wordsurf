<?php
/**
 * Wordsurf OpenAI API Client
 *
 * This class is a lightweight, pure HTTP client for the OpenAI API.
 * It is responsible only for making requests and returning raw response data.
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-api-base.php';

/**
 * OpenAI API Client Class.
 *
 * @class   Wordsurf_OpenAI_Client
 * @version 0.1.0
 * @since   0.1.0
 * @extends Wordsurf_API_Base
 */
class Wordsurf_OpenAI_Client extends Wordsurf_API_Base {

    /**
     * API Endpoint for the Responses API.
     *
     * @var string
     */
    protected $api_endpoint = 'https://api.openai.com/v1/responses';
    
    /**
     * The response ID from the current/last API call
     *
     * @var string|null
     */
    private $last_response_id = null;

    /**
     * Stream a request to the API using cURL.
     *
     * This method opens a connection, streams the raw response directly
     * to the output buffer, and simultaneously captures the entire response
     * to return it as a string.
     *
     * @param array $body The request body to send to OpenAI.
     * @return string The full, raw response from the API.
     */
    public function stream_request($body) {
        return $this->stream_request_with_tool_processing($body, null);
    }

    /**
     * Stream a request with integrated tool processing
     *
     * @param array $body The request body to send to OpenAI.
     * @param callable|null $completion_callback Called when stream completes
     * @return string The full, raw response from the API.
     */
    public function stream_request_with_tool_processing($body, $completion_callback = null) {
        // Reset response ID for new request
        $this->last_response_id = null;
        
        // Ensure the stream flag is set, as required by the Responses API.
        $body['stream'] = true;

        $full_response = '';
        $stream_completed = false;
        
        $ch = curl_init($this->api_endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key,
                'Accept: text/event-stream'
            ],
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$full_response, &$stream_completed, $completion_callback) {
                // Stream the raw data directly to the output buffer
                echo $data;
                
                // Flush immediately
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                
                // Append the data to our buffer to capture the full response.
                $full_response .= $data;
                
                // Extract response ID from this chunk
                $this->extract_response_id($data);
                
                // Check if this chunk contains stream completion
                if (strpos($data, 'event: response.completed') !== false && !$stream_completed) {
                    $stream_completed = true;
                    error_log('Wordsurf DEBUG: Stream completion detected, will process tools after this chunk');
                }
                
                // Return the number of bytes written.
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log('Wordsurf DEBUG: HTTP Status Code: ' . $http_code);
        error_log('Wordsurf DEBUG: Full response length: ' . strlen($full_response));

        if (curl_errno($ch)) {
            error_log('Wordsurf cURL Error: ' . curl_error($ch));
        }
        
        if ($http_code !== 200) {
            error_log('Wordsurf DEBUG: Non-200 response. HTTP Code: ' . $http_code);
            error_log('Wordsurf DEBUG: Response body: ' . $full_response);
        }
        
        curl_close($ch);

        // Process tools immediately after curl completes but before connection closes
        if ($completion_callback && is_callable($completion_callback)) {
            call_user_func($completion_callback, $full_response);
        }

        return $full_response;
    }
    
    /**
     * Get the response ID from the last API call
     *
     * @return string|null The response ID or null if not available
     */
    public function get_last_response_id() {
        return $this->last_response_id;
    }
    
    /**
     * Make a continuation request using previous_response_id
     *
     * @param string $previous_response_id The response ID to continue from
     * @param array $function_call_outputs Array of function call outputs
     * @param callable|null $completion_callback Called when stream completes
     * @return string The full, raw response from the API
     */
    public function stream_continuation_request($previous_response_id, $function_call_outputs, $completion_callback = null) {
        $body = [
            'model' => 'gpt-4.1',
            'previous_response_id' => $previous_response_id,
            'input' => $function_call_outputs,
            'stream' => true,
        ];
        
        error_log('Wordsurf DEBUG: Making continuation request with previous_response_id: ' . $previous_response_id);
        error_log('Wordsurf DEBUG: Function call outputs: ' . json_encode($function_call_outputs));
        
        return $this->stream_request_with_tool_processing($body, $completion_callback);
    }
    
    /**
     * Extract response ID from streaming response data
     *
     * @param string $data The chunk of streaming data
     */
    private function extract_response_id($data) {
        // Look for response ID pattern in streaming data
        // Pattern: "id":"resp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
        if (preg_match('/"id"\s*:\s*"(resp_[^"]+)"/', $data, $matches)) {
            if ($this->last_response_id === null) {
                $this->last_response_id = $matches[1];
                error_log('Wordsurf DEBUG: Captured response ID: ' . $this->last_response_id);
            }
        }
    }
} 