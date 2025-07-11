import React, { useEffect, useState } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock, parse } from '@wordpress/blocks';
import { PinnedItems } from '@wordpress/interface';
import { Button, Fill } from '@wordpress/components';
import { diffChars } from 'diff';

/**
 * InlineDiffManager Component V1: Full Content Preview
 * 
 * This component will:
 * 1. When a diff is available, lock the editor and store the original blocks.
 * 2. Generate a highlighted HTML diff of the entire post content.
 * 3. Replace the editor's content with a single HTML block showing the diff.
 * 4. Render Accept/Reject controls in the editor header.
 * 5. On accept/reject, restore the editor to a normal state.
 */
export const InlineDiffManager = ({ diffContext, onAccept, onReject }) => {
    const { diffs } = diffContext;
    const [originalBlocks, setOriginalBlocks] = useState(null);
    const [isPreviewing, setIsPreviewing] = useState(false);

    const { blocks } = useSelect(select => ({
        blocks: select('core/block-editor').getBlocks(),
    }));

    const { lockPostSaving, unlockPostSaving } = useDispatch('core/editor');
    const { replaceBlocks } = useDispatch('core/block-editor');

    useEffect(() => {
        // Only start a new preview if there are diffs and we aren't already in a preview.
        if (diffs.length > 0 && !isPreviewing) {
            const currentDiff = diffs[0];
            const { original_content, new_content } = currentDiff;

            // 1. Store original blocks and lock editor
            setOriginalBlocks(blocks);
            lockPostSaving('wordsurf-diff-preview');
            setIsPreviewing(true);

            // 2. Generate highlighted HTML
            const diffResult = diffChars(original_content, new_content);
            const highlightedHtml = diffResult.map((part) => {
                const tag = part.added ? 'ins' : part.removed ? 'del' : 'span';
                const className = part.added ? 'wordsurf-diff-ins' : part.removed ? 'wordsurf-diff-del' : '';
                return `<${tag} class="${className}">${part.value}</${tag}>`;
            }).join('');

            // 3. Create and insert the preview block
            const previewBlock = createBlock('core/html', { content: highlightedHtml });
            replaceBlocks(blocks.map(b => b.clientId), previewBlock);
        }
    }, [diffs, isPreviewing, blocks, lockPostSaving, replaceBlocks]);

    const handleAccept = () => {
        const currentDiff = diffs[0];
        const newBlocks = parse(currentDiff.new_content);

        replaceBlocks(blocks.map(b => b.clientId), newBlocks); // `blocks` here is just the single HTML block
        unlockPostSaving('wordsurf-diff-preview');
        setOriginalBlocks(null);
        setIsPreviewing(false);
        onAccept(currentDiff);
    };

    const handleReject = () => {
        const currentDiff = diffs[0];
        
        replaceBlocks(blocks.map(b => b.clientId), originalBlocks);
        unlockPostSaving('wordsurf-diff-preview');
        setOriginalBlocks(null);
        setIsPreviewing(false);
        onReject(currentDiff);
    };

    return (
        <Fill name="PluginHeader">
            {isPreviewing && (
                <PinnedItems>
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center', color: '#1e1e1e', backgroundColor: '#fff', padding: '4px 8px', borderRadius: '4px', border: '1px solid #ddd' }}>
                        <strong>Wordsurf Change Preview:</strong>
                        <Button isPrimary onClick={handleAccept}>Accept Changes</Button>
                        <Button isSecondary onClick={handleReject}>Reject</Button>
                    </div>
                </PinnedItems>
            )}
        </Fill>
    );
}; 