/**
 * Base class for diff highlighting tools
 * Contains shared functionality for all diff types
 */
export class BaseDiffHighlight {
  constructor(diffData, editorDocument, onAccept, onReject) {
    this.diffData = diffData;
    this.editorDocument = editorDocument;
    this.onAccept = onAccept;
    this.onReject = onReject;
    this.originalElements = [];
    this.toolType = 'base'; // Override in subclasses
  }

  /**
   * Main method to create and inject the diff highlight
   * Must be implemented by subclasses
   */
  createHighlight() {
    throw new Error('createHighlight() must be implemented by subclasses');
  }

  /**
   * Handle accepting the diff
   * Can be overridden by subclasses for tool-specific behavior
   */
  onAcceptDiff() {
    this.replaceWithAcceptedContent();
    this.onAccept(this.diffData);
  }

  /**
   * Handle rejecting the diff
   * Can be overridden by subclasses for tool-specific behavior
   */
  onRejectDiff() {
    this.cleanup();
    this.onReject(this.diffData);
  }

  /**
   * Create action buttons (accept/reject)
   * Shared across all tools
   */
  createActionButtons() {
    const actionsContainer = this.editorDocument.createElement('span');
    actionsContainer.className = 'wordsurf-diff-actions';
    
    // Create accept button
    const acceptBtn = this.editorDocument.createElement('button');
    acceptBtn.className = 'wordsurf-diff-btn wordsurf-diff-accept';
    acceptBtn.innerHTML = '✓';
    acceptBtn.title = this.getAcceptButtonTitle();
    acceptBtn.onclick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.onAcceptDiff();
    };
    
    // Create reject button
    const rejectBtn = this.editorDocument.createElement('button');
    rejectBtn.className = 'wordsurf-diff-btn wordsurf-diff-reject';
    rejectBtn.innerHTML = '✗';
    rejectBtn.title = this.getRejectButtonTitle();
    rejectBtn.onclick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.onRejectDiff();
    };
    
    actionsContainer.appendChild(acceptBtn);
    actionsContainer.appendChild(rejectBtn);
    
    return actionsContainer;
  }

  /**
   * Get accept button title - can be overridden by subclasses
   */
  getAcceptButtonTitle() {
    const actionMap = {
      'edit_post': 'Accept change',
      'insert_content': 'Accept insertion',
      'write_to_post': 'Accept replacement'
    };
    return actionMap[this.toolType] || 'Accept';
  }

  /**
   * Get reject button title - can be overridden by subclasses
   */
  getRejectButtonTitle() {
    const actionMap = {
      'edit_post': 'Reject change',
      'insert_content': 'Reject insertion', 
      'write_to_post': 'Reject replacement'
    };
    return actionMap[this.toolType] || 'Reject';
  }

  /**
   * Clean up highlights and restore original content
   */
  cleanup() {
    this.originalElements.forEach(({ wrapper, originalNode }) => {
      if (wrapper.parentNode) {
        if (originalNode) {
          wrapper.parentNode.replaceChild(originalNode, wrapper);
        } else {
          wrapper.parentNode.removeChild(wrapper);
        }
      }
    });
    this.originalElements = [];
  }

  /**
   * Replace highlights with accepted content
   * Default implementation - can be overridden by subclasses
   */
  replaceWithAcceptedContent() {
    this.originalElements.forEach(({ wrapper, originalNode }) => {
      if (wrapper.parentNode) {
        if (originalNode) {
          // Default: restore original text node (backend handles the actual update)
          wrapper.parentNode.replaceChild(originalNode, wrapper);
        } else {
          // Handle overlay removal
          wrapper.parentNode.removeChild(wrapper);
        }
      }
    });
    this.originalElements = [];
  }

  /**
   * Add element to tracking for cleanup
   */
  trackElement(wrapper, originalNode = null) {
    this.originalElements.push({ wrapper, originalNode });
  }

  /**
   * Utility to find text nodes containing a pattern
   */
  findTextNodesWithPattern(pattern) {
    const walker = this.editorDocument.createTreeWalker(
      this.editorDocument.body,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode: function(node) {
          const parent = node.parentElement;
          if (!parent) return NodeFilter.FILTER_REJECT;
          if (['SCRIPT', 'STYLE'].includes(parent.tagName)) return NodeFilter.FILTER_REJECT;
          if (parent.classList.contains('wordsurf-diff-highlight')) return NodeFilter.FILTER_REJECT;
          
          return node.textContent.includes(pattern) 
            ? NodeFilter.FILTER_ACCEPT 
            : NodeFilter.FILTER_REJECT;
        }
      }
    );

    const textNodes = [];
    let node;
    while (node = walker.nextNode()) {
      textNodes.push(node);
    }
    
    return textNodes;
  }
} 