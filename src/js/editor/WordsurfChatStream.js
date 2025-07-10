import React, { useState, useRef, useEffect } from 'react';
import { streamChatMessage } from './chatStreamApi';
import ToolCall from './ToolCall';

const WordsurfChatStream = ({ initialMessages = [], postId, onSend, onDiffReceived }) => {
  const [messages, setMessages] = useState(initialMessages);
  const [inputValue, setInputValue] = useState('');
  const [isStreaming, setIsStreaming] = useState(false);
  const [streamedMessage, setStreamedMessage] = useState('');
  const [toolCalls, setToolCalls] = useState([]);
  const [isWaiting, setIsWaiting] = useState(false);
  const streamedMessageRef = useRef('');
  const chatWindowRef = useRef(null);

  useEffect(() => {
    if (chatWindowRef.current) {
      chatWindowRef.current.scrollTop = chatWindowRef.current.scrollHeight;
    }
  }, [messages, streamedMessage, toolCalls]);



  const handleSend = async () => {
    if (!inputValue.trim() || isStreaming) return;
    const userMessage = inputValue.trim();
    const openaiMessages = [
      ...messages.map(msg =>
        msg.author === 'user'
          ? { role: 'user', content: msg.content }
          : { role: 'assistant', content: msg.content }
      ),
      { role: 'user', content: userMessage }
    ];
    setMessages(prev => [...prev, { author: 'user', content: userMessage }]);
    setInputValue('');
    setIsStreaming(true);
    setStreamedMessage('');
    setToolCalls([]);
    streamedMessageRef.current = '';
    setIsWaiting(true); // Show "Thinking..." immediately
    if (onSend) onSend(userMessage);

    await streamChatMessage(
      openaiMessages,
      postId,
      (event) => {
        setIsWaiting(false); // Clear "Thinking..." as soon as the first event arrives
        if (!event.type || !event.data) return;

        if (event.type === 'tool_start') {
            setToolCalls(prev => [...prev, { name: event.data.name, status: 'in_progress' }]);
        } else if (event.type === 'tool_end') {
            setToolCalls(prev => prev.map(tc => tc.name === event.data.name ? { ...tc, status: event.data.status } : tc));
            
            // Check if this is a tool result with preview data (edit_post, insert_content, or write_to_post)
            if ((event.data.name === 'edit_post' || event.data.name === 'insert_content' || event.data.name === 'write_to_post') && 
                event.data.result && event.data.result.preview) {
              // Send diff data to parent component for editor overlay
              if (onDiffReceived) {
                onDiffReceived(event.data.result);
              }
            }
        } else if (event.type === 'system') {
            // Display a system message, like "Thinking..."
            setToolCalls(prev => [...prev, { name: event.data.content, status: 'system' }]);
        } else if (event.type === 'text') {
            // Once text starts streaming, clear the tool call indicators
            if (toolCalls.length > 0) {
                setToolCalls([]);
            }
            if (event.data.content) {
                streamedMessageRef.current += event.data.content;
                setStreamedMessage(streamedMessageRef.current);
            }
        }
      },
      () => {
        if (streamedMessageRef.current) {
          setMessages(prev => [...prev, { author: 'agent', content: streamedMessageRef.current }]);
        }
        setStreamedMessage('');
        setToolCalls([]);
        setIsStreaming(false);
        setIsWaiting(false);
      },
      (err) => {
        setIsStreaming(false);
        setToolCalls([]);
        setStreamedMessage('');
        setIsWaiting(false);
        setMessages(prev => [...prev, { author: 'agent', content: 'Sorry, there was an error.' }]);
      }
    );
  };

  return (
    <div className="wordsurf-sidebar-content">
      <div className="wordsurf-chat-window" ref={chatWindowRef}>
        {messages.map((msg, idx) => (
          <div key={idx} className={`chat-message ${msg.author}`}>
            <div className="chat-bubble">{msg.content}</div>
          </div>
        ))}
        {isWaiting && <ToolCall name="Thinking..." status="system" />}
        {toolCalls.map((tc, idx) => (
            <ToolCall key={idx} name={tc.name} status={tc.status} />
        ))}

        {isStreaming && streamedMessage && (
          <div className="chat-message agent">
            <div className="chat-bubble">
              {streamedMessage}
              <span className="typing-indicator">
                <span></span><span></span><span></span>
              </span>
            </div>
          </div>
        )}
      </div>
      <div className="wordsurf-input-area">
        <textarea
          value={inputValue}
          onChange={e => setInputValue(e.target.value)}
          placeholder="Ask me to edit, write, or brainstorm..."
          disabled={isStreaming}
          onKeyDown={e => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              handleSend();
            }
          }}
        />
        <button onClick={handleSend} disabled={isStreaming || !inputValue.trim()}>
          Send
        </button>
      </div>
    </div>
  );
};

export default WordsurfChatStream; 