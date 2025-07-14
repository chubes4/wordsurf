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
        
        // Process each diff block using DiffActions.handleAccept
        for (const diffBlock of diffBlocks) {
            try {
                await DiffActions.handleAccept(
                    diffBlock.attributes,
                    diffBlock.clientId,
                    currentPostId
                );
                console.log('HandleAcceptAll: Accepted diff block', diffBlock.attributes.diffId);
            } catch (error) {
                console.error('HandleAcceptAll: Error accepting diff block', diffBlock.attributes.diffId, error);
            }
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
        
        // Process each diff block using DiffActions.handleReject
        for (const diffBlock of diffBlocks) {
            try {
                await DiffActions.handleReject(
                    diffBlock.attributes,
                    diffBlock.clientId,
                    currentPostId
                );
                console.log('HandleAcceptAll: Rejected diff block', diffBlock.attributes.diffId);
            } catch (error) {
                console.error('HandleAcceptAll: Error rejecting diff block', diffBlock.attributes.diffId, error);
            }
        }
        
        return diffBlocks.length;
    }
}