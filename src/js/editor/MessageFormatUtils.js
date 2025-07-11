/**
 * Message Format Utilities
 * 
 * Handles conversion between OpenAI API format and UI display format:
 * - OpenAI API format (role/content/tool_calls)
 * - UI display format (author/content, filtered)
 */

/**
 * Validate that a message is in OpenAI format
 */
export function validateOpenAIMessage(message) {
    if (!message.role) {
        throw new Error('Message must have a role property');
    }
    if (!['user', 'assistant', 'tool', 'system'].includes(message.role)) {
        throw new Error(`Invalid role: ${message.role}`);
    }
    return message;
}

/**
 * Validate an array of messages are in OpenAI format
 */
export function validateOpenAIMessages(messages) {
    return messages.map(validateOpenAIMessage);
}

/**
 * Get messages ready for OpenAI API (validation only)
 * All messages should already be in OpenAI format
 */
export function toOpenAIFormat(messages) {
    // Filter out our internal user decision messages before sending to OpenAI
    const filteredMessages = messages.filter(msg => {
        if (msg.role === 'user' && msg.content) {
            // This is a bit of a heuristic, but it's the best we can do without a dedicated message type
            return !msg.content.startsWith('User accepted the') && !msg.content.startsWith('User rejected the');
        }
        return true;
    });
    
    // Clean messages for OpenAI API - remove internal fields like isStreaming
    const cleanedMessages = filteredMessages.map(msg => {
        const { isStreaming, ...cleanMsg } = msg;
        return cleanMsg;
    });
    
    return validateOpenAIMessages(cleanedMessages);
}

/**
 * Convert OpenAI messages to UI display format
 * Filters out tool calls/results and converts to UI format
 */
export function toUIFormat(messages) {
    const uiMessages = [];
    for (let i = 0; i < messages.length; i++) {
        const msg = messages[i];

        if (msg.role === 'user' || (msg.role === 'assistant' && msg.content)) {
            uiMessages.push({
                author: msg.role === 'user' ? 'user' : 'agent',
                content: msg.content,
                type: 'text',
                isStreaming: msg.isStreaming || false,
            });
        } else if (msg.role === 'assistant' && msg.tool_calls) {
            const toolCall = msg.tool_calls[0];
            const resultMsg = messages[i + 1];

            let result = null;
            if (resultMsg && resultMsg.role === 'tool' && resultMsg.tool_call_id === toolCall.id) {
                try {
                    result = JSON.parse(resultMsg.content);
                } catch (e) {
                    console.error('Failed to parse tool result content:', resultMsg.content);
                }
                i++; // Skip the next message since we've processed it
            }

            uiMessages.push({
                author: 'agent',
                type: 'tool',
                tool_name: toolCall.function.name,
                tool_args: toolCall.function.arguments,
                result: result,
                id: toolCall.id,
            });
        }
    }
    return uiMessages;
}

/**
 * Check if a message is a tool call
 */
export function isToolCall(message) {
    return message.tool_calls && Array.isArray(message.tool_calls);
}

/**
 * Check if a message is a tool result
 */
export function isToolResult(message) {
    return message.role === 'tool' && message.tool_call_id;
}

/**
 * Check if a message should be displayed in UI
 */
export function shouldDisplayInUI(message) {
    return !isToolCall(message) && !isToolResult(message);
}

/**
 * Create an OpenAI format user message
 */
export function createUserMessage(content) {
    return {
        role: 'user',
        content: content
    };
}

/**
 * Create an OpenAI format assistant message
 */
export function createAssistantMessage(content) {
    return {
        role: 'assistant',
        content: content
    };
}

/**
 * Create an OpenAI format tool call message
 */
export function createToolCallMessage(toolCallId, toolName, toolArguments) {
    return {
        role: 'assistant',
        content: null,
        tool_calls: [{
            id: toolCallId,
            type: 'function',
            function: {
                name: toolName,
                arguments: toolArguments
            }
        }]
    };
}

/**
 * Create an OpenAI format tool result message
 */
export function createToolResultMessage(toolCallId, toolResult) {
    return {
        role: 'tool',
        tool_call_id: toolCallId,
        content: JSON.stringify(toolResult)
    };
} 