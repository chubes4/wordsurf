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
     * AI HTTP Client
     *
     * @var AI_HTTP_Client
     */
    private $ai_client;
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
    /**
     * Current response ID for continuation requests
     *
     * @var string|null
     */
    private $current_response_id = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->system_prompt   = new Wordsurf_System_Prompt();
        $this->context_manager = new Wordsurf_Context_Manager();
        $this->tool_manager    = new Wordsurf_Tool_Manager();
        
        // Register tools with AI HTTP Client library for proper "round plug" integration
        $this->tool_manager->register_tools_with_library();
        
        $this->ai_client = new AI_HTTP_Client();
        
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
        
        // Note: post_id parameter removed - tools get current post from WordPress context for MVP simplicity
        
        // Start the first turn. The underlying cURL call is blocking and will complete before moving on.
        $full_response = $this->start_chat_turn($messages);

        // Tool processing and result sending is now handled in start_chat_turn()
        // No additional processing needed here
    }

    /**
     * This is the main entry point for a streaming conversation turn.
     * It initiates the stream and returns the full response body for later processing.
     *
     * @param array $messages The full message history from the frontend.
     * @return string The full, raw response from the OpenAI API.
     */
    private function start_chat_turn($messages) {
        $this->message_history = $messages ?: [];
        $this->pending_tool_calls = []; // Clear any previous tool calls

        // Build and prepend the system prompt for the first turn.
        // Note: context manager will get current post from WordPress context for MVP simplicity
        $context = $this->context_manager->get_context();
        $available_tools = $this->tool_manager->get_tools();
        $prompt_content = $this->system_prompt->build_prompt($context, $available_tools);
        array_unshift($this->message_history, ['role' => 'system', 'content' => $prompt_content]);

        $tool_schemas = $this->tool_manager->get_tool_schemas();
        
        // Convert messages to Responses API format
        $sanitized_messages = array_map(function($message) {
            // Handle tool messages - convert to assistant messages since 'tool' role isn't supported
            if ($message['role'] === 'tool') {
                // Convert tool result to assistant message
                return [
                    'role' => 'assistant',
                    'content' => $message['content']
                ];
            }
            
            // For user messages
            if ($message['role'] === 'user') {
                return [
                    'role' => 'user',
                    'content' => $message['content']
                ];
            }
            
            // For system messages
            if ($message['role'] === 'system') {
                return [
                    'role' => 'system',
                    'content' => $message['content']
                ];
            }
            
            // For assistant messages - remove tool_calls as they're not supported in Responses API input
            if ($message['role'] === 'assistant') {
                // If this is a tool call message, we'll skip it since the Responses API
                // doesn't support tool_calls in the input format
                if (isset($message['tool_calls'])) {
                    // Skip tool call messages in input - they're handled differently in Responses API
                    return null;
                }
                
                return [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? ''
                ];
            }
            
            // Default fallback
            return [
                'role' => $message['role'],
                'content' => $message['content'] ?? ''
            ];
        }, $this->message_history);
        
        // Remove null entries (skipped tool call messages) and reindex
        $sanitized_messages = array_values(array_filter($sanitized_messages, function($message) {
            return $message !== null;
        }));
        
        // Get provider and model from AI HTTP Client (the proper way)
        $options_manager = new AI_HTTP_Options_Manager();
        $provider = $options_manager->get_selected_provider();
        $model = $options_manager->get_provider_setting($provider, 'model');

        $request = [
            'messages' => $sanitized_messages,
            'model' => $model,
            'tools' => $tool_schemas,
            'max_tokens' => 1000,
        ];

        // The client streams raw chunks directly and returns the full response.
        error_log('Wordsurf DEBUG: Making AI streaming request with provider: ' . $provider);
        
        // Stream the response with integrated tool processing
        try {
            $full_response = $this->ai_client->send_streaming_request($request, $provider, [$this, 'handle_stream_completion']);
            error_log('Wordsurf DEBUG: Streaming request completed successfully');
        } catch (Exception $e) {
            error_log('Wordsurf DEBUG: Streaming request failed: ' . $e->getMessage());
            throw $e;
        }
        
        // Capture the response ID for potential continuation
        $this->current_response_id = $this->ai_client->get_last_response_id();
        if ($this->current_response_id) {
            error_log('Wordsurf DEBUG: Stored response ID for continuation: ' . $this->current_response_id);
        }
        
        return $full_response;
    }
    
    /**
     * Continue conversation with tool results using Responses API continuation pattern
     *
     * @param array $tool_results Array of tool results from user interactions
     * @param string|null $response_id Optional specific response ID to use
     * @return string The full, raw response from the continuation API call
     */
    public function continue_with_tool_results($tool_results, $response_id = null) {
        $target_response_id = $response_id ?: $this->current_response_id;
        
        if (!$target_response_id) {
            error_log('Wordsurf DEBUG: No response ID available for continuation');
            return '';
        }
        
        error_log('Wordsurf DEBUG: Starting continuation with ' . count($tool_results) . ' tool results');
        
        // Get selected provider for continuation
        $provider = get_option('wordsurf_ai_provider', 'openai');
        
        // Make continuation request using AI HTTP Client
        $full_response = $this->ai_client->continue_with_tool_results(
            $target_response_id, 
            $tool_results,
            $provider,
            [$this, 'handle_stream_completion']
        );
        
        // Update response ID for potential further continuations
        $new_response_id = $this->ai_client->get_last_response_id();
        if ($new_response_id) {
            $this->current_response_id = $new_response_id;
            error_log('Wordsurf DEBUG: Updated response ID after continuation: ' . $this->current_response_id);
        }
        
        return $full_response;
    }
    
    /**
     * Get the current response ID
     *
     * @return string|null The current response ID or null if not available
     */
    public function get_current_response_id() {
        return $this->current_response_id;
    }
    
    /**
     * Handle stream completion - process tools and send results
     */
    public function handle_stream_completion($full_response) {
        error_log('Wordsurf DEBUG: Stream completed, processing tool calls');
        error_log('Wordsurf DEBUG: Connection info - headers_sent: ' . (headers_sent() ? 'yes' : 'no') . ', output_buffering_level: ' . ob_get_level());
        error_log('Wordsurf DEBUG: Full response length: ' . strlen($full_response) . ' bytes');
        error_log('Wordsurf DEBUG: Full response preview: ' . substr($full_response, 0, 500) . '...');
        
        // Capture response ID from AI client if not already set
        if (!$this->current_response_id) {
            $this->current_response_id = $this->ai_client->get_last_response_id();
            if ($this->current_response_id) {
                error_log('Wordsurf DEBUG: Captured response ID during completion: ' . $this->current_response_id);
            }
        }
        
        // Use AI HTTP Client library to extract tool calls (proper "round plug" architecture)
        $extraction_result = AI_HTTP_OpenAI_Streaming_Module::extract_tool_calls($full_response);
        $tool_calls = $extraction_result['tool_calls'] ?? [];
        
        error_log('Wordsurf DEBUG: AI HTTP Client extracted ' . count($tool_calls) . ' tool calls');
        
        // Execute tools using the ToolExecutor (which will call our registered filters)
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
                
                error_log("Wordsurf DEBUG: Executing tool '{$tool_name}' via AI HTTP Client library");
                
                // Use the library's ToolExecutor which will call our registered WordPress filter
                $result = AI_HTTP_Tool_Executor::execute_tool($tool_name, $arguments, $call_id);
                error_log("Wordsurf DEBUG (AgentCore): ToolExecutor returned for '{$tool_name}': " . json_encode($result));
                
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
                    'response_id' => $this->current_response_id
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
     * Checks if there are tool calls waiting for a follow-up API call.
     *
     * @return boolean
     */
    private function has_pending_tool_calls() {
        return !empty($this->pending_tool_calls);
    }
} 