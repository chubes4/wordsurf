import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

// New architecture imports
import { useChatHandler } from './agent/core/ChatHandler';
import { ChatHistory } from './context/ChatHistory';
import { InlineDiffManager } from './editor/InlineDiffManager';
import { ChatInterface } from './editor/UIComponents';
import { HandleAcceptAll } from '../../includes/blocks/diff/src/HandleAcceptAll';
import { FindDiffBlocks } from '../../includes/blocks/diff/src/FindDiffBlocks';



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
    
    // Debug: Log diffContext changes only when diffs are added
    useEffect(() => {
        if (diffContext.diffs.length > 0) {
            console.log('WordsurfPlugin: diffContext has diffs:', diffContext);
        }
    }, [diffContext.diffs.length]);
    // Chat history management
    const chatHistory = useRef(new ChatHistory([]));
    // Get current post data and blocks
    const postData = useSelect(select => {
        const { getCurrentPostId, getEditedPostAttribute } = select('core/editor');
        const { getBlocks } = select('core/block-editor');
        const postId = getCurrentPostId();
        return {
            postId,
            title: getEditedPostAttribute('title'),
            content: getEditedPostAttribute('content'),
            status: getEditedPostAttribute('status'),
            type: getEditedPostAttribute('type'),
            blocks: getBlocks(),
        };
    });

    // Monitor editor blocks and reset chat state when no diff blocks remain
    useEffect(() => {
        if (diffContext.diffs.length > 0 && postData.blocks) {
            if (!FindDiffBlocks.hasDiffBlocks()) {
                console.log('WordsurfPlugin: No diff blocks remaining in editor - resetting chat state');
                setDiffContext(prev => ({ ...prev, diffs: [] }));
            }
        }
    }, [postData.blocks, diffContext.diffs.length]);

    // Handle diff received from chat handler
    const handleDiffReceived = useCallback((diffData) => {
        console.log('WordsurfPlugin: Received diff data:', diffData);
        console.log('WordsurfPlugin: Has diff_block_content:', !!diffData.diff_block_content);
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

    // Listen for chat continuation events from diff actions
    useEffect(() => {
        const handleChatContinuation = (event) => {
            console.log('WordsurfPlugin: Chat continuation event received:', event.detail);
            
            // Clear pending diffs immediately when chat continuation starts
            console.log('WordsurfPlugin: Clearing pending diffs for chat continuation');
            setDiffContext(prev => ({ ...prev, diffs: [] }));
            
            // Check if we have a chat handler and no current streaming is happening
            if (chatHandler && !chatHandler.isStreaming && !chatHandler.isWaiting && !chatHandler.currentEventSource) {
                console.log('WordsurfPlugin: Triggering model-driven continuation with tool result');
                
                const { toolResult } = event.detail;
                if (toolResult && toolResult.toolCallId) {
                    // Trigger tool result continuation
                    chatHandler.continueWithToolResult(toolResult);
                } else {
                    // Fallback: reset chat state for manual continuation
                    chatHandler.resetForContinuation();
                }
            } else {
                console.log('WordsurfPlugin: Skipping chat continuation - already streaming or waiting', {
                    isStreaming: chatHandler?.isStreaming,
                    isWaiting: chatHandler?.isWaiting,
                    hasEventSource: !!chatHandler?.currentEventSource
                });
            }
        };

        document.addEventListener('wordsurf-continue-chat', handleChatContinuation);
        
        return () => {
            document.removeEventListener('wordsurf-continue-chat', handleChatContinuation);
        };
    }, [chatHandler]);

    const handleAcceptDiff = useCallback((diffData) => {
        // Note: DiffActions already handles backend communication via sendUserDecision
        // Only record locally in chat history - no need for duplicate backend call
        chatHistory.current.addUserDecision('accepted', diffData);
        setDiffContext(prev => ({
            ...prev,
            diffs: prev.diffs.filter(d => d.tool_call_id !== diffData.tool_call_id)
        }));
    }, [chatHistory]);
    const handleRejectDiff = useCallback((diffData) => {
        // Note: DiffActions already handles backend communication via sendUserDecision
        // Only record locally in chat history - no need for duplicate backend call
        chatHistory.current.addUserDecision('rejected', diffData);
        setDiffContext(prev => ({
            ...prev,
            diffs: prev.diffs.filter(d => d.tool_call_id !== diffData.tool_call_id)
        }));
    }, [chatHistory]);
    
    // Accept/reject all changes handlers - clean bridge using DiffActions
    const handleAcceptAllChanges = useCallback(async () => {
        console.log('WordsurfPlugin: Accept all clicked');
        
        try {
            const processedCount = await HandleAcceptAll.acceptAll();
            console.log('WordsurfPlugin: Accepted', processedCount, 'diff blocks');
            
            // Record decisions locally (DiffActions handles backend communication)
            diffContext.diffs.forEach(diff => {
                chatHistory.current.addUserDecision('accepted', diff);
            });
            setDiffContext(prev => ({ ...prev, diffs: [] }));
        } catch (error) {
            console.error('WordsurfPlugin: Error accepting all changes:', error);
        }
    }, [diffContext.diffs, chatHandler]);
    
    const handleRejectAllChanges = useCallback(async () => {
        console.log('WordsurfPlugin: Reject all clicked');
        
        try {
            const processedCount = await HandleAcceptAll.rejectAll();
            console.log('WordsurfPlugin: Rejected', processedCount, 'diff blocks');
            
            // Record decisions locally (DiffActions handles backend communication)
            diffContext.diffs.forEach(diff => {
                chatHistory.current.addUserDecision('rejected', diff);
            });
            setDiffContext(prev => ({ ...prev, diffs: [] }));
        } catch (error) {
            console.error('WordsurfPlugin: Error rejecting all changes:', error);
        }
    }, [diffContext.diffs, chatHandler]);
    // Send handler resets diffs for new turn
    const handleSendWithContent = useCallback(() => {
        setDiffContext(prev => ({ ...prev, originalContent: postData.content, diffs: [] }));
        chatHandler.handleSend();
    }, [postData.content, chatHandler, setDiffContext]);
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
                    hasStreamingAssistant={chatHandler.hasStreamingAssistant}
                    pendingDiffs={diffContext.diffs}
                    onAcceptAllChanges={handleAcceptAllChanges}
                    onRejectAllChanges={handleRejectAllChanges}
                />
            </PluginSidebar>
            <InlineDiffManager 
                diffContext={diffContext}
                onAccept={handleAcceptDiff}
                onReject={handleRejectDiff}
                key={`diff-manager-${diffContext.diffs.length}`}
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