// WordPress-style word-level diff utilities

/**
 * Split text into words while preserving whitespace and punctuation
 */
export function tokenizeText(text) {
  // Split on word boundaries but keep delimiters
  return text.split(/(\s+|[.,!?;:"'()[\]{}])/).filter(token => token.length > 0);
}

/**
 * Calculate word-level differences between old and new text
 * Returns array of diff operations: {type: 'equal'|'delete'|'insert', text: string}
 */
export function calculateWordDiff(oldText, newText) {
  const oldTokens = tokenizeText(oldText);
  const newTokens = tokenizeText(newText);
  
  // Simple LCS-based diff algorithm
  const matrix = buildLCSMatrix(oldTokens, newTokens);
  const diff = backtrackLCS(matrix, oldTokens, newTokens);
  
  return diff;
}

/**
 * Build Longest Common Subsequence matrix
 */
function buildLCSMatrix(oldTokens, newTokens) {
  const matrix = Array(oldTokens.length + 1).fill(null).map(() => 
    Array(newTokens.length + 1).fill(0)
  );
  
  for (let i = 1; i <= oldTokens.length; i++) {
    for (let j = 1; j <= newTokens.length; j++) {
      if (oldTokens[i - 1] === newTokens[j - 1]) {
        matrix[i][j] = matrix[i - 1][j - 1] + 1;
      } else {
        matrix[i][j] = Math.max(matrix[i - 1][j], matrix[i][j - 1]);
      }
    }
  }
  
  return matrix;
}

/**
 * Backtrack through LCS matrix to generate diff operations
 */
function backtrackLCS(matrix, oldTokens, newTokens) {
  const diff = [];
  let i = oldTokens.length;
  let j = newTokens.length;
  
  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && oldTokens[i - 1] === newTokens[j - 1]) {
      // Equal - move diagonally
      diff.unshift({ type: 'equal', text: oldTokens[i - 1] });
      i--;
      j--;
    } else if (j > 0 && (i === 0 || matrix[i][j - 1] >= matrix[i - 1][j])) {
      // Insert - move left
      diff.unshift({ type: 'insert', text: newTokens[j - 1] });
      j--;
    } else if (i > 0) {
      // Delete - move up
      diff.unshift({ type: 'delete', text: oldTokens[i - 1] });
      i--;
    }
  }
  
  return diff;
}

/**
 * Merge consecutive operations of the same type
 */
export function mergeDiffOperations(diff) {
  const merged = [];
  let current = null;
  
  for (const op of diff) {
    if (current && current.type === op.type) {
      current.text += op.text;
    } else {
      if (current) merged.push(current);
      current = { ...op };
    }
  }
  
  if (current) merged.push(current);
  return merged;
}



/**
 * Create WordPress-style diff tooltip showing removed (red) and added (green) text
 */
export function createWordPressDiffTooltip(oldText, newText) {
  // For insertions (empty old text), just show what was added
  if (!oldText || oldText.trim() === '') {
    return `ADDED: "${newText}"`;
  }
  
  // For short changes, show simple before/after
  if (oldText.length <= 50 && newText.length <= 50) {
    return `REMOVED: "${oldText}"\nADDED: "${newText}"`;
  }
  
  // For longer text, show word-level diff
  const wordDiff = calculateWordDiff(oldText, newText);
  const merged = mergeDiffOperations(wordDiff);
  
  let tooltipText = '';
  let hasRemovals = false;
  let hasAdditions = false;
  
  // First pass: collect removed text
  for (const op of merged) {
    if (op.type === 'delete') {
      if (!hasRemovals) {
        tooltipText += 'REMOVED: ';
        hasRemovals = true;
      }
      tooltipText += op.text;
    }
  }
  
  if (hasRemovals) tooltipText += '\n';
  
  // Second pass: collect added text  
  for (const op of merged) {
    if (op.type === 'insert') {
      if (!hasAdditions) {
        tooltipText += 'ADDED: ';
        hasAdditions = true;
      }
      tooltipText += op.text;
    }
  }
  
  return tooltipText || `CHANGED: "${oldText}" → "${newText}"`;
}



/**
 * Create WordPress-style diff with word-level highlighting and HTML tooltip
 * Returns both the visual diff HTML and the tooltip HTML
 */
export function createWordPressStyleDiff(oldText, newText, editorDocument) {
  const wordDiff = calculateWordDiff(oldText, newText);
  const merged = mergeDiffOperations(wordDiff);
  
  // Create the visual diff container (shows new text with darker green highlights for changes)
  const wordDiffHTML = editorDocument.createElement('span');
  wordDiffHTML.className = 'wordsurf-wp-diff';
  
  // Build both visual diff and tooltip text
  let tooltipText = 'ORIGINAL TEXT:\n';
  
  for (const op of merged) {
    // For visual diff: show equal and insert operations (the "new" text)
    if (op.type === 'equal' || op.type === 'insert') {
      const span = editorDocument.createElement('span');
      
      if (op.type === 'insert') {
        span.className = 'wordsurf-diff-inserted'; // Darker green highlight
      } else {
        span.className = 'wordsurf-diff-equal'; // No special styling
      }
      
      span.textContent = op.text;
      wordDiffHTML.appendChild(span);
    }
    
    // For tooltip: collect original text with markers for deletions
    if (op.type === 'equal') {
      tooltipText += op.text;
    } else if (op.type === 'delete') {
      tooltipText += `[REMOVED: ${op.text}]`;
    }
  }
  
  return { wordDiffHTML, tooltipHTML: tooltipText };
} 