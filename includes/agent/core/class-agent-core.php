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

require_once __DIR__ . '/../../api/class-sse-parser.php';

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
     * OpenAI API client
     *
     * @var Wordsurf_OpenAI_Client
     */
    private $openai_client;
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
     * SSE Parser for handling streaming responses.
     *
     * @var Wordsurf_SSE_Parser
     */
    private $sse_parser;

    /**
     * Constructor
     */
    public function __construct() {
        $this->system_prompt   = new Wordsurf_System_Prompt();
        $this->context_manager = new Wordsurf_Context_Manager();
        $this->tool_manager    = new Wordsurf_Tool_Manager();
        
        $api_key = get_option('wordsurf_openai_api_key', '');
        $this->openai_client = new Wordsurf_OpenAI_Client($api_key);
        $this->sse_parser = new Wordsurf_SSE_Parser();
        
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
        $post_id  = $request_data['post_id'] ?? null;
        
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
     * @param int|null $post_id The ID of the current post.
     * @return string The full, raw response from the OpenAI API.
     */
    private function start_chat_turn($messages, $post_id = null) {
        $this->message_history = $messages ?: [];
        $this->pending_tool_calls = []; // Clear any previous tool calls

        // Build and prepend the system prompt for the first turn.
        $context = $this->context_manager->get_context($post_id);
        $tool_descriptions = $this->tool_manager->get_tool_descriptions();
        $prompt_content = $this->system_prompt->build_prompt($context, $tool_descriptions);
        array_unshift($this->message_history, ['role' => 'system', 'content' => $prompt_content]);

        $tool_schemas = $this->tool_manager->get_tool_schemas();
        
        // Convert messages to Responses API format
        $sanitized_messages = array_map(function($message) {
            // Handle tool messages differently - these stay in the same format
            if ($message['role'] === 'tool') {
                return [
                    'role' => 'tool',
                    'tool_call_id' => $message['tool_call_id'],
                    'content' => $message['content']
                ];
            }
            
            // For user messages
            if ($message['role'] === 'user') {
                return [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $message['content']
                        ]
                    ]
                ];
            }
            
            // For system messages
            if ($message['role'] === 'system') {
                return [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $message['content']
                        ]
                    ]
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
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $message['content'] ?? ''
                        ]
                    ]
                ];
            }
            
            // Default fallback
            return [
                'role' => $message['role'],
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => $message['content'] ?? ''
                    ]
                ]
            ];
        }, $this->message_history);
        
        // Remove null entries (skipped tool call messages) and reindex
        $sanitized_messages = array_values(array_filter($sanitized_messages, function($message) {
            return $message !== null;
        }));
        
        $body = [
            'model' => 'gpt-4.1',
            'input' => $sanitized_messages,
            'tools' => $tool_schemas,
            'stream' => true,
        ];

        // The client streams raw chunks directly and returns the full response.
        error_log('Wordsurf DEBUG: Making OpenAI streaming request...');
        
        // Stream the response with integrated tool processing
        $full_response = $this->openai_client->stream_request_with_tool_processing($body, [$this, 'handle_stream_completion']);
        
        return $full_response;
    }
    
    /**
     * Handle stream completion - process tools and send results
     */
    public function handle_stream_completion($full_response) {
        error_log('Wordsurf DEBUG: Stream completed, processing tool calls');
        
        // Process tool calls and prepare results
        $this->pending_tool_calls = $this->tool_manager->process_and_execute_tool_calls($full_response);
        
        // Send tool results immediately while connection is still open
        if ($this->has_pending_tool_calls()) {
            error_log('Wordsurf DEBUG: Tool calls detected, sending results during stream');
            foreach ($this->pending_tool_calls as $call) {
                $tool_result = [
                    'tool_call_id' => $call['tool_call_object']['call_id'],
                    'tool_name' => $call['tool_call_object']['name'],
                    'result' => $call['result']
                ];
                $this->send_tool_result_to_frontend($tool_result);
            }
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