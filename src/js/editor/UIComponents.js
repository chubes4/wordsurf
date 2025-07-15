import React from 'react';

/**
 * Tool Status Display Component
 */
export const ToolStatus = ({ name, args, result, status }) => {
    const getIcon = () => {
        if (status === 'in_progress') return <div className="spinner"></div>;
        if (result?.success === true) return <div className="icon">✅</div>;
        if (result?.success === false) return <div className="icon">❌</div>;
        return <div className="icon">⚙️</div>; // Default icon for pending
    };

    // Safely parse arguments for display
    let displayArgs = args;
    if (typeof args === 'string') {
        try {
            displayArgs = JSON.parse(args);
        } catch (e) {
            // Keep as string if not valid JSON
        }
    }

    return (
        <div className="tool-call">
            <div className="tool-header">
                {getIcon()}
                <span className="tool-name">{name}</span>
            </div>
            {/* We can add more details here later if needed */}
        </div>
    );
};


/**
 * Chat Message Component
 */
export const ChatMessage = ({ message }) => {
    if (message.type === 'tool') {
        return (
            <div className={`chat-message ${message.author}`}>
                <ToolStatus 
                    name={message.tool_name}
                    args={message.tool_args}
                    result={message.result}
                    status={message.result ? 'completed' : 'in_progress'}
                />
            </div>
        );
    }

    // Default to text message
    return (
        <div className={`chat-message ${message.author}`}>
            <div className="chat-bubble">
                {message.content}
                {message.isStreaming && (
                    <span className="typing-indicator">
                        <span></span><span></span><span></span>
                    </span>
                )}
            </div>
        </div>
    );
};

/**
 * Chat Input Component
 */
export const ChatInput = ({ 
    value, 
    onChange, 
    onSend, 
    disabled = false, 
    placeholder = "Ask me to edit, write, or brainstorm..." 
}) => {
    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            onSend();
        }
    };

    return (
        <div className="wordsurf-input-area">
            <textarea
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                disabled={disabled}
                onKeyDown={handleKeyDown}
            />
            <button 
                onClick={onSend} 
                disabled={disabled || !value.trim()}
            >
                Send
            </button>
        </div>
    );
};

/**
 * Pending Changes Component - Revolutionary approach: just ready to clear diffs
 */
export const PendingChanges = ({ diffs, onAcceptAll, onRejectAll }) => {
    if (!diffs || diffs.length === 0) {
        return null;
    }
    console.log('PendingChanges: Rendering diff control UI for', diffs.length, 'diffs');

    return (
        <div className="wordsurf-pending-changes">
            <div className="pending-changes-content">
                <div className="pending-changes-label">Pending changes:</div>
                <div className="pending-changes-actions">
                    <button 
                        className="button button-primary" 
                        onClick={onAcceptAll}
                        style={{ marginRight: '8px' }}
                    >
                        Accept All
                    </button>
                    <button 
                        className="button button-secondary" 
                        onClick={onRejectAll}
                    >
                        Reject All
                    </button>
                </div>
            </div>
        </div>
    );
};

/**
 * Main Chat Interface Component
 */
export const ChatInterface = ({ 
    messages, 
    isWaiting, 
    inputValue, 
    onInputChange, 
    onSend, 
    isStreaming,
    hasStreamingAssistant,
    pendingDiffs,
    onAcceptAllChanges,
    onRejectAllChanges
}) => {
    const chatWindowRef = React.useRef(null);

    // Auto-scroll to bottom when messages change
    React.useEffect(() => {
        if (chatWindowRef.current) {
            chatWindowRef.current.scrollTop = chatWindowRef.current.scrollHeight;
        }
    }, [messages]);

    return (
        <div className="wordsurf-sidebar-content">
            <div className="wordsurf-chat-window" ref={chatWindowRef}>
                {messages.map((msg, idx) => (
                    <ChatMessage key={idx} message={msg} />
                ))}
                {/* Show 'Thinking...' if waiting and no streaming assistant message */}
                {isWaiting && !hasStreamingAssistant && (
                    <div className="chat-message agent">
                        <div className="chat-bubble">
                            Thinking...
                            <span className="typing-indicator">
                                <span></span><span></span><span></span>
                            </span>
                        </div>
                    </div>
                )}
                {/* Show pending changes if any */}
                <PendingChanges 
                    diffs={pendingDiffs}
                    onAcceptAll={onAcceptAllChanges}
                    onRejectAll={onRejectAllChanges}
                />
            </div>
            <ChatInput
                value={inputValue}
                onChange={onInputChange}
                onSend={onSend}
                disabled={isStreaming}
            />
        </div>
    );
}; 