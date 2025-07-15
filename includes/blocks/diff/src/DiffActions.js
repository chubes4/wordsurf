import { ContentUpdater } from './ContentUpdater';
import { FindDiffBlocks } from './FindDiffBlocks';

/**
 * DiffActions Module
 * 
 * Handles all diff-related actions including:
 * - Accept/reject logic
 * - Backend communication
 * - Coordinating content updates
 * - State management
 */

export class DiffActions {
    /**
     * Handle accepting a diff
     */
    static async handleAccept(attributes, clientId, currentPostId, suppressContinuation = false) {
        const { toolCallId, diffId } = attributes;
        
        try {
            // Remove the diff wrapper and apply the accepted changes
            ContentUpdater.removeDiffWrapper(clientId, true);

            // Send acceptance signal to backend
            const response = await DiffActions.sendUserDecision('accepted', {
                tool_call_id: toolCallId,
                diff_id: diffId,
                post_id: currentPostId,
            });

            // Check if backend indicates we should continue the chat
            const responseData = await response.json();
            if (responseData.continue_chat && !suppressContinuation) {
                // Check if there are any remaining pending diff blocks for this diff ID
                const remainingPendingBlocks = FindDiffBlocks.findDiffBlocksByDiffId(diffId)
                    .filter(block => block.attributes?.status === 'pending');
                
                // Only trigger chat continuation if no pending blocks remain for this diff ID
                if (remainingPendingBlocks.length === 0) {
                    // Use the original tool call ID from the backend response if available
                    const originalToolCallId = responseData.tool_result?.tool_call_id || toolCallId;
                    DiffActions.triggerChatContinuation({
                        action: 'accepted',
                        toolCallId: originalToolCallId,
                        diffId,
                        postId: currentPostId
                    });
                }
            }

            return { success: true };

        } catch (error) {
            console.error('Error accepting diff:', error);
            throw error;
        }
    }

    /**
     * Handle rejecting a diff
     */
    static async handleReject(attributes, clientId, currentPostId, suppressContinuation = false) {
        const { toolCallId, diffId } = attributes;
        
        try {
            // Remove the diff wrapper and restore original content
            ContentUpdater.removeDiffWrapper(clientId, false);

            // Send rejection signal to backend
            const response = await DiffActions.sendUserDecision('rejected', {
                tool_call_id: toolCallId,
                diff_id: diffId,
                post_id: currentPostId,
            });

            // Check if backend indicates we should continue the chat
            const responseData = await response.json();
            if (responseData.continue_chat && !suppressContinuation) {
                // Check if there are any remaining pending diff blocks for this diff ID
                const remainingPendingBlocks = FindDiffBlocks.findDiffBlocksByDiffId(diffId)
                    .filter(block => block.attributes?.status === 'pending');
                
                // Only trigger chat continuation if no pending blocks remain for this diff ID
                if (remainingPendingBlocks.length === 0) {
                    // Use the original tool call ID from the backend response if available
                    const originalToolCallId = responseData.tool_result?.tool_call_id || toolCallId;
                    DiffActions.triggerChatContinuation({
                        action: 'rejected',
                        toolCallId: originalToolCallId,
                        diffId,
                        postId: currentPostId
                    });
                }
            }

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
        formData.append('nonce', window.wordsurfData?.nonce || '');
        formData.append('user_action', decision);
        formData.append('tool_call_id', data.tool_call_id);
        formData.append('diff_id', data.diff_id);
        formData.append('post_id', data.post_id);

        const response = await fetch(window.wordsurfData?.ajax_url || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error('Failed to send user decision');
        }

        return response;
    }

    /**
     * Trigger chat continuation after diff acceptance/rejection
     */
    static triggerChatContinuation(toolResultData) {
        // Dispatch a custom event that the chat interface can listen for
        const event = new CustomEvent('wordsurf-continue-chat', {
            bubbles: true,
            detail: {
                trigger: 'diff_resolved',
                timestamp: Date.now(),
                toolResult: toolResultData
            }
        });
        
        document.dispatchEvent(event);
        console.log('DiffActions: Chat continuation event dispatched with tool result:', toolResultData);
    }

    /**
     * Create action handlers with proper context
     */
    static createActionHandlers(attributes, clientId, currentPostId, setIsProcessing, setAttributes) {
        const handleAccept = async () => {
            setIsProcessing(true);
            try {
                setAttributes({ status: 'accepted' });
                await DiffActions.handleAccept(attributes, clientId, currentPostId);
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
                await DiffActions.handleReject(attributes, clientId, currentPostId);
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