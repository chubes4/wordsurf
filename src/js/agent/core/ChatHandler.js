import React, { useRef, useEffect, useCallback, useState } from 'react';
import { streamChatMessage } from './StreamApi';
import { ChatStreamSession } from './ChatStreamSession';

// Global flag to prevent concurrent EventSource connections
let globalEventSourceActive = false;

/**
 * ChatHandler - Refactored to use ChatStreamSession for all state and event management
 */
export const useChatHandler = ({ postId, onDiffReceived, onUserDecision, chatHistory }) => {
    // Session instance (persists across renders)
    const sessionRef = useRef(null);
    if (!sessionRef.current) {
        sessionRef.current = new ChatStreamSession(chatHistory.current, onDiffReceived);
    }
    const session = sessionRef.current;

    // React state for UI updates
    const [uiState, setUIState] = useState(session.getState());
    const [inputValue, setInputValue] = useState('');
    
    // Update UI state when session changes
    const updateUIState = useCallback(() => {
        setUIState(session.getState());
    }, [session]);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            session.cleanup();
        };
    }, [session]);

    // Controlled input value
    useEffect(() => {
        session.setInputValue(inputValue);
    }, [inputValue, session]);

    // Send message handler
    const handleSend = useCallback(() => {
        const message = inputValue.trim();
        if (!message || session.isStreaming || session.hasSentMessage || session.currentEventSource) {
            return;
        }
        chatHistory.current.addUserMessage(message);
        updateUIState();
        setInputValue('');
        session.startStream(
            chatHistory.current.getOpenAIMessages(),
            postId,
            streamChatMessage,
            updateUIState,
            updateUIState,
            updateUIState // Pass as onUIUpdate
        );
    }, [inputValue, session, chatHistory, postId, updateUIState]);

    // Record user decision (tool accept/reject)
    const recordUserDecision = useCallback((action, toolData) => {
        chatHistory.current.addUserDecision(action, toolData);
        updateUIState();
        // Send tool action to backend (unchanged)
        const formData = new FormData();
        formData.append('action', 'wordsurf_tool_action');
        formData.append('action_type', action);
        formData.append('tool_call_id', toolData.tool_call_id);
        formData.append('tool_data', JSON.stringify(toolData));
        formData.append('nonce', window.wordsurfData?.nonce || '');
        fetch(window.wordsurfData.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Tool action processed successfully:', data);
            } else {
                console.error('Tool action failed:', data);
            }
        })
        .catch(error => {
            console.error('Error processing tool action:', error);
        });
    }, [chatHistory, updateUIState]);

    // Reset chat session state to allow for continuation
    const resetForContinuation = useCallback(() => {
        console.log('ChatHandler: Resetting for continuation');
        session.hasSentMessage = false;
        session.isStreaming = false;
        session.isWaiting = false;
        updateUIState();
    }, [session, updateUIState]);

    // Continue chat with tool result
    const continueWithToolResult = useCallback((toolResult) => {
        console.log('ChatHandler: Continuing with tool result:', toolResult);
        
        if (session.isStreaming || session.hasSentMessage || session.currentEventSource || globalEventSourceActive) {
            console.log('ChatHandler: Cannot continue - already streaming or has sent message', {
                isStreaming: session.isStreaming,
                hasSentMessage: session.hasSentMessage,
                hasEventSource: !!session.currentEventSource,
                globalActive: globalEventSourceActive
            });
            return;
        }

        // AI HTTP Client library handles response ID tracking internally
        console.log('ChatHandler: Starting continuation via AI HTTP Client library');

        // Ensure any existing EventSource is properly closed before creating new one
        if (session.currentEventSource) {
            console.log('ChatHandler: Closing existing EventSource before creating new one');
            session.currentEventSource.close();
            session.currentEventSource = null;
        }

        // Prepare data for tool result continuation
        const formData = new FormData();
        formData.append('action', 'wordsurf_continue_with_tool_result');
        formData.append('nonce', window.wordsurfData?.nonce || '');
        formData.append('tool_call_id', toolResult.toolCallId);
        formData.append('user_action', toolResult.action);
        formData.append('post_id', toolResult.postId);

        // Create EventSource for streaming response
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            params.append(key, value);
        }

        // Set global flag to prevent concurrent connections
        globalEventSourceActive = true;
        
        console.log('ChatHandler: Creating new EventSource for tool result continuation');
        const eventSource = new EventSource(`${window.wordsurfData?.ajax_url}?${params.toString()}`);
        
        session.isStreaming = true;
        session.hasSentMessage = true;
        session.isWaiting = true;
        session.currentEventSource = eventSource;
        updateUIState();

        eventSource.onmessage = (event) => {
            try {
                const eventData = JSON.parse(event.data);
                session.handleEvent(eventData);
                
                // Clear global flag on stream completion  
                if (eventData.type === 'stream_end') {
                    globalEventSourceActive = false;
                }
            } catch (error) {
                console.error('Error parsing SSE data:', error);
            }
        };

        eventSource.onerror = (error) => {
            console.error('EventSource error:', error);
            eventSource.close();
            session.isStreaming = false;
            session.isWaiting = false;
            session.hasSentMessage = false;
            session.currentEventSource = null;
            
            // Clear global flag
            globalEventSourceActive = false;
            
            updateUIState();
        };

        eventSource.onopen = () => {
            console.log('Tool result continuation stream opened');
        };

    }, [session, chatHistory, updateUIState]);

    return {
        isStreaming: uiState.isStreaming,
        isWaiting: uiState.isWaiting,
        inputValue,
        setInputValue,
        uiMessages: uiState.uiMessages,
        handleSend,
        updateUIMessages: updateUIState,
        recordUserDecision,
        resetForContinuation,
        continueWithToolResult,
        hasStreamingAssistant: uiState.hasStreamingAssistant,
        currentEventSource: session.currentEventSource,
        session: session, // Expose session for error recovery
    };
}; 