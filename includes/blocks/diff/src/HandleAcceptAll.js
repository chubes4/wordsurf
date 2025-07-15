import { DiffActions } from './DiffActions';
import { FindDiffBlocks } from './FindDiffBlocks';

/**
 * HandleAcceptAll - Clean bridge for bulk diff operations
 * 
 * Provides clean interface between chat UI and individual diff block handlers
 */
export class HandleAcceptAll {
    /**
     * Accept all diff blocks in the editor
     */
    static async acceptAll() {
        const { getCurrentPostId } = wp.data.select('core/editor');
        
        const diffBlocks = FindDiffBlocks.findAllDiffBlocks();
        const currentPostId = getCurrentPostId();
        
        console.log('HandleAcceptAll: Processing', diffBlocks.length, 'diff blocks');
        
        let lastToolCallId = null;
        let lastDiffId = null;
        
        // Process each diff block using DiffActions.handleAccept
        // BUT suppress chat continuation until all are processed
        for (let i = 0; i < diffBlocks.length; i++) {
            const diffBlock = diffBlocks[i];
            const isLastBlock = i === diffBlocks.length - 1;
            
            try {
                // Store the last tool call ID for continuation
                lastToolCallId = diffBlock.attributes.toolCallId;
                lastDiffId = diffBlock.attributes.diffId;
                
                await DiffActions.handleAccept(
                    diffBlock.attributes,
                    diffBlock.clientId,
                    currentPostId,
                    !isLastBlock // suppressContinuation = true for all but last
                );
                console.log('HandleAcceptAll: Accepted diff block', diffBlock.attributes.diffId);
            } catch (error) {
                console.error('HandleAcceptAll: Error accepting diff block', diffBlock.attributes.diffId, error);
            }
        }
        
        // If we processed blocks and suppressed continuation, trigger it now
        if (diffBlocks.length > 1 && lastToolCallId) {
            DiffActions.triggerChatContinuation({
                action: 'accepted',
                toolCallId: lastToolCallId,
                diffId: lastDiffId,
                postId: currentPostId
            });
        }
        
        return diffBlocks.length;
    }
    
    /**
     * Reject all diff blocks in the editor
     */
    static async rejectAll() {
        const { getCurrentPostId } = wp.data.select('core/editor');
        
        const diffBlocks = FindDiffBlocks.findAllDiffBlocks();
        const currentPostId = getCurrentPostId();
        
        console.log('HandleAcceptAll: Rejecting', diffBlocks.length, 'diff blocks');
        
        let lastToolCallId = null;
        let lastDiffId = null;
        
        // Process each diff block using DiffActions.handleReject
        // BUT suppress chat continuation until all are processed
        for (let i = 0; i < diffBlocks.length; i++) {
            const diffBlock = diffBlocks[i];
            const isLastBlock = i === diffBlocks.length - 1;
            
            try {
                // Store the last tool call ID for continuation
                lastToolCallId = diffBlock.attributes.toolCallId;
                lastDiffId = diffBlock.attributes.diffId;
                
                await DiffActions.handleReject(
                    diffBlock.attributes,
                    diffBlock.clientId,
                    currentPostId,
                    !isLastBlock // suppressContinuation = true for all but last
                );
                console.log('HandleAcceptAll: Rejected diff block', diffBlock.attributes.diffId);
            } catch (error) {
                console.error('HandleAcceptAll: Error rejecting diff block', diffBlock.attributes.diffId, error);
            }
        }
        
        // If we processed blocks and suppressed continuation, trigger it now
        if (diffBlocks.length > 1 && lastToolCallId) {
            DiffActions.triggerChatContinuation({
                action: 'rejected',
                toolCallId: lastToolCallId,
                diffId: lastDiffId,
                postId: currentPostId
            });
        }
        
        return diffBlocks.length;
    }
}