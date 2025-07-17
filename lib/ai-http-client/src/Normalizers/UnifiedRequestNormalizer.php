<?php
/**
 * AI HTTP Client - Unified Request Normalizer
 * 
 * Single Responsibility: Convert standard format to ANY provider format
 * This is the sole point of request normalization for all providers
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Request_Normalizer {

    /**
     * Normalize request for any provider
     *
     * @param array $standard_request Standardized request format
     * @param string $provider_name Target provider (openai, anthropic, gemini, etc.)
     * @return array Provider-specific formatted request
     * @throws Exception If provider not supported
     */
    public function normalize($standard_request, $provider_name) {
        // Validate standard input first
        $this->validate_standard_request($standard_request);
        
        // Route to provider-specific normalization
        switch (strtolower($provider_name)) {
            case 'openai':
                return $this->normalize_for_openai($standard_request);
            
            case 'anthropic':
                return $this->normalize_for_anthropic($standard_request);
            
            case 'gemini':
                return $this->normalize_for_gemini($standard_request);
            
            case 'grok':
                return $this->normalize_for_grok($standard_request);
            
            case 'openrouter':
                return $this->normalize_for_openrouter($standard_request);
            
            default:
                throw new Exception("Unsupported provider: {$provider_name}");
        }
    }

    /**
     * Validate standard request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
     */
    private function validate_standard_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }
    }

    /**
     * Normalize for OpenAI API (Responses API format)
     *
     * @param array $standard_request Standard format
     * @return array OpenAI Responses API format
     */
    private function normalize_for_openai($standard_request) {
        $request = $this->sanitize_common_fields($standard_request);
        
        // Convert messages to input for Responses API
        if (isset($request['messages'])) {
            $request['input'] = $request['messages'];
            unset($request['messages']);
        }

        // Convert max_tokens to max_output_tokens for Responses API
        if (isset($request['max_tokens'])) {
            $request['max_output_tokens'] = max(16, intval($request['max_tokens']));
            unset($request['max_tokens']);
        }

        // Handle multi-modal content in input
        if (isset($request['input'])) {
            $request['input'] = $this->normalize_openai_messages($request['input']);
        }

        // Handle tools
        if (isset($request['tools'])) {
            $request['tools'] = $this->normalize_openai_tools($request['tools']);
        }

        // Constrain parameters
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(2, floatval($request['temperature'])));
        }

        return $request;
    }

    /**
     * Normalize for Anthropic API
     *
     * @param array $standard_request Standard format
     * @return array Anthropic API format
     */
    private function normalize_for_anthropic($standard_request) {
        $request = $this->sanitize_common_fields($standard_request);
        
        // Anthropic uses standard messages format, just constrain parameters
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        // Handle system message extraction for Anthropic
        if (isset($request['messages'])) {
            $request = $this->extract_anthropic_system_message($request);
        }

        return $request;
    }

    /**
     * Normalize for Google Gemini API
     *
     * @param array $standard_request Standard format
     * @return array Gemini API format
     */
    private function normalize_for_gemini($standard_request) {
        $request = $this->sanitize_common_fields($standard_request);
        
        // Convert messages to Gemini contents format
        if (isset($request['messages'])) {
            $request['contents'] = $this->convert_to_gemini_contents($request['messages']);
            unset($request['messages']);
        }

        // Gemini uses maxOutputTokens
        if (isset($request['max_tokens'])) {
            $request['generationConfig']['maxOutputTokens'] = max(1, intval($request['max_tokens']));
            unset($request['max_tokens']);
        }

        // Gemini temperature in generationConfig
        if (isset($request['temperature'])) {
            $request['generationConfig']['temperature'] = max(0, min(2, floatval($request['temperature'])));
            unset($request['temperature']);
        }

        return $request;
    }

    /**
     * Normalize for Grok/X.AI API
     *
     * @param array $standard_request Standard format
     * @return array Grok API format
     */
    private function normalize_for_grok($standard_request) {
        $request = $this->sanitize_common_fields($standard_request);
        
        // Grok uses OpenAI-compatible format, just add reasoning_effort if supported
        if (isset($request['reasoning_effort'])) {
            $request['reasoning_effort'] = sanitize_text_field($request['reasoning_effort']);
        }

        // Standard OpenAI-style constraints
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(2, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        return $request;
    }

    /**
     * Normalize for OpenRouter API
     *
     * @param array $standard_request Standard format
     * @return array OpenRouter API format
     */
    private function normalize_for_openrouter($standard_request) {
        $request = $this->sanitize_common_fields($standard_request);
        
        // OpenRouter uses OpenAI-compatible format
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(2, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        return $request;
    }

    /**
     * Sanitize common fields across all providers
     *
     * @param array $request Request to sanitize
     * @return array Sanitized request
     */
    private function sanitize_common_fields($request) {
        // Sanitize messages
        if (isset($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
                if (isset($message['content']) && is_string($message['content'])) {
                    $message['content'] = sanitize_textarea_field($message['content']);
                }
            }
        }

        // Sanitize other common fields
        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        return $request;
    }

    /**
     * Normalize OpenAI messages for multi-modal support
     *
     * @param array $messages Array of messages
     * @return array OpenAI-formatted messages
     */
    private function normalize_openai_messages($messages) {
        $normalized = array();

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $normalized[] = $message;
                continue;
            }

            $normalized_message = array('role' => $message['role']);

            // Handle multi-modal content
            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files'])) {
                $normalized_message['content'] = $this->build_openai_multimodal_content($message);
            } else {
                $normalized_message['content'] = $message['content'];
            }

            // Preserve other fields (tool_calls, etc.)
            foreach ($message as $key => $value) {
                if (!in_array($key, array('role', 'content', 'images', 'image_urls', 'files'))) {
                    $normalized_message[$key] = $value;
                }
            }

            $normalized[] = $normalized_message;
        }

        return $normalized;
    }

    /**
     * Build OpenAI multi-modal content
     *
     * @param array $message Message with multi-modal content
     * @return array OpenAI multi-modal content format
     */
    private function build_openai_multimodal_content($message) {
        $content = array();

        // Add text content
        if (!empty($message['content'])) {
            $content[] = array(
                'type' => 'text',
                'text' => $message['content']
            );
        }

        // Handle image URLs
        if (isset($message['image_urls']) && is_array($message['image_urls'])) {
            foreach ($message['image_urls'] as $image_url) {
                $content[] = array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => $image_url,
                        'detail' => 'auto'
                    )
                );
            }
        }

        return $content;
    }

    /**
     * Normalize OpenAI tools
     *
     * @param array $tools Array of tools
     * @return array OpenAI-formatted tools
     */
    private function normalize_openai_tools($tools) {
        $normalized = array();

        foreach ($tools as $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                $normalized[] = $tool; // Already in OpenAI format
            } elseif (isset($tool['name']) && isset($tool['description'])) {
                $normalized[] = array(
                    'type' => 'function',
                    'function' => array(
                        'name' => sanitize_text_field($tool['name']),
                        'description' => sanitize_textarea_field($tool['description']),
                        'parameters' => $tool['parameters'] ?? array()
                    )
                );
            }
        }

        return $normalized;
    }

    /**
     * Extract system message for Anthropic
     *
     * @param array $request Request with messages
     * @return array Request with system extracted
     */
    private function extract_anthropic_system_message($request) {
        $messages = $request['messages'];
        $system_content = '';
        $filtered_messages = array();

        foreach ($messages as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $system_content .= $message['content'] . "\n";
            } else {
                $filtered_messages[] = $message;
            }
        }

        $request['messages'] = $filtered_messages;
        
        if (!empty(trim($system_content))) {
            $request['system'] = trim($system_content);
        }

        return $request;
    }

    /**
     * Convert messages to Gemini contents format
     *
     * @param array $messages Standard messages
     * @return array Gemini contents format
     */
    private function convert_to_gemini_contents($messages) {
        $contents = array();

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            // Map roles
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            
            // Skip system messages for now (Gemini handles differently)
            if ($message['role'] === 'system') {
                continue;
            }

            $contents[] = array(
                'role' => $role,
                'parts' => array(
                    array('text' => $message['content'])
                )
            );
        }

        return $contents;
    }
}