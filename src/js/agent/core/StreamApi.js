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
    let hasReceivedCompletion = false;
    let isConnectionClosed = false;
    let hasToolCalls = false;
    
    // Log ALL events received for debugging
    eventSource.onmessage = function(event) {
        console.log('🚀 DEFAULT MESSAGE EVENT:', event.type, event.data);
    };
    
    // Override the open event to log connection status
    eventSource.onopen = function(event) {
        console.log('🟢 EventSource connection opened');
    };
    
    eventSource.onerror = function(event) {
        console.log('🔴 EventSource error event:', event, 'readyState:', eventSource.readyState);
    };
    

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
                hasToolCalls = true; // Mark that we have tool calls
                onEvent({ type: 'tool_start', data: parsed.item });
            }
        } catch (e) {
             console.error('Failed to parse tool start:', event.data, e);
        }
    });

    // Tool result event (sent by backend after tool execution)
    eventSource.addEventListener('tool_result', (event) => {
        console.log('🔧 TOOL_RESULT EVENT RECEIVED:', event.data);
        console.log('🔧 Event object:', event);
        console.log('🔧 EventSource readyState:', eventSource.readyState);
        
        try {
            const parsed = JSON.parse(event.data);
            console.log('🔧 Parsed tool result:', parsed);
            console.log('🔧 About to call onEvent with:', { type: 'tool_result', data: parsed });
            onEvent({ type: 'tool_result', data: parsed });
            
            // Close the connection after receiving tool results
            isConnectionClosed = true;
            eventSource.close();
            if (onComplete) onComplete();
        } catch (e) {
            console.error('Failed to parse tool result:', event.data, e);
        }
    });

    // End of stream event
    eventSource.addEventListener('response.completed', (event) => {
        hasReceivedCompletion = true;
        try {
            const parsed = JSON.parse(event.data);
            console.log('StreamApi: Stream completed by API.', parsed);
            // Pass the entire final response object to the handler.
            onEvent({ type: 'stream_end', data: parsed.response });
            
            // Smart connection handling based on whether tools were involved
            if (hasToolCalls) {
                console.log('StreamApi: Stream completed, keeping connection open for tool results');
                // NO TIMEOUT - let tool_result event handle closing
                // This allows tools to take as long as they need
            } else {
                console.log('StreamApi: Stream completed, no tools detected, closing immediately');
                isConnectionClosed = true;
                eventSource.close();
                if (onComplete) onComplete();
            }
            
        } catch (e) {
            console.error('Failed to parse response.completed event:', event.data, e);
            // Try to recover by sending a basic completion event
            onEvent({ type: 'stream_end', data: { status: 'completed' } });
            
            // Close immediately on parse error (recovery mode)
            if (!isConnectionClosed) {
                console.log('StreamApi: Parse error recovery - closing connection immediately');
                isConnectionClosed = true;
                eventSource.close();
                if (onComplete) onComplete();
            }
        }
    });
    
    // Add a generic message handler to catch any events we're not specifically listening for
    eventSource.addEventListener('message', (event) => {
        // Only log if it's not one of our expected events
        if (!event.type.startsWith('response.')) {
            console.log('StreamApi: Received unexpected message event:', event);
        }
    });
    
    // The browser dispatches an error event when the connection is closed by the server.
    // We can often ignore this as 'response.completed' is our real signal.
    eventSource.addEventListener('error', (err) => {
        // Prevent automatic reconnection by closing immediately
        if (!isConnectionClosed) {
            isConnectionClosed = true;
            eventSource.close();
        }
        
        // Only treat as an error if we haven't received completion and the connection is not closed
        // The error event often fires when the server closes the connection normally
        if (!hasReceivedCompletion && eventSource.readyState !== EventSource.CLOSED) {
            console.error('StreamApi: EventSource error:', err);
            if (onError) onError(err);
        }
    });
    
    
    // Return the EventSource instance so the caller can close it if needed.
    return eventSource;
} 