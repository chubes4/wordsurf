// ChatStreamSession.js
// Centralized state manager for chat streaming, message lifecycle, and event handling

// Simple message creation helpers for AI HTTP Client library
const createUserMessage = (content) => ({ role: 'user', content });
const createAssistantMessage = (content) => ({ role: 'assistant', content });
const createToolCallMessage = (toolCallId, toolName, toolArguments) => ({
    role: 'assistant',
    tool_calls: [{
        id: toolCallId,
        type: 'function',
        function: {
            name: toolName,
            arguments: typeof toolArguments === 'string' ? toolArguments : JSON.stringify(toolArguments)
        }
    }]
});
const createToolResultMessage = (toolCallId, toolResult) => ({
    role: 'tool',
    tool_call_id: toolCallId,
    content: typeof toolResult === 'string' ? toolResult : JSON.stringify(toolResult)
});

// Global flag to prevent concurrent EventSource connections
let globalEventSourceActive = false;

export class ChatStreamSession {
    constructor(chatHistory, onDiffReceived) {
        this.chatHistory = chatHistory;
        this.onDiffReceived = onDiffReceived;
        this.isStreaming = false;
        this.isWaiting = false;
        this.streamingBuffer = '';
        this.accumulatedContent = ''; // Full accumulated message content
        this.hasSentMessage = false;
        this.currentEventSource = null;
        this.animationFrameId = null;
        this.onUIUpdate = null; // UI update callback
        this.streamingMessageIndex = -1; // Track which message is being streamed
        this.currentResponseId = null; // Store response ID for continuation
    }

    // Start a new chat turn
    startStream(openaiMessages, postId, streamApi, onComplete, onError, onUIUpdate) {
        if (this.isStreaming || this.hasSentMessage || this.currentEventSource || globalEventSourceActive) {
            console.log('ChatStreamSession: Cannot start stream - already active', {
                isStreaming: this.isStreaming,
                hasSentMessage: this.hasSentMessage,
                hasEventSource: !!this.currentEventSource,
                globalActive: globalEventSourceActive
            });
            return;
        }
        
        // Ensure any existing EventSource is properly closed before creating new one
        if (this.currentEventSource) {
            console.log('ChatStreamSession: Closing existing EventSource before creating new one');
            this.currentEventSource.close();
            this.currentEventSource = null;
        }
        
        // Set global flag to prevent concurrent connections
        globalEventSourceActive = true;
        
        this.isStreaming = true;
        this.isWaiting = true; // Show 'Thinking...' immediately
        this.hasSentMessage = true;
        this.streamingBuffer = '';
        this.accumulatedContent = '';
        this.onUIUpdate = onUIUpdate;
        this.streamingMessageIndex = -1;

        console.log('ChatStreamSession: Creating new EventSource for chat stream');
        this.currentEventSource = streamApi(
            openaiMessages,
            postId,
            (event) => this.handleEvent(event),
            () => this.handleComplete(onComplete),
            (err) => this.handleError(onError, err)
        );
        if (this.onUIUpdate) this.onUIUpdate(); // Update UI for 'Thinking...'
    }

    // Handle incoming events from StreamApi
    handleEvent(event) {
        if (!event.type || !event.data) return;
        switch (event.type) {
            case 'tool_start':
                this.isWaiting = false;
                this.flushStreamingBuffer();
                this.chatHistory.addToolCall(event.data.call_id, event.data.name, event.data.arguments);
                this.finalizeStreamingMessage();
                break;
            case 'text':
                this.isWaiting = false;
                if (event.data.content != null) {
                    this.streamingBuffer += event.data.content;
                    if (!this.animationFrameId) {
                        this.animationFrameId = requestAnimationFrame(() => {
                            this.flushStreamingBuffer();
                            if (this.onUIUpdate) this.onUIUpdate();
                        });
                    }
                }
                break;
            case 'stream_end':
                // Don't flush here - handleComplete() will handle final flush
                this.finalizeStreamingMessage();
                break;
            case 'tool_result':
                this.isWaiting = false;
                const { tool_call_id, tool_name, result, response_id } = event.data;
                
                // Store response ID for continuation
                if (response_id) {
                    this.currentResponseId = response_id;
                    console.log('ChatStreamSession: Stored response ID for continuation:', response_id);
                }
                
                console.log('ChatStreamSession: Received tool_result:', {
                    tool_call_id,
                    tool_name,
                    result,
                    response_id,
                    preview: result?.preview,
                    hasCallback: !!this.onDiffReceived
                });
                
                // Debug: Log the full event data structure
                console.log('ChatStreamSession: DEBUG - Full event.data structure:', event.data);
                console.log('ChatStreamSession: DEBUG - result object keys:', result ? Object.keys(result) : 'result is null/undefined');
                console.log('ChatStreamSession: DEBUG - result.preview value and type:', result?.preview, typeof result?.preview);
                
                this.chatHistory.updateToolCall(tool_call_id, {
                    success: result.success,
                    completed: true,
                    ...result
                });
                
                // The actual tool result is nested under result.result
                const actualToolResult = result.result || result;
                
                // Show diff if we have diff data (Wordsurf always shows previews for content changes)
                const hasDiffData = actualToolResult.target_blocks || actualToolResult.original_content || actualToolResult.diff_block_content;
                
                if (hasDiffData && this.onDiffReceived) {
                    console.log('ChatStreamSession: CALLING onDiffReceived with diff data');
                    console.log('ChatStreamSession: Full result data:', result);
                    console.log('ChatStreamSession: Actual tool result:', actualToolResult);
                    this.onDiffReceived({
                        tool_call_id,
                        original_content: actualToolResult.original_content,
                        new_content: actualToolResult.new_content,
                        search_pattern: actualToolResult.search_pattern,
                        replacement_text: actualToolResult.replacement_text,
                        diff_block_content: actualToolResult.diff_block_content, // Legacy field for backward compatibility
                        target_blocks: actualToolResult.target_blocks, // New granular approach
                        diff_id: actualToolResult.diff_id,
                        tool_name,
                        message: actualToolResult.message
                    });
                } else {
                    console.log('ChatStreamSession: NOT calling onDiffReceived because:', {
                        hasDiffData: !!hasDiffData,
                        hasCallback: !!this.onDiffReceived,
                        hasTargetBlocks: !!actualToolResult.target_blocks,
                        hasOriginalContent: !!actualToolResult.original_content,
                        hasDiffBlockContent: !!actualToolResult.diff_block_content,
                        resultKeys: result ? Object.keys(result) : 'no result',
                        actualToolResultKeys: actualToolResult ? Object.keys(actualToolResult) : 'no actualToolResult'
                    });
                }
                break;
            default:
                break;
        }
        if (this.onUIUpdate) this.onUIUpdate(); // Always update UI after event
    }

    // Flush the streaming buffer and update the UI
    flushStreamingBuffer() {
        if (this.streamingBuffer.length > 0) {
            // Accumulate the content
            this.accumulatedContent += this.streamingBuffer;
            
            if (this.streamingMessageIndex === -1) {
                // Only create message if there's actual content (not just whitespace)
                if (this.accumulatedContent.trim().length > 0) {
                    this.chatHistory.addAssistantMessage(this.accumulatedContent, true);
                    this.streamingMessageIndex = this.chatHistory.getMessageCount() - 1;
                }
            } else {
                // Update existing streaming message with accumulated content
                this.chatHistory.updateMessageContent(this.streamingMessageIndex, this.accumulatedContent);
            }
            this.streamingBuffer = '';
        }
        this.animationFrameId = null;
    }

    // Finalize the current streaming message
    finalizeStreamingMessage() {
        if (this.streamingMessageIndex !== -1) {
            this.chatHistory.updateMessageStreamingStatus(this.streamingMessageIndex, false);
        }
    }

    // Handle stream completion
    handleComplete(onComplete) {
        if (this.streamingBuffer.length > 0) {
            this.flushStreamingBuffer();
        }
        this.finalizeStreamingMessage();
        this.isStreaming = false;
        this.isWaiting = false;
        this.hasSentMessage = false;
        this.currentEventSource = null;
        this.streamingMessageIndex = -1;
        this.accumulatedContent = '';
        
        // Clear global flag
        globalEventSourceActive = false;
        
        if (onComplete) onComplete();
        if (this.onUIUpdate) this.onUIUpdate();
    }

    // Handle stream error
    handleError(onError, err) {
        if (this.streamingBuffer.length > 0) {
            this.flushStreamingBuffer();
        } else if (this.accumulatedContent.length === 0) {
            // Create error message if no content was streamed at all
            this.chatHistory.addAssistantMessage('Sorry, there was an error.', false);
        }
        this.finalizeStreamingMessage();
        this.isStreaming = false;
        this.isWaiting = false;
        this.hasSentMessage = false;
        this.currentEventSource = null;
        this.streamingMessageIndex = -1;
        this.accumulatedContent = '';
        
        // Clear global flag
        globalEventSourceActive = false;
        
        if (onError) onError(err);
        if (this.onUIUpdate) this.onUIUpdate();
    }

    // Expose state for UI
    getState() {
        return {
            isStreaming: this.isStreaming,
            isWaiting: this.isWaiting,
            uiMessages: this.chatHistory.getUIMessages(),
            hasStreamingAssistant: this.hasStreamingAssistantMessage(),
        };
    }

    // Check if there is a streaming assistant message (for UI 'Thinking...' logic)
    hasStreamingAssistantMessage() {
        const msgs = this.chatHistory.getUIMessages();
        return msgs.some(m => m.role === 'assistant' && m.isStreaming);
    }

    // Set input value (for controlled input)
    setInputValue(value) {
        this.inputValue = value;
    }

    // Get input value
    getInputValue() {
        return this.inputValue || '';
    }

    // Get current response ID for continuation
    getCurrentResponseId() {
        return this.currentResponseId;
    }

    // Force cleanup connection (for error recovery)
    forceCleanupConnection() {
        console.log('ChatStreamSession: Force cleaning up connection');
        
        if (this.currentEventSource) {
            this.currentEventSource.close();
            this.currentEventSource = null;
        }
        
        this.isStreaming = false;
        this.isWaiting = false;
        this.hasSentMessage = false;
        
        // Clear global flag
        globalEventSourceActive = false;
        
        if (this.onUIUpdate) {
            this.onUIUpdate();
        }
    }

    // Clean up (e.g., on unmount)
    cleanup() {
        console.log('ChatStreamSession: Cleaning up session');
        
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
        
        if (this.currentEventSource) {
            console.log('ChatStreamSession: Closing EventSource during cleanup');
            this.currentEventSource.close();
            this.currentEventSource = null;
        }
        
        // Reset state
        this.isStreaming = false;
        this.isWaiting = false;
        this.hasSentMessage = false;
        this.streamingBuffer = '';
        this.accumulatedContent = '';
        this.streamingMessageIndex = -1;
        this.currentResponseId = null;
        
        // Clear global flag
        globalEventSourceActive = false;
    }
} 