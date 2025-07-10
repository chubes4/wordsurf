import { EditPostDiff } from './EditPostDiff';
import { InsertContentDiff } from './InsertContentDiff';
import { WriteToPostDiff } from './WriteToPostDiff';

// Tool registry mapping tool types to their handler classes
const TOOL_HANDLERS = {
  'edit_post': EditPostDiff,
  'insert_content': InsertContentDiff,
  'write_to_post': WriteToPostDiff,
};

/**
 * Factory function to create the appropriate diff handler based on tool type
 * @param {Object} diffData - The diff data containing tool_type and other info
 * @param {Document} editorDocument - The editor document to inject highlights into
 * @param {Function} onAccept - Callback when user accepts the diff
 * @param {Function} onReject - Callback when user rejects the diff
 * @returns {BaseDiffHighlight} The appropriate diff handler instance
 */
export function createDiffHandler(diffData, editorDocument, onAccept, onReject) {
  const toolType = diffData.tool_type || 'edit_post';
  const HandlerClass = TOOL_HANDLERS[toolType];
  
  if (!HandlerClass) {
    throw new Error(`Unknown tool type: ${toolType}. Available tools: ${Object.keys(TOOL_HANDLERS).join(', ')}`);
  }
  
  return new HandlerClass(diffData, editorDocument, onAccept, onReject);
}

/**
 * Get list of supported tool types
 * @returns {string[]} Array of supported tool type names
 */
export function getSupportedToolTypes() {
  return Object.keys(TOOL_HANDLERS);
}

/**
 * Check if a tool type is supported
 * @param {string} toolType - The tool type to check
 * @returns {boolean} Whether the tool type is supported
 */
export function isToolTypeSupported(toolType) {
  return toolType in TOOL_HANDLERS;
}

// Export individual tools for direct import if needed
export { EditPostDiff, InsertContentDiff, WriteToPostDiff }; 