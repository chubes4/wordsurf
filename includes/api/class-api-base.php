<?php
/**
 * Wordsurf API Base Class
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract API Base Class.
 *
 * Provides a base for all API integrations, ensuring a consistent interface.
 *
 * @class   Wordsurf_API_Base
 * @version 0.1.0
 * @since   0.1.0
 */
abstract class Wordsurf_API_Base {

    /**
     * API Key.
     *
     * @var string
     */
    protected $api_key;

    /**
     * API Endpoint.
     *
     * @var string
     */
    protected $api_endpoint;

    /**
     * Constructor.
     *
     * @param string $api_key API Key.
     */
    public function __construct( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * Send a request to the API.
     *
     * @param array $body The request body to send to OpenAI.
     * @param bool $stream Whether to use streaming responses.
     * @return array|WP_Error The response from the API or a WP_Error on failure.
     */
    public function send_request( $body, $stream = false ) {
        return new WP_Error(
            'deprecated_method',
            __('The send_request method is deprecated and should not be used in streaming architecture.', 'wordsurf'),
            ['status' => 501]
        );
    }
} 