import React, { useRef, useEffect, useCallback, useState } from 'react';
import { streamChatMessage } from './StreamApi';
import { ChatStreamSession } from './ChatStreamSession';

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

    return {
        isStreaming: uiState.isStreaming,
        isWaiting: uiState.isWaiting,
        inputValue,
        setInputValue,
        uiMessages: uiState.uiMessages,
        handleSend,
        updateUIMessages: updateUIState,
        recordUserDecision,
        hasStreamingAssistant: uiState.hasStreamingAssistant,
    };
}; 