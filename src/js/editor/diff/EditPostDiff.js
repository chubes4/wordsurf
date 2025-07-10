import { BaseDiffHighlight } from './BaseDiffHighlight';
import { createWordPressStyleDiff } from './DiffUtils';

/**
 * Edit Post Diff Handler
 * Handles word-level diff highlighting with WordPress-style visual formatting
 */
export class EditPostDiff extends BaseDiffHighlight {
  constructor(diffData, editorDocument, onAccept, onReject) {
    super(diffData, editorDocument, onAccept, onReject);
    this.toolType = 'edit_post';
  }

  /**
   * Create word-level diff highlighting
   */
  createHighlight() {
    const pattern = this.diffData.search_pattern;
    const textNodes = this.findTextNodesWithPattern(pattern);

    textNodes.forEach(textNode => {
      this.highlightTextNode(textNode, pattern);
    });
  }

  /**
   * Highlight a specific text node with word-level diff
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
    
    // Create diff container with WordPress-style word-level highlighting
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
   * Create the diff container with word-level highlighting
   */
  createDiffContainer(pattern) {
    const diffContainer = this.editorDocument.createElement('span');
    diffContainer.className = 'wordsurf-diff-highlight';
    
    // Create word-level diff highlighting
    const { wordDiffHTML, tooltipHTML } = createWordPressStyleDiff(
      pattern, 
      this.diffData.replacement_text, 
      this.editorDocument
    );
    
    // Clear any existing content and append word diff HTML
    while (diffContainer.firstChild) {
      diffContainer.removeChild(diffContainer.firstChild);
    }
    
    // Append all child nodes from wordDiffHTML
    while (wordDiffHTML.firstChild) {
      diffContainer.appendChild(wordDiffHTML.firstChild);
    }
    
    // Set attributes for tracking and tooltips
    diffContainer.setAttribute('data-diff-tooltip', tooltipHTML);
    diffContainer.setAttribute('data-original-text', pattern);
    diffContainer.setAttribute('data-new-text', this.diffData.replacement_text);
    diffContainer.setAttribute('data-tool-type', this.toolType);
    
    return diffContainer;
  }

  /**
   * Replace highlights with accepted content (EditPost-specific behavior)
   * For edit_post, we actually replace the text content in the editor
   */
  replaceWithAcceptedContent() {
    this.originalElements.forEach(({ wrapper, originalNode }) => {
      if (wrapper.parentNode && originalNode) {
        // For edit_post: replace the original text with the new text
        const newTextNode = originalNode.ownerDocument.createTextNode(this.diffData.replacement_text);
        wrapper.parentNode.replaceChild(newTextNode, wrapper);
      } else if (wrapper.parentNode) {
        // Fallback: just remove the wrapper
        wrapper.parentNode.removeChild(wrapper);
      }
    });
    this.originalElements = [];
  }
} 