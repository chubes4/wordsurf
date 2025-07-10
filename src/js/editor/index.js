import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import WordsurfChatStream from './WordsurfChatStream';
import InlineDiffHighlight from './InlineDiffHighlight';

const PLUGIN_NAME = 'wordsurf';
const PLUGIN_TITLE = __('Wordsurf', 'wordsurf');

// A simple SVG for the icon, inspired by modern AI interfaces.
const PluginIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="currentColor"/>
        <path d="M12 12m-6 0a6 6 0 1 0 12 0a6 6 0 1 0 -12 0" fill="currentColor" fill-opacity="0.3"/>
    </svg>
);

const WordsurfSidebar = () => {
    const [pendingDiffs, setPendingDiffs] = useState([]);
    const dispatch = useDispatch();
    
    // Get current post data
    const postData = useSelect(select => {
        const { getCurrentPostId, getEditedPostAttribute } = select('core/editor');
        const postId = getCurrentPostId();
        return {
            postId,
            title: getEditedPostAttribute('title'),
            content: getEditedPostAttribute('content'),
            status: getEditedPostAttribute('status'),
            type: getEditedPostAttribute('type'),
        };
    });

    // Handle accepting diff changes
    const handleAcceptDiff = (diffData) => {
        if (!diffData) return;

        // Handle different types of changes
        const changeType = diffData.content_type || diffData.edit_type || 'content';
        
        // Apply changes to Gutenberg editor based on change type
        // Instead of replacing entire content, apply the specific change to current content
        if (changeType === 'content') {
            // Get current editor content to ensure we're working with latest state
            const currentContent = postData.content;
            
            // Apply the same search/replace operation that was previewed
            const searchPattern = diffData.search_pattern;
            const replacementText = diffData.replacement_text;
            const caseSensitive = diffData.case_sensitive || false;
            
            if (searchPattern && replacementText !== undefined) {
                // Escape the search pattern for regex use
                const escapedPattern = searchPattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const flags = caseSensitive ? 'g' : 'gi';
                const regex = new RegExp(escapedPattern, flags);
                
                // Apply the replacement to current content
                const updatedContent = currentContent.replace(regex, replacementText);
                
                // Only update if content actually changed
                if (updatedContent !== currentContent) {
                    dispatch('core/editor').editPost({ content: updatedContent });
                } else {
                    // Pattern not found in current content - show warning
                    dispatch('core/notices').createWarningNotice(
                        `⚠️ Could not apply change - the target text may have been modified. Please try the edit again.`,
                        { type: 'snackbar', isDismissible: true }
                    );
                    setPendingDiffs(prev => prev.filter(diff => diff !== diffData));
                    return;
                }
            } else {
                // Fallback to PHP-generated content if no search pattern available
                dispatch('core/editor').editPost({ content: diffData.new_content });
            }
        } else if (changeType === 'title') {
            // For title changes, get current title and apply the change
            const currentTitle = postData.title;
            const searchPattern = diffData.search_pattern;
            const replacementText = diffData.replacement_text;
            
            if (searchPattern && replacementText !== undefined) {
                const escapedPattern = searchPattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const flags = diffData.case_sensitive ? 'g' : 'gi';
                const regex = new RegExp(escapedPattern, flags);
                const updatedTitle = currentTitle.replace(regex, replacementText);
                dispatch('core/editor').editPost({ title: updatedTitle });
            } else {
                dispatch('core/editor').editPost({ title: diffData.new_content });
            }
        } else if (changeType === 'excerpt') {
            // For excerpt changes, get current excerpt and apply the change
            const currentExcerpt = postData.excerpt || '';
            const searchPattern = diffData.search_pattern;
            const replacementText = diffData.replacement_text;
            
            if (searchPattern && replacementText !== undefined) {
                const escapedPattern = searchPattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const flags = diffData.case_sensitive ? 'g' : 'gi';
                const regex = new RegExp(escapedPattern, flags);
                const updatedExcerpt = currentExcerpt.replace(regex, replacementText);
                dispatch('core/editor').editPost({ excerpt: updatedExcerpt });
            } else {
                dispatch('core/editor').editPost({ excerpt: diffData.new_content });
            }
        }

        // Show success notice with appropriate message
        const actionType = diffData.inserted_content ? 'inserted' : 'updated';
        const location = diffData.insertion_point ? ` ${diffData.insertion_point}` : '';
        
        dispatch('core/notices').createSuccessNotice(
            `✅ Changes applied! Content ${actionType}${location} successfully.`,
            { type: 'snackbar', isDismissible: true }
        );

        // Send feedback to the AI that changes were accepted
        sendAIFeedback('accepted', diffData);

        // Remove this specific diff from pending diffs
        setPendingDiffs(prev => prev.filter(diff => diff !== diffData));
    };

    // Handle rejecting diff changes
    const handleRejectDiff = (diffData) => {
        // Show rejection notice
        dispatch('core/notices').createInfoNotice(
            `❌ Changes rejected. No modifications were made to the ${diffData.edit_type}.`,
            { type: 'snackbar', isDismissible: true }
        );

        // Send feedback to the AI that changes were rejected
        sendAIFeedback('rejected', diffData);

        // Remove this specific diff from pending diffs
        setPendingDiffs(prev => prev.filter(diff => diff !== diffData));
    };

    const handleSend = (message) => {
        // Optional: Add any side effects when a message is sent
        console.log('Message sent:', message);
    };

    const handleDiffReceived = (diffData) => {
        // Add diff to pending diffs array instead of overwriting
        setPendingDiffs(prev => [...prev, diffData]);
    };

    // Send feedback to AI about user's decision on changes
    const sendAIFeedback = async (action, diffData) => {
        try {
            const formData = new FormData();
            formData.append('action', 'wordsurf_user_feedback');
            formData.append('user_action', action); // 'accepted' or 'rejected'
            formData.append('tool_type', diffData.tool_type || 'edit_post');
            formData.append('post_id', postData.postId);
            formData.append('nonce', window.wordsurfData?.nonce || '');
            
            // Include comprehensive tool data for better context tracking
            const toolData = {
                search_pattern: diffData.search_pattern,
                replacement_text: diffData.replacement_text,
                edit_type: diffData.edit_type,
                original_content: diffData.original_content,
                new_content: diffData.new_content,
                case_sensitive: diffData.case_sensitive,
                insertion_point: diffData.insertion_point,
                inserted_content: diffData.inserted_content,
                content_type: diffData.content_type,
                message: diffData.message
            };
            
            formData.append('tool_data', JSON.stringify(toolData));

            await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData,
            });
            
            console.log(`Wordsurf DEBUG: Sent ${action} feedback for ${diffData.tool_type || 'edit_post'} tool`);
        } catch (error) {
            console.error('Failed to send AI feedback:', error);
        }
    };

    return (
        <>
            <PluginSidebarMoreMenuItem target={PLUGIN_NAME}>
                {PLUGIN_TITLE}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name={PLUGIN_NAME}
                title={PLUGIN_TITLE}
                icon={<PluginIcon />}
            >
                <WordsurfChatStream 
                    postId={postData.postId}
                    onSend={handleSend}
                    onDiffReceived={handleDiffReceived}
                />
            </PluginSidebar>
            
            {pendingDiffs.map((diffData, index) => (
                <InlineDiffHighlight
                    key={index}
                    diffData={diffData}
                    onAccept={handleAcceptDiff}
                    onReject={handleRejectDiff}
                    onClose={() => setPendingDiffs(prev => prev.filter(diff => diff !== diffData))}
                />
            ))}
        </>
    );
};

registerPlugin(PLUGIN_NAME, {
    render: WordsurfSidebar,
}); 