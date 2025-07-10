<?php
/**
 * Wordsurf OpenAI API Client
 *
 * This class is a lightweight, pure HTTP client for the OpenAI API.
 * It is responsible only for making requests and streaming back the raw,
 * uninterpreted response data.
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
     * Stream a request to the API using cURL.
     *
     * This method opens a connection and streams the raw response back
     * to a provided callback function. It does not interpret the data.
     *
     * @param array    $body The request body to send to OpenAI.
     * @param callable $stream_chunk_callback A function to call for each chunk of data received.
     */
    public function stream_request( $body, $stream_chunk_callback ) {
        // Ensure the stream flag is set, as required by the Responses API.
        $body['stream'] = true;

        error_log('Wordsurf OpenAI Streaming Request: ' . json_encode($body));

        $ch = curl_init($this->api_endpoint);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key,
                'Accept: text/event-stream'
            ],
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_WRITEFUNCTION  => function($ch, $data) use ($stream_chunk_callback) {
                if (is_callable($stream_chunk_callback)) {
                    call_user_func($stream_chunk_callback, $data);
                }
                return strlen($data);
            },
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log(message: 'Wordsurf DEBUG: HTTP Status Code: ' . $http_code);

        if (curl_errno($ch)) {
            error_log('Wordsurf cURL Error: ' . curl_error($ch));
        }
        
        if ($http_code !== 200) {
            error_log('Wordsurf DEBUG: Non-200 response. HTTP Code: ' . $http_code);
        }
        
        curl_close($ch);
    }
} 