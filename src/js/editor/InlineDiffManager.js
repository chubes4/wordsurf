import React, { useEffect, useState, useRef } from 'react';
import { useSelect, useDispatch } from '@wordpress/data';
import { parse, createBlock } from '@wordpress/blocks';
import { PinnedItems } from '@wordpress/interface';
import { Button, Fill } from '@wordpress/components';
import { FindDiffBlocks } from '../../../includes/blocks/diff/src/FindDiffBlocks';

/**
 * InlineDiffManager Component V4: Modern Block Editor Only
 * 
 * Revolutionary WordPress diff system - modern block editor only.
 * Backend wraps target blocks with diff metadata, frontend renders diff blocks.
 * No classic editor fallback - transient wrapper blocks that disappear after action.
 */
export const InlineDiffManager = ({ diffContext, onAccept, onReject }) => {
    const { diffs } = diffContext;
    
    const [originalBlocks, setOriginalBlocks] = useState(null);
    const [isPreviewing, setIsPreviewing] = useState(false);
    const processedDiffsRef = useRef(new Set());

    const { blocks, postContent } = useSelect(select => ({
        blocks: select('core/block-editor').getBlocks(),
        postContent: select('core/editor').getEditedPostAttribute('content'),
    }));

    const { lockPostSaving, unlockPostSaving } = useDispatch('core/editor');
    const { updateBlockAttributes, insertBlocks, replaceBlock, resetBlocks } = useDispatch('core/block-editor');


    useEffect(() => {
        if (diffs.length > 0 && !isPreviewing) {
            const currentDiff = diffs[0];
            const diffId = currentDiff.diff_id || currentDiff.tool_call_id;
            
            // Prevent processing the same diff multiple times
            if (processedDiffsRef.current.has(diffId)) {
                return;
            }
            
            console.log('InlineDiffManager: Processing new diff:', currentDiff);
            console.log('InlineDiffManager: Diff has target_blocks:', !!currentDiff.target_blocks);
            if (currentDiff.target_blocks) {
                console.log('InlineDiffManager: Number of target blocks:', currentDiff.target_blocks.length);
            }
            
            try {
                // Store original state and lock editor
                setOriginalBlocks([...blocks]);
                lockPostSaving('wordsurf-diff-preview');
                setIsPreviewing(true);
                processedDiffsRef.current.add(diffId);

                // Check if we have target blocks info from the backend (new granular approach)
                if (currentDiff.target_blocks && currentDiff.target_blocks.length > 0) {
                    console.log('InlineDiffManager: Using granular target blocks approach');
                    
                    try {
                        // Process each target block individually
                        currentDiff.target_blocks.forEach((targetInfo, index) => {
                            const { block_index, diff_wrapper_block } = targetInfo;
                            
                            // Parse the diff wrapper block
                            const parsedDiffBlock = wp.blocks.parse(diff_wrapper_block)[0];
                            
                            if (parsedDiffBlock && blocks[block_index]) {
                                const targetBlock = blocks[block_index];
                                console.log(`InlineDiffManager: Replacing block ${block_index} with diff wrapper`);
                                
                                replaceBlock(targetBlock.clientId, parsedDiffBlock);
                            } else {
                                console.error(`InlineDiffManager: Could not process target block ${block_index}`);
                            }
                        });
                        
                        console.log('InlineDiffManager: Successfully processed', currentDiff.target_blocks.length, 'target blocks granularly');
                    } catch (error) {
                        console.error('InlineDiffManager: Error processing target blocks:', error);
                        setIsPreviewing(false);
                        processedDiffsRef.current.delete(diffId);
                    }
                } else if (currentDiff.diff_block_content) {
                    // Fallback to legacy approach for backward compatibility
                    console.log('InlineDiffManager: Using legacy diff_block_content approach');
                    const diffBlocks = wp.blocks.parse(currentDiff.diff_block_content);
                    
                    if (diffBlocks.length > 0) {
                        resetBlocks(diffBlocks);
                        console.log('InlineDiffManager: Successfully used legacy approach');
                    } else {
                        setIsPreviewing(false);
                        processedDiffsRef.current.delete(diffId);
                    }
                } else {
                    console.error('InlineDiffManager: No target_blocks or diff_block_content provided');
                    setIsPreviewing(false);
                    processedDiffsRef.current.delete(diffId);
                }
                
            } catch (error) {
                console.error('InlineDiffManager: Error processing diff:', error);
                setIsPreviewing(false);
                processedDiffsRef.current.delete(diffId);
            }
        }
    }, [diffs.length, isPreviewing]); // Simplified dependencies to prevent infinite loops


    // Handle diff block accept/reject (called from the diff block itself)
    const handleDiffBlockAccept = (diffId) => {
        console.log('InlineDiffManager: Diff block accept clicked for', diffId);
        
        // Find and remove the diff block from the editor
        const { removeBlock, replaceBlock } = useDispatch('core/block-editor');
        
        const targetDiffBlock = FindDiffBlocks.findDiffBlocksByDiffId(diffId)[0];
        const diffBlockIndex = targetDiffBlock ? blocks.findIndex(block => block.clientId === targetDiffBlock.clientId) : -1;
        
        if (diffBlockIndex !== -1 && targetDiffBlock) {
            const diffBlock = targetDiffBlock;
            
            // Replace the diff block with the replacement content
            const newBlock = createBlock('core/paragraph', {
                content: diffBlock.attributes.replacementContent,
            });
            
            replaceBlock(diffBlock.clientId, newBlock);
            
            console.log('InlineDiffManager: Replaced diff block with accepted content');
        }
        
        // Clean up and call parent accept handler
        const currentDiff = diffs[0];
        unlockPostSaving('wordsurf-diff-preview');
        setOriginalBlocks(null);
        setIsPreviewing(false);
        processedDiffsRef.current.delete(currentDiff.tool_call_id);
        onAccept(currentDiff);
    };

    const handleDiffBlockReject = (diffId) => {
        console.log('InlineDiffManager: Diff block reject clicked for', diffId);
                    
        // Find and remove the diff block from the editor
        const { removeBlock, replaceBlock } = useDispatch('core/block-editor');
        
        const targetDiffBlock = FindDiffBlocks.findDiffBlocksByDiffId(diffId)[0];
        const diffBlockIndex = targetDiffBlock ? blocks.findIndex(block => block.clientId === targetDiffBlock.clientId) : -1;
        
        if (diffBlockIndex !== -1 && targetDiffBlock) {
            const diffBlock = targetDiffBlock;
            
            // Replace the diff block with the original content (if any)
            if (diffBlock.attributes.originalContent) {
                const newBlock = createBlock('core/paragraph', {
                    content: diffBlock.attributes.originalContent,
                });
                replaceBlock(diffBlock.clientId, newBlock);
            } else {
                // For insertions, just remove the block
                removeBlock(diffBlock.clientId);
            }
            
            console.log('InlineDiffManager: Removed/replaced diff block with rejected content');
                }
        
        // Clean up and call parent reject handler
        const currentDiff = diffs[0];
        unlockPostSaving('wordsurf-diff-preview');
        setOriginalBlocks(null);
        setIsPreviewing(false);
        processedDiffsRef.current.delete(currentDiff.tool_call_id);
        onReject(currentDiff);
    };

    const handleAccept = () => {
        const currentDiff = diffs[0];
        
        console.log('InlineDiffManager: Header accept clicked for diff block');
        
        unlockPostSaving('wordsurf-diff-preview');
        setOriginalBlocks(null);
        setIsPreviewing(false);
        processedDiffsRef.current.delete(currentDiff.tool_call_id);
        onAccept(currentDiff);
    };

    const handleReject = () => {
        const currentDiff = diffs[0];
        
        console.log('InlineDiffManager: Header reject clicked for diff block');
        
        unlockPostSaving('wordsurf-diff-preview');
        setOriginalBlocks(null);
        setIsPreviewing(false);
        processedDiffsRef.current.delete(currentDiff.tool_call_id);
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
// Add CSS for .wordsurf-diff-ins and .wordsurf-diff-del in your stylesheet for green/red highlights 