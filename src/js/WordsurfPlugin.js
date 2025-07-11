import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { useState, useRef, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

// New architecture imports
import { useChatHandler } from './agent/core/ChatHandler';
import { ChatHistory } from './context/ChatHistory';
import { InlineDiffManager } from './editor/InlineDiffManager';
import { ChatInterface } from './editor/UIComponents';

const PLUGIN_NAME = 'wordsurf';
const PLUGIN_TITLE = __('Wordsurf', 'wordsurf');

// Plugin icon component
const PluginIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8 8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="currentColor"/>
        <path d="M12 12m-6 0a6 6 0 1 0 12 0a6 6 0 1 0 -12 0" fill="currentColor" fillOpacity="0.3"/>
    </svg>
);

const WordsurfSidebar = () => {
    const [diffContext, setDiffContext] = useState({ originalContent: '', diffs: [] });
    
    // Chat history management
    const chatHistory = useRef(new ChatHistory([]));
    
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

    // Handle diff received from chat handler
    const handleDiffReceived = useCallback((diffData) => {
        setDiffContext(prev => ({
            ...prev,
            diffs: [...prev.diffs, diffData],
        }));
    }, []);

    // Chat handler hook must be initialized before callbacks that use it.
    const chatHandler = useChatHandler({
        postId: postData.postId,
        onDiffReceived: handleDiffReceived,
        chatHistory
    });

    const handleAcceptDiff = useCallback((diffData) => {
        // Record user decision in chat history
        chatHandler.recordUserDecision('accepted', diffData);
        // Remove the diff from the queue
        setDiffContext(prev => ({
            ...prev,
            diffs: prev.diffs.filter(d => d.tool_call_id !== diffData.tool_call_id)
        }));
    }, [chatHandler]);

    const handleRejectDiff = useCallback((diffData) => {
        // Record user decision in chat history
        chatHandler.recordUserDecision('rejected', diffData);
        // Remove the diff from the queue
        setDiffContext(prev => ({
            ...prev,
            diffs: prev.diffs.filter(d => d.tool_call_id !== diffData.tool_call_id)
        }));
    }, [chatHandler]);

    const handleSendWithContent = () => {
        setDiffContext(prev => ({ ...prev, originalContent: postData.content, diffs: [] })); // Reset diffs for new turn
        chatHandler.handleSend();
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
                <ChatInterface
                    messages={chatHandler.uiMessages}
                    isWaiting={chatHandler.isWaiting}
                    inputValue={chatHandler.inputValue}
                    onInputChange={(e) => chatHandler.setInputValue(e.target.value)}
                    onSend={handleSendWithContent}
                    isStreaming={chatHandler.isStreaming}
                />
            </PluginSidebar>
            
            <InlineDiffManager 
                diffContext={diffContext}
                onAccept={handleAcceptDiff}
                onReject={handleRejectDiff}
            />
        </>
    );
};

// Only register if not already registered to prevent conflicts
if (!wp.plugins.getPlugin(PLUGIN_NAME)) {
    registerPlugin(PLUGIN_NAME, {
        render: WordsurfSidebar,
    });
} 