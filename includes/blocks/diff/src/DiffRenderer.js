/**
 * DiffRenderer Module
 * 
 * Handles rendering logic for diff blocks, including:
 * - Creating inline diff HTML
 * - Preserving unchanged content
 * - Applying diff styling
 */

export class DiffRenderer {
    /**
     * Create inline diff content with proper styling
     * 
     * @param {Object} attributes - Block attributes
     * @returns {string} HTML content with inline diffs applied
     */
    static createWrappedContent(attributes) {
        const {
            diffId,
            diffType,
            originalContent,
            replacementContent,
            originalBlockContent,
            searchPattern,
        } = attributes;

        // Start with the original block content - preserve everything unchanged
        let content = originalBlockContent || '';
        
        if (diffType === 'edit' && originalContent && replacementContent) {
            // Use the search pattern if available, otherwise fall back to original content
            const searchText = searchPattern || originalContent;
            
            // Only replace the specific text that changed, preserve everything else
            if (content.includes(searchText)) {
                const diffHtml = DiffRenderer.createEditDiffHtml(diffId, originalContent, replacementContent);
                content = content.replace(searchText, diffHtml);
            }
        } else if (diffType === 'insert' && replacementContent) {
            const diffHtml = DiffRenderer.createInsertDiffHtml(diffId, replacementContent);
            
            if (searchPattern && content.includes(searchPattern)) {
                // Insert after the search pattern
                content = content.replace(searchPattern, searchPattern + ' ' + diffHtml);
            } else {
                // Insert at the end
                content = content + ' ' + diffHtml;
            }
        } else if (diffType === 'delete' && originalContent) {
            // For deletions, replace the text to be deleted with diff highlighting
            const searchText = searchPattern || originalContent;
            
            if (content.includes(searchText)) {
                const diffHtml = DiffRenderer.createDeleteDiffHtml(diffId, originalContent);
                content = content.replace(searchText, diffHtml);
            }
        }
        
        return content;
    }

    /**
     * Create HTML for edit diff (deletion + insertion)
     */
    static createEditDiffHtml(diffId, originalContent, replacementContent) {
        return `<span class="wordsurf-diff-container" data-diff-id="${diffId}">` +
               `<del class="wordsurf-diff-removed">${originalContent}</del>` +
               `<ins class="wordsurf-diff-added">${replacementContent}</ins>` +
               `<span class="wordsurf-diff-controls">` +
               `<button class="wordsurf-accept-btn" data-diff-id="${diffId}">✓</button>` +
               `<button class="wordsurf-reject-btn" data-diff-id="${diffId}">✗</button>` +
               `</span></span>`;
    }

    /**
     * Create HTML for insert diff
     */
    static createInsertDiffHtml(diffId, replacementContent) {
        return `<span class="wordsurf-diff-container" data-diff-id="${diffId}">` +
               `<ins class="wordsurf-diff-added">${replacementContent}</ins>` +
               `<span class="wordsurf-diff-controls">` +
               `<button class="wordsurf-accept-btn" data-diff-id="${diffId}">✓</button>` +
               `<button class="wordsurf-reject-btn" data-diff-id="${diffId}">✗</button>` +
               `</span></span>`;
    }

    /**
     * Create HTML for delete diff
     */
    static createDeleteDiffHtml(diffId, originalContent) {
        return `<span class="wordsurf-diff-container" data-diff-id="${diffId}">` +
               `<del class="wordsurf-diff-removed">${originalContent}</del>` +
               `<span class="wordsurf-diff-controls">` +
               `<button class="wordsurf-accept-btn" data-diff-id="${diffId}">✓</button>` +
               `<button class="wordsurf-reject-btn" data-diff-id="${diffId}">✗</button>` +
               `</span></span>`;
    }

    /**
     * Validate if diff can be applied to content
     */
    static canApplyDiff(content, searchPattern, originalContent) {
        const searchText = searchPattern || originalContent;
        return content && searchText && content.includes(searchText);
    }
}