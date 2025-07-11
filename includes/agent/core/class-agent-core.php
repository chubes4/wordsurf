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
     * Constructor
     */
    public function __construct() {
        $this->system_prompt   = new Wordsurf_System_Prompt();
        $this->context_manager = new Wordsurf_Context_Manager();
        $this->tool_manager    = new Wordsurf_Tool_Manager();
        
        $api_key = get_option('wordsurf_openai_api_key', '');
        $this->openai_client = new Wordsurf_OpenAI_Client($api_key);
        
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
        
        // After the stream is complete, delegate processing to the Tool Manager.
        $this->pending_tool_calls = $this->tool_manager->process_and_execute_tool_calls($full_response);

        // After processing, check if we need to make a follow-up call.
        if ($this->has_pending_tool_calls() && !$this->has_preview_tools()) {
            error_log('Wordsurf DEBUG: Making follow-up call for tool results...');
            $this->make_follow_up_call();
        } else if ($this->has_preview_tools()) {
            error_log('Wordsurf DEBUG: Preview tools detected, skipping auto follow-up. User decision required first.');
        } else {
            error_log('Wordsurf DEBUG: No pending tool calls, skipping follow-up.');
        }
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
        $body = [
            'model' => 'gpt-4.1',
            'input' => $this->message_history,
            'tools' => $tool_schemas,
        ];

        // The client streams raw chunks directly and returns the full response.
        return $this->openai_client->stream_request($body);
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
     * Checks if there are preview tools that require user decisions
     *
     * @return boolean
     */
    private function has_preview_tools() {
        foreach ($this->pending_tool_calls as $pending_call) {
            $result = $pending_call['result'];
            $tool_name = $pending_call['tool_call_object']['name'];
            
            // Check if this is a preview tool (tools that require user acceptance)
            if (in_array($tool_name, ['edit_post', 'insert_content', 'write_to_post']) && 
                isset($result['preview']) && $result['preview'] === true) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Makes a follow-up API call with the results of the tool executions.
     * This continues the conversation and allows the AI to summarize results.
     */
    private function make_follow_up_call() {
        error_log("Wordsurf DEBUG: Preparing follow-up call with " . count($this->pending_tool_calls) . " tool results.");

        // First, add the assistant's last message (which contained the tool calls) to history.
        // Since we don't have the message content here, we need a placeholder or reconstruct it.
        // For now, we'll construct the tool_calls part of the message.
        $tool_calls_for_history = [];
        foreach ($this->pending_tool_calls as $call) {
            $tool_calls_for_history[] = [
                'type' => 'function_call',
                'id' => $call['tool_call_object']['call_id'],
                'name' => $call['tool_call_object']['name'],
                'arguments' => $call['tool_call_object']['arguments'],
            ];
        }
        $this->append_message([
            'role' => 'assistant',
            'content' => null, // No text content in a tool-calling message
            'tool_calls' => $tool_calls_for_history,
        ]);
        
        // Then, add a message for each tool's result.
        foreach ($this->pending_tool_calls as $call) {
            $this->append_message([
                'role' => 'tool',
                'tool_call_id' => $call['tool_call_object']['call_id'],
                'content' => json_encode($call['result']),
            ]);
        }
        
        // Clear the pending calls as they are now in the history.
        $this->pending_tool_calls = [];

        // Now, make a new request to the API with the updated history.
        $body = [
            'model' => 'gpt-4.1',
            'input' => $this->message_history,
            'tools' => $this->tool_manager->get_tool_schemas(),
        ];
        
        // This follow-up call is also streamed directly to the client.
        $this->openai_client->stream_request($body);
    }
} 