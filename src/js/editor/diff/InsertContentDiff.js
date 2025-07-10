import { BaseDiffHighlight } from './BaseDiffHighlight';

/**
 * Insert Content Diff Handler
 * Handles position-based content insertion with preview styling
 */
export class InsertContentDiff extends BaseDiffHighlight {
  constructor(diffData, editorDocument, onAccept, onReject) {
    super(diffData, editorDocument, onAccept, onReject);
    this.toolType = 'insert_content';
  }

  /**
   * Create insertion preview at the correct position
   */
  createHighlight() {
    const insertionPoint = this.findInsertionPoint();
    
    if (insertionPoint) {
      this.createInsertionPreview(insertionPoint);
    }
  }

  /**
   * Find the insertion point based on position and target text
   */
  findInsertionPoint() {
    const position = this.diffData.position;
    const targetText = this.diffData.target_paragraph_text;
    
    if (position === 'beginning') {
      return this.editorDocument.body.firstChild;
    } 
    
    if (position === 'end') {
      return this.editorDocument.body.lastChild;
    } 
    
    if (position === 'after_paragraph' && targetText) {
      return this.findParagraphWithText(targetText);
    }
    
    return null;
  }

  /**
   * Find paragraph element containing the target text
   */
  findParagraphWithText(targetText) {
    const walker = this.editorDocument.createTreeWalker(
      this.editorDocument.body,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode: function(node) {
          return node.textContent.includes(targetText) 
            ? NodeFilter.FILTER_ACCEPT 
            : NodeFilter.FILTER_REJECT;
        }
      }
    );
    
    let targetNode = walker.nextNode();
    if (targetNode) {
      // Find the paragraph element containing this text
      let paragraph = targetNode.parentElement;
      while (paragraph && paragraph.tagName !== 'P') {
        paragraph = paragraph.parentElement;
      }
      return paragraph;
    }
    
    return null;
  }

  /**
   * Create the insertion preview with distinctive styling
   */
  createInsertionPreview(insertionPoint) {
    const position = this.diffData.position;
    const newContent = this.diffData.replacement_text;
    
    // Create the insertion highlight container
    const insertionHighlight = this.editorDocument.createElement('div');
    insertionHighlight.className = 'wordsurf-diff-wrapper wordsurf-insertion-preview';
    insertionHighlight.style.cssText = `
      margin: 10px 0;
      padding: 10px;
      border: 2px dashed #34d058;
      background: rgba(52, 208, 88, 0.1);
      border-radius: 4px;
    `;
    
    // Create diff container
    const diffContainer = this.createDiffContainer(newContent);
    insertionHighlight.appendChild(diffContainer);
    
    // Add action buttons
    const actionsContainer = this.createActionButtons();
    insertionHighlight.appendChild(actionsContainer);
    
    // Insert the preview at the correct position
    this.insertAtPosition(insertionHighlight, insertionPoint, position);
    
    this.trackElement(insertionHighlight, null);
  }

  /**
   * Create the diff container for insertion preview
   */
  createDiffContainer(newContent) {
    const diffContainer = this.editorDocument.createElement('span');
    diffContainer.className = 'wordsurf-diff-highlight';
    diffContainer.textContent = newContent;
    diffContainer.setAttribute('data-new-text', newContent);
    diffContainer.setAttribute('data-tool-type', this.toolType);
    diffContainer.title = `NEW CONTENT: "${newContent}"`;
    
    return diffContainer;
  }

  /**
   * Insert the preview at the specified position
   */
  insertAtPosition(insertionHighlight, insertionPoint, position) {
    if (position === 'beginning') {
      insertionPoint.parentNode.insertBefore(insertionHighlight, insertionPoint);
    } else if (position === 'end') {
      insertionPoint.parentNode.appendChild(insertionHighlight);
    } else if (position === 'after_paragraph') {
      insertionPoint.parentNode.insertBefore(insertionHighlight, insertionPoint.nextSibling);
    }
  }

  /**
   * Override button titles for insertion-specific language
   */
  getAcceptButtonTitle() {
    return 'Accept insertion';
  }

  getRejectButtonTitle() {
    return 'Reject insertion';
  }
} 