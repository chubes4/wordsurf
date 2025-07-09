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

require_once __DIR__ . '/class-response-stream-parser.php';

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
     * Stream parser
     *
     * @var Wordsurf_Response_Stream_Parser
     */
    private $stream_parser;
    /**
     * In-memory message history (per page load)
     *
     * @var array
     */
    private $message_history = [];
    /**
     * Function to call for streaming output to the client.
     *
     * @var callable|null
     */
    private $stream_output_callback = null;
    /**
     * Stores tool calls from a single assistant response.
     *
     * @var array
     */
    private $pending_tool_calls = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->system_prompt   = new Wordsurf_System_Prompt();
        $this->context_manager = new Wordsurf_Context_Manager();
        $this->tool_manager    = new Wordsurf_Tool_Manager();
        $this->stream_parser   = new Wordsurf_Response_Stream_Parser();
        
        $api_key = get_option('wordsurf_openai_api_key', '');
        $this->openai_client = new Wordsurf_OpenAI_Client($api_key);
        
        $this->reset_message_history();
        $this->setup_parser_callbacks();
    }

    /**
     * Set up the callbacks for the stream parser.
     */
    private function setup_parser_callbacks() {
        $this->stream_parser->on('onToolStart', function($tool_name, $tool_call_id) {
            $this->send_sse_event('system', ['content' => "Thinking... Using tool: `{$tool_name}`..."]);
        });

        $this->stream_parser->on('onToolEnd', function($tool_call_object) {
            $tool_name = $tool_call_object['name'];
            $tool_call_id = $tool_call_object['call_id'];
            $arguments = json_decode($tool_call_object['arguments'], true);

            // Execute the tool
            error_log("Wordsurf DEBUG: Executing tool '{$tool_name}' with ID '{$tool_call_id}'.");
            $result = $this->tool_manager->execute_tool($tool_name, $arguments);
            error_log("Wordsurf DEBUG: Tool '{$tool_name}' executed. Result: " . json_encode($result));

            $this->send_sse_event('tool_end', [
                'name' => $tool_name,
                'id' => $tool_call_id,
                'result' => $result
            ]);

            // Store the original tool call and its result for the follow-up API call.
            $this->pending_tool_calls[] = [
                'tool_call_object' => $tool_call_object,
                'result' => $result,
            ];
            error_log("Wordsurf DEBUG: Added tool result to pending calls. Count: " . count($this->pending_tool_calls));
        });

        $this->stream_parser->on('onTextDelta', function($text_delta) {
            $this->send_sse_event('text', ['content' => $text_delta]);
        });
    }

    /**
     * Sends a Server-Sent Event (SSE) to the client.
     *
     * @param string $type The event type (e.g., 'text', 'tool_start').
     * @param array $data The data payload for the event.
     */
    private function send_sse_event($type, $data) {
        error_log("Wordsurf DEBUG: Sending SSE event of type '{$type}'");
        $event_data = json_encode(['type' => $type, 'data' => $data]);
        $sse_string = "data: {$event_data}\n\n";
        
        if (is_callable($this->stream_output_callback)) {
            call_user_func($this->stream_output_callback, $sse_string);
        }
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
     * @param callable|null $stream_callback Optional callback for streaming output
     */
    public function handle_chat_request($request_data, $stream_callback = null) {
        $this->stream_output_callback = $stream_callback;
        
        $messages = $request_data['messages'] ?? [];
        $post_id  = $request_data['post_id'] ?? null;
        
        // Start the first turn. The underlying cURL call is blocking and will complete before moving on.
        $this->start_chat_turn($messages, $post_id);

        // After the first stream is complete, check if we need to make a follow-up call.
        if ($this->has_pending_tool_calls()) {
            $this->make_follow_up_call();
        }
    }

    /**
     * This is the main entry point for a streaming conversation turn.
     *
     * @param array $messages The full message history from the frontend
     * @param array $context Optional context (e.g., post info, conversation history)
     * @return void
     */
    private function start_chat_turn($messages, $post_id = null) {
        $this->message_history = $messages ?: [];
        $this->pending_tool_calls = []; // Clear any previous tool calls
        $this->stream_parser->reset();

        // Build and prepend the system prompt for the first turn.
        $context = $this->context_manager->get_context($post_id);
        $tool_descriptions = $this->tool_manager->get_tool_descriptions();
        $prompt_content = $this->system_prompt->build_prompt($context, $tool_descriptions);
        array_unshift($this->message_history, ['role' => 'system', 'content' => $prompt_content]);

        $tool_schemas = $this->tool_manager->get_tool_schemas();
        $body = [
            'model' => 'gpt-4.1',
            'input' => $this->message_history,
            'tools' => $tool_schemas,
        ];

        // The client will stream raw chunks to our parser.
        $this->openai_client->stream_request($body, [$this->stream_parser, 'parse']);
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
     * After the initial API call, if tools were used, this method is called
     * to send the tool results back to the model for a final text response.
     */
    private function make_follow_up_call() {
        // Per the Responses API docs, we append the tool call and its output to the history.
        // These are role-less objects.

        foreach ($this->pending_tool_calls as $pending_call) {
            // 1. Append the original function_call object from the model's response.
            $this->message_history[] = $pending_call['tool_call_object'];

            // 2. Append the result of that function call.
            $this->message_history[] = [
                'type' => 'function_call_output',
                'call_id' => $pending_call['tool_call_object']['call_id'],
                'output' => json_encode($pending_call['result'])
            ];
        }

        // 3. Reset state for the next turn.
        $this->pending_tool_calls = [];
        $this->stream_parser->reset();
        
        $tool_schemas = $this->tool_manager->get_tool_schemas();
        
        // The new `input` array for the follow-up call contains the full history.
        $body = [
            'model'  => 'gpt-4.1',
            'input'  => $this->message_history,
            'tools'  => $tool_schemas,
            'stream' => true,
        ];
        
        error_log('Wordsurf Follow-up OpenAI Streaming Request: ' . json_encode($body, JSON_UNESCAPED_SLASHES));

        // The client will stream raw chunks to our parser.
        $this->openai_client->stream_request($body, [$this->stream_parser, 'parse']);
    }

    /**
     * Execute a tool
     *
     * @param string $tool_name
     * @param array $parameters
     * @return array
     */
    public function execute_tool($tool_name, $parameters = []) {
        return $this->tool_manager->execute_tool($tool_name, $parameters);
    }

    /**
     * Append assistant tool call and tool result to message history
     */
    public function append_tool_call_and_result($function_call, $result) {
        // Append assistant's tool call message
        $this->append_message([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => $function_call['id'],
                'type' => 'function',
                'function' => [
                    'name' => $function_call['name'],
                    'arguments' => $function_call['arguments']
                ]
            ]]
        ]);
        // Append tool result message
        $this->append_message([
            'role' => 'tool',
            'tool_call_id' => $function_call['id'],
            'content' => json_encode($result)
        ]);
    }
} 