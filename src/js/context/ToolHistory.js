/**
 * ToolHistory Module
 * 
 * Specialized handling of tool-related conversation tracking:
 * - Tool call and result message creation
 * - Pending tool result management during streaming
 * - User decision recording with tool-specific context
 */

import { createUserMessage } from '../editor/MessageFormatUtils';
import { createToolCallMessage, createToolResultMessage } from '../editor/MessageFormatUtils';

export class ToolHistory {
  constructor() {
    this.pendingToolResults = new Map();
  }

  /**
   * Create a tool call message
   */
  createToolCall(toolCallId, toolName, toolArguments) {
    return createToolCallMessage(toolCallId, toolName, toolArguments);
  }

  /**
   * Create a tool result message
   */
  createToolResult(toolCallId, toolResult) {
    return createToolResultMessage(toolCallId, toolResult);
  }

  /**
   * Create both tool call and result messages as a pair
   */
  createToolCallAndResult(toolCallId, toolName, toolArguments, toolResult) {
    return [
      this.createToolCall(toolCallId, toolName, toolArguments),
      this.createToolResult(toolCallId, toolResult)
    ];
  }

  /**
   * Track a pending tool result during streaming
   */
  trackPendingResult(toolCallId, toolName, toolArguments, toolResult) {
    this.pendingToolResults.set(toolCallId, {
      name: toolName,
      arguments: toolArguments,
      result: toolResult
    });
    return this;
  }

  /**
   * Get all pending tool results as message pairs
   */
  getPendingResultMessages() {
    const messages = [];
    this.pendingToolResults.forEach((toolData, toolCallId) => {
      const [toolCall, toolResult] = this.createToolCallAndResult(
        toolCallId,
        toolData.name,
        toolData.arguments,
        toolData.result
      );
      messages.push(toolCall, toolResult);
    });
    return messages;
  }

  /**
   * Clear all pending tool results
   */
  clearPendingResults() {
    this.pendingToolResults.clear();
    return this;
  }

  /**
   * Check if there are pending tool results
   */
  hasPendingResults() {
    return this.pendingToolResults.size > 0;
  }

  /**
   * Get pending result count
   */
  getPendingResultCount() {
    return this.pendingToolResults.size;
  }

  /**
   * Create a user decision message with tool-specific context
   */
  createUserDecisionMessage(action, toolData) {
    const actionText = action === 'accepted' ? 'accepted' : 'rejected';
    const toolType = toolData.tool_type || 'tool';
    let decisionMessage = `User ${actionText} the ${toolType} suggestion.`;
    
    if (action === 'accepted') {
      decisionMessage += this.getAcceptanceDetails(toolType, toolData);
    }
    
    return createUserMessage(decisionMessage);
  }

  /**
   * Get tool-specific acceptance details for decision messages
   */
  getAcceptanceDetails(toolType, toolData) {
    switch (toolType) {
      case 'edit_post':
        return ` The text "${toolData.search_pattern}" was changed to "${toolData.replacement_text}" in the post.`;
      case 'insert_content':
        return ` New content was added to the post.`;
      case 'write_to_post':
        return ` The post content was completely replaced.`;
      default:
        return '';
    }
  }

  /**
   * Debug utility - log current tool state
   */
  debug() {
    console.log('ToolHistory Debug:', {
      pendingToolResults: this.pendingToolResults.size,
      pendingTools: Array.from(this.pendingToolResults.keys())
    });
  }
} 