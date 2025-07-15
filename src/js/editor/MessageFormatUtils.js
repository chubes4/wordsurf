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
    
    // Convert messages to proper OpenAI format
    const cleanedMessages = filteredMessages.map(msg => {
        const { isStreaming, ...cleanMsg } = msg;
        
        // Convert tool call messages to proper OpenAI format
        if (cleanMsg.type === 'tool' && cleanMsg.role === 'assistant') {
            return {
                role: 'assistant',
                tool_calls: [
                    {
                        id: cleanMsg.id,
                        type: 'function',
                        function: {
                            name: cleanMsg.tool_name,
                            arguments: typeof cleanMsg.tool_args === 'string' ? cleanMsg.tool_args : JSON.stringify(cleanMsg.tool_args || {})
                        }
                    }
                ]
            };
        }
        
        return cleanMsg;
    });
    
    return validateOpenAIMessages(cleanedMessages);
}

/**
 * Create an OpenAI format user message
 */
export function createUserMessage(content) {
    return {
        role: 'user',
        content: content,
        type: 'text',
        author: 'user',
    };
}

/**
 * Create an OpenAI format assistant message
 */
export function createAssistantMessage(content, isStreaming = false) {
    return {
        role: 'assistant',
        content: content,
        type: 'text',
        author: 'agent',
        isStreaming: isStreaming,
    };
} 

/**
 * Create a tool call message
 */
export function createToolCallMessage(toolCallId, toolName, toolArguments) {
    return {
        role: 'assistant',
        id: toolCallId,
        author: 'agent',
        type: 'tool',
        tool_name: toolName,
        tool_args: toolArguments,
        result: null,
        isStreaming: true,
    };
}

/**
 * Create a tool result message
 */
export function createToolResultMessage(toolCallId, toolResult) {
    return {
        role: 'tool',
        tool_call_id: toolCallId,
        content: JSON.stringify(toolResult),
        // Keep these for UI display purposes
        author: 'agent',
        type: 'tool',
        tool_name: toolResult.tool_name || '',
        tool_args: toolResult.tool_args || {},
        result: toolResult,
        isStreaming: false,
    };
} 