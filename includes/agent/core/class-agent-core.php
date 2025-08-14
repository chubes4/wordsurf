<?php
/**
 * Wordsurf Agent Core
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


class Wordsurf_Agent_Core {
    /**
     * System prompt manager
     *
     * @var Wordsurf_System_Prompt
     */
    private $system_prompt;
    /**
     * Context manager
     *
     * @var Wordsurf_Context_Manager
     */
    private $context_manager;
    /**
     * Tool manager
     *
     * @var Wordsurf_Tool_Manager
     */
    private $tool_manager;
    /**
     * Current provider name for AI requests
     *
     * @var string
     */
    private $current_provider = 'openai';
    /**
     * In-memory message history (per page load)
     *
     * @var array
     */
    private $message_history = [];
    /**
     * Stores tool calls from a single assistant response.
     *
     * @var array
     */
    private $pending_tool_calls = [];
    // Response ID tracking removed - now handled by AI HTTP Client library

    /**
     * Constructor
     */
    public function __construct() {
        $this->system_prompt   = new Wordsurf_System_Prompt();
        $this->context_manager = new Wordsurf_Context_Manager();
        $this->tool_manager    = new Wordsurf_Tool_Manager();
        
        // Tools are now registered globally at plugin initialization
        // No need to register per-instance
        
        // Get current provider from WordPress options (filter-based architecture)
        $this->current_provider = get_option('wordsurf_ai_provider', 'openai');
        
        $this->reset_message_history();
    }

    /**
     * Reset message history (for new chat session)
     */
    public function reset_message_history() {
        $this->message_history = [];
    }

    /**
     * Get current message history
     */
    public function get_message_history() {
        return $this->message_history;
    }

    /**
     * Append a message to history
     */
    public function append_message($message) {
        $this->message_history[] = $message;
    }

    /**
     * Handle a chat message from the user.
     *
     * @param string $user_message The user's message.
     * @param array $context Optional context (e.g., post info, conversation history).
     * @param bool $stream Whether to use streaming responses.
     * @return array|WP_Error The response from the API or a WP_Error on failure.
     */
    public function chat($user_message, $context = [], $stream = true) {
        // This method appears to be legacy and may need refactoring or removal.
        // For now, it's not part of the main streaming flow.
        return new WP_Error('not_implemented', 'This chat method is not implemented for the new architecture.');
    }

    /**
     * Handle incoming chat request (main public entry point)
     *
     * @param array $request_data The request data (messages, post_id, etc.)
     */
    public function handle_chat_request($request_data) {
        $messages = $request_data['messages'] ?? [];
        $post_id = $request_data['post_id'] ?? null;
        
        // Note: post_id is sent for context setup since get_the_ID() doesn't work in AJAX context
        
        // Start the first turn. The underlying cURL call is blocking and will complete before moving on.
        $full_response = $this->start_chat_turn($messages, $post_id);

        // Tool processing and result sending is now handled in start_chat_turn()
        // No additional processing needed here
    }

    /**
     * This is the main entry point for a streaming conversation turn.
     * It initiates the stream and returns the full response body for later processing.
     *
     * @param array $messages The full message history from the frontend.
     * @param int|null $post_id The ID of the current post (for context setup in AJAX)
     * @return string The full, raw response from the OpenAI API.
     */
    private function start_chat_turn($messages, $post_id = null) {
        $this->message_history = $messages ?: [];
        $this->pending_tool_calls = []; // Clear any previous tool calls

        // Set current post ID in helper for AJAX context
        if ($post_id) {
            Wordsurf_Post_Context_Helper::set_current_post_id($post_id);
        }

        // Build system prompt with context and available tools
        // Note: post_id is sent for context setup since get_the_ID() doesn't work in AJAX context
        $context = $this->context_manager->get_context($post_id);
        
        // Load all Wordsurf tools on-demand for this request
        $supported_tools = ['read_post', 'edit_post', 'insert_content', 'write_to_post'];
        foreach ($supported_tools as $tool_name) {
            $this->tool_manager->load_tool($tool_name);
        }
        
        $available_tools = $this->tool_manager->get_tools();
        $system_prompt = $this->system_prompt->build_prompt($context, $available_tools);

        $tool_schemas = $this->tool_manager->get_tool_schemas();

        // Build messages array with system prompt
        $messages = [];
        if (!empty($system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        // Add conversation history
        $messages = array_merge($messages, $this->message_history);

        // Create standardized request for filter-based architecture
        $request = [
            'messages' => $messages,
            'tools' => $tool_schemas,
            'model' => get_option('wordsurf_ai_model', ''), // Let provider choose if empty
        ];

        // Use filter-based AI request with streaming
        error_log('Wordsurf DEBUG: Making AI streaming request via filter system');
        
        try {
            $response = apply_filters('ai_request', $request, $this->current_provider, [$this, 'handle_stream_completion']);
            
            if (!$response['success']) {
                error_log('Wordsurf DEBUG: AI request failed: ' . $response['error']);
                throw new Exception('AI request failed: ' . $response['error']);
            }
            
            $full_response = $response['data'];
            error_log('Wordsurf DEBUG: Streaming request completed successfully');
        } catch (Exception $e) {
            error_log('Wordsurf DEBUG: Streaming request failed: ' . $e->getMessage());
            throw $e;
        }
        
        return $full_response;
    }
    
    
    
    /**
     * Handle stream completion - process tools and send results
     */
    public function handle_stream_completion($full_response) {
        error_log('Wordsurf DEBUG: Stream completed, processing tool calls');
        error_log('Wordsurf DEBUG: Connection info - headers_sent: ' . (headers_sent() ? 'yes' : 'no') . ', output_buffering_level: ' . ob_get_level());
        error_log('Wordsurf DEBUG: Full response length: ' . strlen($full_response) . ' bytes');
        error_log('Wordsurf DEBUG: Full response preview: ' . substr($full_response, 0, 500) . '...');
        
        // Extract tool calls from response (simple pattern matching for now)
        $tool_calls = $this->extract_tool_calls_from_response($full_response);
        
        error_log('Wordsurf DEBUG: Extracted ' . count($tool_calls) . ' tool calls from response');
        
        // Execute tools using the new filter-based system
        if (!empty($tool_calls)) {
            $this->pending_tool_calls = [];
            
            foreach ($tool_calls as $tool_call) {
                $tool_name = $tool_call['function']['name'] ?? '';
                $arguments = $tool_call['function']['arguments'] ?? '{}';
                $call_id = $tool_call['id'] ?? uniqid('tool_');
                
                // Parse arguments if they're JSON string
                if (is_string($arguments)) {
                    $arguments = json_decode($arguments, true) ?: [];
                }
                
                error_log("Wordsurf DEBUG: Executing tool '{$tool_name}' via filter-based system");
                
                // Use the new filter-based tool execution
                $result = ai_http_execute_tool($tool_name, $arguments);
                error_log("Wordsurf DEBUG (AgentCore): Tool execution returned for '{$tool_name}': " . json_encode($result));
                
                // Store in the same format for backward compatibility
                $this->pending_tool_calls[] = [
                    'tool_call_object' => [
                        'call_id' => $call_id,
                        'name' => $tool_name,
                        'arguments' => is_string($arguments) ? $arguments : json_encode($arguments)
                    ],
                    'result' => $result,
                ];
            }
            
            // Send tool results immediately while connection is still open
            error_log('Wordsurf DEBUG: Tool calls processed, sending results during stream. Found ' . count($this->pending_tool_calls) . ' tool calls.');
            foreach ($this->pending_tool_calls as $call) {
                $tool_result = [
                    'tool_call_id' => $call['tool_call_object']['call_id'],
                    'tool_name' => $call['tool_call_object']['name'],
                    'result' => $call['result'],
                    'response_id' => null // Response ID no longer needed - managed by library
                ];
                error_log('Wordsurf DEBUG: About to send tool_result event: ' . json_encode($tool_result));
                $this->send_tool_result_to_frontend($tool_result);
                error_log('Wordsurf DEBUG: Tool_result event sent successfully');
            }
        } else {
            error_log('Wordsurf DEBUG: No tool calls found to process');
        }
        
        error_log('Wordsurf DEBUG: Tool result processing completed');
    }

    /**
     * Send a single tool result directly to frontend
     */
    private function send_tool_result_to_frontend($tool_result) {
        error_log('Wordsurf DEBUG: Sending tool_result event: ' . json_encode($tool_result));
        echo "event: tool_result\n";
        echo "data: " . json_encode($tool_result) . "\n\n";
        flush();
        if (ob_get_level()) {
            ob_flush();
        }
    }

    /**
     * Continue conversation with tool results using filter-based architecture
     *
     * @param array $tool_results Array of tool results from user interactions
     * @return string The full response from the continuation
     */
    public function continue_with_tool_results($tool_results) {
        error_log('Wordsurf DEBUG: Starting tool result continuation via filter system');
        
        try {
            // Build tool result messages for continuation
            $tool_result_messages = [];
            foreach ($tool_results as $result) {
                $tool_result_messages[] = [
                    'role' => 'tool',
                    'name' => $result['tool_name'],
                    'content' => json_encode($result['result']),
                    'tool_call_id' => $result['tool_call_id']
                ];
            }
            
            // Add tool results to message history for continuation
            $messages = array_merge($this->message_history, $tool_result_messages);
            
            // Create continuation request
            $request = [
                'messages' => $messages,
                'tools' => $this->tool_manager->get_tool_schemas(),
                'model' => get_option('wordsurf_ai_model', ''),
            ];
            
            // Send continuation request via filters
            $response = apply_filters('ai_request', $request, $this->current_provider, [$this, 'handle_stream_completion']);
            
            if (!$response['success']) {
                error_log('Wordsurf DEBUG: Tool result continuation failed: ' . $response['error']);
                throw new Exception('Tool result continuation failed: ' . $response['error']);
            }
            
            error_log('Wordsurf DEBUG: Tool result continuation completed successfully');
            return $response['data'];
            
        } catch (Exception $e) {
            error_log('Wordsurf DEBUG: Tool result continuation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if continuation is possible
     *
     * @return bool True if continuation is possible
     */
    public function can_continue() {
        // In filter-based architecture, continuation is always possible
        // as long as we have conversation history
        return !empty($this->message_history);
    }

    /**
     * Checks if there are tool calls waiting for a follow-up API call.
     *
     * @return boolean
     */
    private function has_pending_tool_calls() {
        return !empty($this->pending_tool_calls);
    }

    /**
     * Extract tool calls from AI response
     * 
     * Simple pattern matching for OpenAI and Anthropic tool call formats
     *
     * @param string $response The full AI response
     * @return array Array of tool calls
     */
    private function extract_tool_calls_from_response($response) {
        $tool_calls = [];
        
        // Try to parse as JSON first (streaming responses might be JSON)
        $json_response = json_decode($response, true);
        if (is_array($json_response)) {
            // Handle OpenAI format
            if (isset($json_response['choices'][0]['message']['tool_calls'])) {
                return $json_response['choices'][0]['message']['tool_calls'];
            }
            
            // Handle Anthropic format
            if (isset($json_response['content'])) {
                foreach ($json_response['content'] as $content_block) {
                    if (isset($content_block['type']) && $content_block['type'] === 'tool_use') {
                        $tool_calls[] = [
                            'id' => $content_block['id'],
                            'function' => [
                                'name' => $content_block['name'],
                                'arguments' => json_encode($content_block['input'])
                            ]
                        ];
                    }
                }
            }
        } else {
            // Handle streaming response format - look for tool call patterns
            // This is a simplified extraction for basic tool calls
            if (preg_match_all('/\{"type":\s*"tool_use"[^}]*\}/', $response, $matches)) {
                foreach ($matches[0] as $match) {
                    $tool_data = json_decode($match, true);
                    if ($tool_data && isset($tool_data['name'])) {
                        $tool_calls[] = [
                            'id' => $tool_data['id'] ?? uniqid('tool_'),
                            'function' => [
                                'name' => $tool_data['name'],
                                'arguments' => json_encode($tool_data['input'] ?? [])
                            ]
                        ];
                    }
                }
            }
        }
        
        return $tool_calls;
    }
} 