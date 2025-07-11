import React, { useState, useRef, useEffect, useCallback } from 'react';
import { streamChatMessage } from './StreamApi';

/**
 * ChatHandler - Manages chat flow and coordinates with ToolManager
 */
export const useChatHandler = ({ postId, onDiffReceived, onUserDecision, chatHistory }) => {
    const [isStreaming, setIsStreaming] = useState(false);
    const [isWaiting, setIsWaiting] = useState(false);
    const [inputValue, setInputValue] = useState('');
    
    const [uiMessages, setUIMessages] = useState(chatHistory.current.getUIMessages());
    
    // Buffer for streaming text deltas to improve render performance
    const streamingBuffer = useRef('');
    const animationFrameId = useRef(null);

    const updateUIMessages = useCallback(() => {
        setUIMessages([...chatHistory.current.getUIMessages()]);
    }, [chatHistory]);
    
    // Flushes the buffer and updates the UI.
    const flushStreamingBuffer = useCallback(() => {
        if (streamingBuffer.current.length > 0) {
            chatHistory.current.addOrUpdateAssistantMessage(streamingBuffer.current);
            streamingBuffer.current = '';
            updateUIMessages();
        }
        animationFrameId.current = null;
    }, [chatHistory, updateUIMessages]);

    // Cleanup function to cancel any pending animation frame.
    useEffect(() => {
        return () => {
            if (animationFrameId.current) {
                cancelAnimationFrame(animationFrameId.current);
            }
        };
    }, []);

    const recordUserDecision = useCallback((action, toolData) => {
        chatHistory.current.addUserDecision(action, toolData);
        updateUIMessages();
    }, [chatHistory, updateUIMessages]);

    const handleSend = useCallback(async () => {
        const message = inputValue.trim();
        if (!message || isStreaming) return;
        
        chatHistory.current.addUserMessage(message);
        updateUIMessages();
        
        setInputValue('');
        setIsStreaming(true);
        setIsWaiting(true);

        const openaiMessages = chatHistory.current.getOpenAIMessages();

        streamChatMessage(
            openaiMessages,
            postId,
            (event) => { // onEvent
                setIsWaiting(false);
                if (!event.type || !event.data) return;

                // The new StreamApi sends native OpenAI events, which we handle here.
                if (event.type === 'tool_start') {
                    flushStreamingBuffer(); // Flush any pending text before showing the tool.
                    const { name, call_id, arguments: toolArgs } = event.data;
                    chatHistory.current.addToolCall(call_id, name, toolArgs);
                    updateUIMessages();

                } else if (event.type === 'text') {
                    if (event.data.content != null) {
                        streamingBuffer.current += event.data.content;
                        if (!animationFrameId.current) {
                            animationFrameId.current = requestAnimationFrame(flushStreamingBuffer);
                        }
                    }
                }
                // The 'system' event type for 'Thinking...' has been removed.
                // The UI should use the `isWaiting` state to show a temporary thinking indicator.
            },
            () => { // onComplete
                flushStreamingBuffer(); // Final flush to catch any remaining text
                // Finalize the last message to remove the streaming indicator.
                chatHistory.current.finalizeLastMessage();
                updateUIMessages();
                setIsStreaming(false);
                setIsWaiting(false);
            },
            (err) => { // onError
                flushStreamingBuffer(); // Ensure buffer is cleared on error
                chatHistory.current.addOrUpdateAssistantMessage('Sorry, there was an error.');
                chatHistory.current.finalizeLastMessage();
                updateUIMessages();
                setIsStreaming(false);
                setIsWaiting(false);
            }
        );
    }, [postId, chatHistory, inputValue, isStreaming, onDiffReceived, updateUIMessages, flushStreamingBuffer]);

    return {
        isStreaming,
        isWaiting,
        inputValue,
        setInputValue,
        uiMessages,
        handleSend,
        updateUIMessages,
        recordUserDecision,
    };
}; 