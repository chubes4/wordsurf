/**
 * ChatHistory Module
 * 
 * Centralized management of conversation history including:
 * - Message storage and retrieval
 * - Basic conversation flow
 * - Integration with ToolHistory for tool-specific tracking
 */

// Simple message creation helpers for AI HTTP Client library
const createUserMessage = (content) => ({ role: 'user', content });
const createAssistantMessage = (content) => ({ role: 'assistant', content });
import { ToolHistory } from './ToolHistory';

export class ChatHistory {
  constructor(initialMessages = []) {
    this.messages = this.validateInitialMessages(initialMessages);
    this.toolHistory = new ToolHistory();
  }

  /**
   * Validate initial messages are in correct format
   */
  validateInitialMessages(messages) {
    return Array.isArray(messages) ? messages : [];
  }

  /**
   * Get all messages
   */
  getMessages() {
    return [...this.messages];
  }

  /**
   * Get messages formatted for AI HTTP Client library
   */
  getOpenAIMessages() {
    return this.messages;
  }

  /**
   * Get messages formatted for UI display (excludes tool calls/results)
   */
  getUIMessages() {
    // With the standardized message format, all messages are UI-displayable
    return this.messages;
  }

  /**
   * Add a user message
   */
  addUserMessage(content) {
    this.messages.push(createUserMessage(content));
    return this;
  }

  /**
   * Add an assistant message
   */
  addAssistantMessage(content, isStreaming = false) {
    this.messages.push(createAssistantMessage(content, isStreaming));
    return this;
  }

  /**
   * Update the content of a specific message by index
   */
  updateMessageContent(messageIndex, content) {
    if (this.messages[messageIndex]) {
      this.messages[messageIndex].content = content;
    }
    return this;
  }

  /**
   * Update streaming status of a specific message by index
   */
  updateMessageStreamingStatus(messageIndex, isStreaming) {
    if (this.messages[messageIndex]) {
      this.messages[messageIndex].isStreaming = isStreaming;
    }
    return this;
  }


  /**
   * Add a tool call from the assistant
   */
  addToolCall(toolCallId, toolName, toolArguments) {
    // Prevent duplicate tool calls
    if (this.messages.some(m => m.type === 'tool' && m.id === toolCallId)) {
      return this;
    }
    // Create the message in the format the UI expects from the start.
    this.messages.push({
      role: 'assistant',
      id: toolCallId,
      author: 'agent',
      type: 'tool',
      tool_name: toolName,
      tool_args: toolArguments,
      result: null, // No result yet
      isStreaming: true, // It's in progress
    });
    return this;
  }

  /**
   * Updates a tool call with its result.
   */
  updateToolCall(toolCallId, result) {
    const message = this.messages.find(m => m.type === 'tool' && m.id === toolCallId);
    if (message) {
      message.result = result;
      message.isStreaming = false;
    }
    return this;
  }

  /**
   * Add a tool result
   */
  addToolResult(toolCallId, toolResult) {
    const toolResultMessage = this.toolHistory.createToolResult(toolCallId, toolResult);
    this.messages.push(toolResultMessage);
    return this;
  }

  /**
   * Add tool call and result as a pair (convenience method)
   */
  addToolCallAndResult(toolCallId, toolName, toolArguments, toolResult) {
    const [toolCallMessage, toolResultMessage] = this.toolHistory.createToolCallAndResult(
      toolCallId, toolName, toolArguments, toolResult
    );
    this.messages.push(toolCallMessage, toolResultMessage);
    return this;
  }

  /**
   * Track a pending tool result (during streaming)
   */
  trackPendingToolResult(toolCallId, toolName, toolArguments, toolResult) {
    this.toolHistory.trackPendingResult(toolCallId, toolName, toolArguments, toolResult);
    return this;
  }

  /**
   * Add all pending tool results to message history
   */
  flushPendingToolResults() {
    const pendingMessages = this.toolHistory.getPendingResultMessages();
    this.messages.push(...pendingMessages);
    this.toolHistory.clearPendingResults();
    return this;
  }

  /**
   * Add user decision about a tool suggestion
   */
  addUserDecision(action, toolData) {
    const decisionMessage = this.toolHistory.createUserDecisionMessage(action, toolData);
    this.messages.push(decisionMessage);
    return this;
  }

  /**
   * Clear all messages
   */
  clear() {
    this.messages = [];
    this.toolHistory.clearPendingResults();
    return this;
  }

  /**
   * Get the last message
   */
  getLastMessage() {
    return this.messages[this.messages.length - 1] || null;
  }

  /**
   * Get message count
   */
  getMessageCount() {
    return this.messages.length;
  }

  /**
   * Replace entire message history (for loading saved conversations)
   */
  setMessages(newMessages) {
    this.messages = Array.isArray(newMessages) ? newMessages : [];
    return this;
  }

  /**
   * Debug utility - log current state
   */
  debug() {
    console.log('ChatHistory Debug:', {
      messageCount: this.messages.length,
      messages: this.messages
    });
    this.toolHistory.debug();
  }
} 