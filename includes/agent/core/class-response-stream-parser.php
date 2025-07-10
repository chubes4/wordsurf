<?php
/**
 * Wordsurf Response Stream Parser
 *
 * This class is responsible for parsing raw Server-Sent Events (SSE) data
 * from the OpenAI Responses API stream. It translates the low-level events
 * into higher-level, semantic callbacks for the agent core to act upon.
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Wordsurf_Response_Stream_Parser {
    /**
     * Callbacks for stream events.
     *
     * @var array
     */
    private $callbacks = [];

    /**
     * Internal buffer for incoming stream data.
     *
     * @var string
     */
    private $buffer = '';

    /**
     * Holds the state of the tool call currently being streamed.
     *
     * @var array|null
     */
    private $current_tool_call = null;

    /**
     * Register a callback for a specific stream event.
     *
     * @param string $event_name The name of the event (e.g., 'onToolStart').
     * @param callable $callback The function to execute when the event occurs.
     */
    public function on($event_name, $callback) {
        if (is_callable($callback)) {
            $this->callbacks[$event_name] = $callback;
        }
    }

    /**
     * Fire a registered callback.
     *
     * @param string $event_name The name of the event to fire.
     * @param mixed ...$args Arguments to pass to the callback.
     */
    private function fire($event_name, ...$args) {
        if (isset($this->callbacks[$event_name])) {
            call_user_func($this->callbacks[$event_name], ...$args);
        }
    }

    /**
     * Process a chunk of raw data from the cURL stream.
     *
     * @param string $data The raw data chunk from the stream.
     */
    public function parse($data) {
        $this->buffer .= $data;
        $events = explode("\n\n", $this->buffer);
        $this->buffer = array_pop($events); // Keep the last, potentially incomplete event.

        foreach ($events as $event) {
            $lines = explode("\n", $event);
            $event_type = null;
            $data_line = null;
            
            foreach ($lines as $line) {
                if (strpos($line, 'event: ') === 0) {
                    $event_type = substr($line, 7);
                } elseif (strpos($line, 'data: ') === 0) {
                    $data_line = substr($line, 6);
                }
            }

            if (!$data_line) continue;

            $this->process_event($event_type, $data_line);
        }
    }

    /**
     * Process a single, complete SSE event.
     *
     * @param string $event_type The event type (e.g., 'content_block_start').
     * @param string $data_line The JSON data payload of the event.
     */
    private function process_event($event_type, $data_line) {
        $parsed = json_decode($data_line, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return; // Ignore JSON parse errors.
        }
        
        switch ($event_type) {
            // This event signals the start of a tool call.
            case 'response.output_item.added':
                if (isset($parsed['item']['type']) && $parsed['item']['type'] === 'function_call') {
                    // We have a new tool call. Store the initial object.
                    // The 'arguments' will be empty, and we will fill them with deltas.
                    $this->current_tool_call = $parsed['item'];
                    $this->current_tool_call['arguments'] = ''; // Ensure it starts empty
                    $this->fire('onToolStart', $this->current_tool_call['name'], $this->current_tool_call['call_id']);
                }
                break;
            
            // This event streams the arguments for the current tool call.
            case 'response.function_call_arguments.delta':
                 if ($this->current_tool_call && isset($parsed['delta'])) {
                    $this->current_tool_call['arguments'] .= $parsed['delta'];
                }
                break;

            // This event signals the arguments are complete and the tool can be executed.
            case 'response.function_call_arguments.done':
                 if ($this->current_tool_call) {
                    // The arguments are now complete. Let's make sure our object has the final, full argument string.
                    $this->current_tool_call['arguments'] = $parsed['arguments'];
                    // Fire the onToolEnd event with the single, complete tool_call object.
                    $this->fire('onToolEnd', $this->current_tool_call);
                    $this->current_tool_call = null;
                }
                break;

            // This event handles regular text responses.
            case 'response.output_text.delta':
                if (isset($parsed['delta'])) {
                    $this->fire('onTextDelta', $parsed['delta']);
                }
                break;
                
            // Handle other potential text events
            case 'response.text.delta':
                if (isset($parsed['delta'])) {
                    $this->fire('onTextDelta', $parsed['delta']);
                }
                break;
                
            // Handle content deltas (another possible format)
            case 'response.content.delta':
                if (isset($parsed['delta'])) {
                    $this->fire('onTextDelta', $parsed['delta']);
                }
                break;
                
            // Log unknown events for debugging
            default:
                error_log("Wordsurf DEBUG: Unknown event type: {$event_type}, data: " . $data_line);
                break;
        }
    }

    /**
     * Reset the parser's internal state for a new stream.
     */
    public function reset() {
        $this->buffer = '';
        $this->current_tool_call = null;
    }
} 