<?php
/**
 * AI HTTP Client - Simplified OpenRouter Provider
 * 
 * Single Responsibility: Pure OpenRouter API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Provider {

    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    private $http_referer;
    private $app_title;
    private $timeout = 30;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        $this->http_referer = isset($config['http_referer']) ? $config['http_referer'] : '';
        $this->app_title = isset($config['app_title']) ? $config['app_title'] : 'AI HTTP Client';
        $this->timeout = isset($config['timeout']) ? intval($config['timeout']) : 30;
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        }
    }

    /**
     * Send raw request to OpenRouter API
     *
     * @param array $provider_request Already normalized for OpenRouter
     * @return array Raw OpenRouter response
     * @throws Exception If request fails
     */
    public function send_raw_request($provider_request) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured - missing API key');
        }

        $url = $this->base_url . '/chat/completions';
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => wp_json_encode($provider_request),
            'timeout' => $this->timeout,
            'method' => 'POST'
        ));

        if (is_wp_error($response)) {
            throw new Exception('OpenRouter API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = 'OpenRouter API error (HTTP ' . $status_code . ')';
            if (isset($decoded_response['error']['message'])) {
                $error_message .= ': ' . $decoded_response['error']['message'];
            }
            throw new Exception($error_message);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenRouter API');
        }

        return $decoded_response;
    }

    /**
     * Send raw streaming request to OpenRouter API
     *
     * @param array $provider_request Already normalized for OpenRouter
     * @param callable $callback Optional callback for each chunk
     * @return string Full response content
     * @throws Exception If request fails
     */
    public function send_raw_streaming_request($provider_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('OpenRouter provider not configured - missing API key');
        }

        error_log('AI HTTP Client DEBUG: OpenRouter streaming request via WordPress SSE endpoint');

        // Use WordPress SSE endpoint instead of direct CURL
        $sse_url = rest_url('ai-http-client/v1/stream');
        
        // Prepare configuration for SSE handler
        $config = array(
            'api_key' => $this->api_key,
            'base_url' => $this->base_url,
            'http_referer' => $this->http_referer,
            'app_title' => $this->app_title,
            'timeout' => $this->timeout
        );

        $response = wp_remote_post($sse_url, array(
            'headers' => array(
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'provider' => 'openrouter',
                'request' => $provider_request,
                'config' => $config
            )),
            'timeout' => $this->timeout,
            'blocking' => false // Non-blocking for SSE
        ));

        if (is_wp_error($response)) {
            throw new Exception('WordPress SSE request failed: ' . $response->get_error_message());
        }

        return '';
    }

    /**
     * Get available models from OpenRouter API
     *
     * @return array Raw models response
     * @throws Exception If request fails
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return array();
        }

        $url = $this->base_url . '/models';
        $headers = $this->get_auth_headers();

        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => $this->timeout
        ));

        if (is_wp_error($response)) {
            throw new Exception('OpenRouter models request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);

        if ($status_code !== 200) {
            throw new Exception('OpenRouter models request failed with HTTP ' . $status_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenRouter models API');
        }

        return $decoded_response;
    }

    /**
     * Check if provider is configured
     *
     * @return bool True if configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Get authentication headers for OpenRouter API
     *
     * @return array Headers array
     */
    public function get_auth_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key
        );

        if (!empty($this->http_referer)) {
            $headers['HTTP-Referer'] = $this->http_referer;
        }

        if (!empty($this->app_title)) {
            $headers['X-Title'] = $this->app_title;
        }

        return $headers;
    }

    /**
     * Get streaming URL for OpenRouter API
     *
     * @return string Streaming URL
     */
    public function get_streaming_url() {
        return $this->base_url . '/chat/completions';
    }

    /**
     * Format headers for cURL
     *
     * @param array $headers Associative headers array
     * @return array Indexed headers array for cURL
     */
    private function format_curl_headers($headers) {
        $formatted = array();
        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }
        return $formatted;
    }
}