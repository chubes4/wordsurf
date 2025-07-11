/**
 * ChatHistory Module
 * 
 * Centralized management of conversation history including:
 * - Message storage and retrieval
 * - Basic conversation flow
 * - Integration with ToolHistory for tool-specific tracking
 */

import {
  validateOpenAIMessages,
  toOpenAIFormat,
  toUIFormat,
  createUserMessage,
  createAssistantMessage
} from '../editor/MessageFormatUtils';
import { ToolHistory } from './ToolHistory';

export class ChatHistory {
  constructor(initialMessages = []) {
    this.messages = this.validateInitialMessages(initialMessages);
    this.toolHistory = new ToolHistory();
  }

  /**
   * Validate initial messages are in OpenAI format
   */
  validateInitialMessages(messages) {
    return validateOpenAIMessages(messages);
  }

  /**
   * Get all messages
   */
  getMessages() {
    return [...this.messages];
  }

  /**
   * Get messages formatted for OpenAI API
   */
  getOpenAIMessages() {
    return toOpenAIFormat(this.messages);
  }

  /**
   * Get messages formatted for UI display (excludes tool calls/results)
   */
  getUIMessages() {
    return toUIFormat(this.messages);
  }

  /**
   * Add a user message
   */
  addUserMessage(content) {
    this.messages.push(createUserMessage(content));
    return this;
  }

  /**
   * Adds or updates the last assistant message with new content.
   * If the last message is from the assistant and is streaming, it appends content.
   * Otherwise, it adds a new streaming assistant message.
   */
  addOrUpdateAssistantMessage(contentChunk) {
    const lastMessage = this.getLastMessage();
    if (lastMessage && lastMessage.role === 'assistant' && lastMessage.isStreaming) {
      lastMessage.content += contentChunk;
    } else {
      this.messages.push({
        role: 'assistant',
        content: contentChunk,
        isStreaming: true,
      });
    }
    return this;
  }

  /**
   * Finalizes the last message by setting its streaming status to false.
   */
  finalizeLastMessage() {
    const lastMessage = this.getLastMessage();
    if (lastMessage && lastMessage.isStreaming) {
      lastMessage.isStreaming = false;
    }
    return this;
  }

  /**
   * Add an assistant message
   */
  addAssistantMessage(content) {
    this.messages.push(createAssistantMessage(content));
    return this;
  }

  /**
   * Add a tool call from the assistant
   */
  addToolCall(toolCallId, toolName, toolArguments) {
    const toolCallMessage = this.toolHistory.createToolCall(toolCallId, toolName, toolArguments);
    this.messages.push(toolCallMessage);
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
    this.messages = validateOpenAIMessages(newMessages);
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