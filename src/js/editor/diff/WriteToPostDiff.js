import { BaseDiffHighlight } from './BaseDiffHighlight';
import { createWordPressDiffTooltip } from './DiffUtils';

/**
 * Write To Post Diff Handler
 * Handles simple text replacement highlighting
 */
export class WriteToPostDiff extends BaseDiffHighlight {
  constructor(diffData, editorDocument, onAccept, onReject) {
    super(diffData, editorDocument, onAccept, onReject);
    this.toolType = 'write_to_post';
  }

  /**
   * Create simple replacement highlighting
   */
  createHighlight() {
    const pattern = this.diffData.search_pattern;
    const textNodes = this.findTextNodesWithPattern(pattern);

    textNodes.forEach(textNode => {
      this.highlightTextNode(textNode, pattern);
    });
  }

  /**
   * Highlight a specific text node with simple replacement
   */
  highlightTextNode(textNode, pattern) {
    const parent = textNode.parentElement;
    const text = textNode.textContent;
    
    if (!text.includes(pattern)) return;

    const beforeText = text.substring(0, text.indexOf(pattern));
    const afterText = text.substring(text.indexOf(pattern) + pattern.length);
    
    // Create wrapper span
    const wrapper = this.editorDocument.createElement('span');
    wrapper.className = 'wordsurf-diff-wrapper';
    
    // Add before text
    if (beforeText) {
      wrapper.appendChild(this.editorDocument.createTextNode(beforeText));
    }
    
    // Create diff container with simple text replacement
    const diffContainer = this.createDiffContainer(pattern);
    wrapper.appendChild(diffContainer);
    
    // Add action buttons
    const actionsContainer = this.createActionButtons();
    wrapper.appendChild(actionsContainer);
    
    // Add after text
    if (afterText) {
      wrapper.appendChild(this.editorDocument.createTextNode(afterText));
    }
    
    // Replace the original text node
    parent.replaceChild(wrapper, textNode);
    this.trackElement(wrapper, textNode);
  }

  /**
   * Create the diff container with simple text highlighting
   */
  createDiffContainer(pattern) {
    const diffContainer = this.editorDocument.createElement('span');
    diffContainer.className = 'wordsurf-diff-highlight';
    
    // For write_to_post, use simple text replacement
    diffContainer.textContent = this.diffData.replacement_text;
    
    // Create simple tooltip
    const tooltip = createWordPressDiffTooltip(pattern, this.diffData.replacement_text);
    diffContainer.setAttribute('data-diff-tooltip', tooltip);
    
    // Set attributes for tracking
    diffContainer.setAttribute('data-original-text', pattern);
    diffContainer.setAttribute('data-new-text', this.diffData.replacement_text);
    diffContainer.setAttribute('data-tool-type', this.toolType);
    
    return diffContainer;
  }

  /**
   * Override button titles for replacement-specific language
   */
  getAcceptButtonTitle() {
    return 'Accept replacement';
  }

  getRejectButtonTitle() {
    return 'Reject replacement';
  }
} 