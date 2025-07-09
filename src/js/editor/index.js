import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import WordsurfChatStream from './WordsurfChatStream';

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

    const handleSend = (message) => {
        // Optional: Add any side effects when a message is sent
        console.log('Message sent:', message);
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
                />
            </PluginSidebar>
        </>
    );
};

registerPlugin(PLUGIN_NAME, {
    render: WordsurfSidebar,
}); 