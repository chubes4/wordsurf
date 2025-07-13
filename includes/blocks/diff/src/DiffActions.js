import { createBlock } from '@wordpress/blocks';

/**
 * DiffActions Module
 * 
 * Handles all diff-related actions including:
 * - Accept/reject logic
 * - Backend communication
 * - Block replacement
 * - State management
 */

export class DiffActions {
    /**
     * Handle accepting a diff
     */
    static async handleAccept(attributes, clientId, replaceBlock, currentPostId) {
        const { 
            toolCallId, 
            diffId, 
            originalContent, 
            replacementContent, 
            originalBlockContent, 
            originalBlockType, 
            searchPattern 
        } = attributes;
        
        try {
            // Apply the change to the full original block content
            let finalContent = originalBlockContent || '';
            
            if (finalContent && originalContent && replacementContent) {
                const searchText = searchPattern || originalContent;
                if (finalContent.includes(searchText)) {
                    finalContent = finalContent.replace(searchText, replacementContent);
                }
            }
            
            // Create new block with the updated content
            const newBlock = createBlock(originalBlockType || 'core/paragraph', {
                content: finalContent,
            });
            
            replaceBlock(clientId, newBlock);

            // Send acceptance to backend
            await DiffActions.sendUserDecision('accepted', {
                tool_call_id: toolCallId,
                diff_id: diffId,
                post_id: currentPostId,
            });

            return { success: true };

        } catch (error) {
            console.error('Error accepting diff:', error);
            throw error;
        }
    }

    /**
     * Handle rejecting a diff
     */
    static async handleReject(attributes, clientId, replaceBlock, currentPostId) {
        const { toolCallId, diffId, originalBlockContent, originalBlockType } = attributes;
        
        try {
            // For rejections, restore the original block content exactly as it was
            const newBlock = createBlock(originalBlockType || 'core/paragraph', {
                content: originalBlockContent || '',
            });
            
            replaceBlock(clientId, newBlock);

            // Send rejection to backend
            await DiffActions.sendUserDecision('rejected', {
                tool_call_id: toolCallId,
                diff_id: diffId,
                post_id: currentPostId,
            });

            return { success: true };

        } catch (error) {
            console.error('Error rejecting diff:', error);
            throw error;
        }
    }

    /**
     * Send user decision to backend
     */
    static async sendUserDecision(decision, data) {
        const formData = new FormData();
        formData.append('action', 'wordsurf_user_feedback');
        formData.append('nonce', wp.ajax.settings.nonce);
        formData.append('user_action', decision);
        formData.append('tool_call_id', data.tool_call_id);
        formData.append('diff_id', data.diff_id);
        formData.append('post_id', data.post_id);

        const response = await fetch(wp.ajax.settings.url, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error('Failed to send user decision');
        }

        return response;
    }

    /**
     * Create action handlers with proper context
     */
    static createActionHandlers(attributes, clientId, replaceBlock, currentPostId, setIsProcessing, setAttributes) {
        const handleAccept = async () => {
            setIsProcessing(true);
            try {
                setAttributes({ status: 'accepted' });
                await DiffActions.handleAccept(attributes, clientId, replaceBlock, currentPostId);
            } catch (error) {
                setAttributes({ status: 'pending' });
                throw error;
            } finally {
                setIsProcessing(false);
            }
        };

        const handleReject = async () => {
            setIsProcessing(true);
            try {
                setAttributes({ status: 'rejected' });
                await DiffActions.handleReject(attributes, clientId, replaceBlock, currentPostId);
            } catch (error) {
                setAttributes({ status: 'pending' });
                throw error;
            } finally {
                setIsProcessing(false);
            }
        };

        return { handleAccept, handleReject };
    }
}