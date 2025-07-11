/**
 * Stream API - Handles communication with the WordPress backend.
 * Uses the native EventSource API for robust SSE handling.
 */
export function streamChatMessage(messages, postId, onEvent, onComplete, onError) {

    // Construct the URL with query parameters for EventSource (which only supports GET)
    // We must still use our AJAX endpoint, but we'll trick it into thinking it's a POST
    // by sending data in the query string and checking for it in PHP. This is a
    // common workaround for streaming via WordPress AJAX.
    const params = new URLSearchParams({
        action: 'wordsurf_stream_chat',
        messages: JSON.stringify(messages),
        post_id: postId,
        context_window: JSON.stringify({}), // For future use
        nonce: window.wordsurfData?.nonce || ''
    });

    const eventSource = new EventSource(`${window.wordsurfData.ajax_url}?${params.toString()}`);

    // Generic event handler
    const handleEvent = (event_type, event) => {
        try {
            const data = JSON.parse(event.data);
            console.log(`StreamApi: Received '${event_type}':`, data);
            onEvent({ type: event_type, data });
        } catch (e) {
            console.error(`Failed to parse ${event_type} data:`, event.data, e);
        }
    };

    // Text streaming event
    eventSource.addEventListener('response.output_text.delta', (event) => {
        try {
            const parsed = JSON.parse(event.data);
            if (parsed.delta) {
                 onEvent({ type: 'text', data: { content: parsed.delta } });
            }
        } catch (e) {
             console.error('Failed to parse text delta:', event.data, e);
        }
    });

    // Tool call start event
    eventSource.addEventListener('response.output_item.added', (event) => {
        try {
            const parsed = JSON.parse(event.data);
            if (parsed.item && parsed.item.type === 'function_call') {
                 onEvent({ type: 'tool_start', data: parsed.item });
            }
        } catch (e) {
             console.error('Failed to parse tool start:', event.data, e);
        }
    });

    // End of stream event
    eventSource.addEventListener('response.completed', (event) => {
        console.log('StreamApi: Stream completed by API.');
        eventSource.close();
        if (onComplete) onComplete();
    });
    
    // Final catch-all for the connection closing
    eventSource.addEventListener('error', (err) => {
        console.error('EventSource error:', err);
        // Avoid duplicate calls if already completed.
        if (eventSource.readyState === EventSource.CLOSED) {
            if (onComplete) onComplete();
        } else if (onError) {
             onError(err);
        }
        eventSource.close();
    });
    
    // Return the EventSource instance so the caller can close it if needed.
    return eventSource;
} 