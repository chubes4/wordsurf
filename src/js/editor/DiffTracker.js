/**
 * DiffTracker - Centralized diff state management and continuation triggering
 * 
 * This module decouples diff acceptance/rejection from continuation logic.
 * It tracks the total number of diff blocks and triggers continuation events
 * when all diffs have been resolved (accepted or rejected).
 */

export class DiffTracker {
    constructor() {
        this.activeDiffs = new Set();
        this.totalDiffsForCurrentTool = 0;
        this.currentToolCallId = null;
        this.isTrackingBulkOperation = false;
    }

    /**
     * Initialize tracking for a new set of diff blocks
     * Called when tool results create new diffs
     */
    startTracking(toolCallId, diffIds) {
        console.log('DiffTracker: Starting tracking for tool:', toolCallId, 'with diffs:', diffIds);
        
        this.currentToolCallId = toolCallId;
        this.activeDiffs.clear();
        
        // Add all diff IDs to active tracking
        diffIds.forEach(diffId => this.activeDiffs.add(diffId));
        this.totalDiffsForCurrentTool = diffIds.length;
        
        console.log('DiffTracker: Now tracking', this.totalDiffsForCurrentTool, 'active diffs');
    }

    /**
     * Mark a diff as resolved (accepted or rejected)
     */
    markDiffResolved(diffId, action, postId) {
        if (!this.activeDiffs.has(diffId)) {
            console.log('DiffTracker: Diff', diffId, 'not in active tracking, ignoring');
            return;
        }

        console.log('DiffTracker: Marking diff as resolved:', diffId, 'action:', action);
        this.activeDiffs.delete(diffId);
        
        const remainingDiffs = this.activeDiffs.size;
        console.log('DiffTracker: Remaining active diffs:', remainingDiffs);

        // If no more active diffs, trigger continuation
        if (remainingDiffs === 0 && this.currentToolCallId) {
            console.log('DiffTracker: All diffs resolved, triggering continuation');
            this.triggerContinuation(action, postId);
        }
    }

    /**
     * Start bulk operation tracking
     * This suppresses individual continuation events
     */
    startBulkOperation() {
        console.log('DiffTracker: Starting bulk operation');
        this.isTrackingBulkOperation = true;
    }

    /**
     * End bulk operation tracking
     * This triggers continuation if all diffs are resolved
     */
    endBulkOperation(action, postId) {
        console.log('DiffTracker: Ending bulk operation');
        this.isTrackingBulkOperation = false;
        
        // If all diffs are resolved, trigger continuation
        if (this.activeDiffs.size === 0 && this.currentToolCallId) {
            console.log('DiffTracker: Bulk operation complete, triggering continuation');
            this.triggerContinuation(action, postId);
        }
    }

    /**
     * Check if we're currently tracking a bulk operation
     */
    isBulkOperation() {
        return this.isTrackingBulkOperation;
    }

    /**
     * Get current tracking status
     */
    getStatus() {
        return {
            activeDiffs: Array.from(this.activeDiffs),
            totalDiffs: this.totalDiffsForCurrentTool,
            currentToolCallId: this.currentToolCallId,
            isBulkOperation: this.isTrackingBulkOperation,
            allResolved: this.activeDiffs.size === 0
        };
    }

    /**
     * Trigger continuation event
     */
    triggerContinuation(action, postId) {
        if (!this.currentToolCallId) {
            console.log('DiffTracker: No current tool call ID, skipping continuation');
            return;
        }

        console.log('DiffTracker: Triggering continuation for tool:', this.currentToolCallId);
        
        document.dispatchEvent(new CustomEvent('wordsurf-continue-chat', {
            detail: {
                trigger: 'diff_resolved',
                toolResult: {
                    action: action,
                    toolCallId: this.currentToolCallId,
                    postId: postId,
                    totalDiffs: this.totalDiffsForCurrentTool,
                    timestamp: Date.now()
                }
            }
        }));

        // Reset tracking for next tool
        this.reset();
    }

    /**
     * Reset tracking state
     */
    reset() {
        console.log('DiffTracker: Resetting tracking state');
        this.activeDiffs.clear();
        this.totalDiffsForCurrentTool = 0;
        this.currentToolCallId = null;
        this.isTrackingBulkOperation = false;
    }

    /**
     * Get singleton instance
     */
    static getInstance() {
        if (!DiffTracker.instance) {
            DiffTracker.instance = new DiffTracker();
        }
        return DiffTracker.instance;
    }
}

// Export singleton instance
export const diffTracker = DiffTracker.getInstance();