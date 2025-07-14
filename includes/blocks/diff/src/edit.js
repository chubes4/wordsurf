import { __ } from '@wordpress/i18n';
import { 
    useBlockProps, 
    InspectorControls,
    InnerBlocks
} from '@wordpress/block-editor';
import { 
    PanelBody, 
    Button, 
    TextControl,
    SelectControl,
    ToggleControl
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { DiffRenderer } from './DiffRenderer';
import { DiffActions } from './DiffActions';

export default function Edit({ attributes, setAttributes, clientId }) {
    const {
        diffId,
        diffType,
        originalContent,
        replacementContent,
        status,
        toolCallId,
        editType,
        searchPattern,
        caseSensitive,
        originalBlockContent,
        originalBlockType,
    } = attributes;

    const [isProcessing, setIsProcessing] = useState(false);
    const [innerBlocksInitialized, setInnerBlocksInitialized] = useState(false);

    // Use WordPress hooks for data select
    const currentPostId = useSelect(select => select('core/editor').getCurrentPostId());
    
    const { replaceInnerBlocks } = useDispatch('core/block-editor');
    const innerBlocks = useSelect(select => select('core/block-editor').getBlocks(clientId));

    // Initialize inner blocks from originalBlockContent when block is first created
    useEffect(() => {
        if (!innerBlocksInitialized && originalBlockContent) {
            try {
                // Parse the original block content into actual blocks
                const parsedBlocks = wp.blocks.parse(originalBlockContent);
                
                if (parsedBlocks.length > 0) {
                    // Apply diff tags directly to the block content if status is pending
                    const blocksWithDiffTags = status === 'pending' 
                        ? DiffRenderer.applyDiffTagsToBlocks(parsedBlocks, attributes)
                        : parsedBlocks;
                    
                    replaceInnerBlocks(clientId, blocksWithDiffTags, false);
                    setInnerBlocksInitialized(true);
                }
            } catch (error) {
                console.error('Error parsing original block content:', error);
            }
        }
    }, [originalBlockContent, innerBlocks.length, innerBlocksInitialized, clientId, replaceInnerBlocks, status, attributes]);

    const blockProps = useBlockProps({
        className: `wordsurf-diff-block wordsurf-diff-${diffType} wordsurf-diff-${status}`,
    });

    // Create action handlers using DiffActions module
    const { handleAccept, handleReject } = DiffActions.createActionHandlers(
        attributes,
        clientId,
        currentPostId,
        setIsProcessing,
        setAttributes
    );

    // Render action buttons
    const renderActionButtons = () => {
        if (status !== 'pending') {
            return (
                <div className="wordsurf-diff-status">
                    {status === 'accepted' ? '✓ Accepted' : '✗ Rejected'}
                </div>
            );
        }

        return (
            <div className="wordsurf-diff-actions">
                <Button
                    isPrimary
                    onClick={handleAccept}
                    disabled={isProcessing}
                    className="wordsurf-accept-btn"
                >
                    {isProcessing ? 'Accepting...' : 'Accept'}
                </Button>
                <Button
                    isSecondary
                    onClick={handleReject}
                    disabled={isProcessing}
                    className="wordsurf-reject-btn"
                >
                    {isProcessing ? 'Rejecting...' : 'Reject'}
                </Button>
            </div>
        );
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Diff Settings', 'wordsurf')}>
                    <TextControl
                        label={__('Diff ID', 'wordsurf')}
                        value={diffId}
                        onChange={(value) => setAttributes({ diffId: value })}
                        readOnly
                    />
                    <SelectControl
                        label={__('Diff Type', 'wordsurf')}
                        value={diffType}
                        options={[
                            { label: 'Edit', value: 'edit' },
                            { label: 'Insert', value: 'insert' },
                            { label: 'Delete', value: 'delete' },
                        ]}
                        onChange={(value) => setAttributes({ diffType: value })}
                    />
                    <SelectControl
                        label={__('Status', 'wordsurf')}
                        value={status}
                        options={[
                            { label: 'Pending', value: 'pending' },
                            { label: 'Accepted', value: 'accepted' },
                            { label: 'Rejected', value: 'rejected' },
                        ]}
                        onChange={(value) => setAttributes({ status: value })}
                    />
                    <ToggleControl
                        label={__('Case Sensitive', 'wordsurf')}
                        checked={caseSensitive}
                        onChange={(value) => setAttributes({ caseSensitive: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div className="wordsurf-diff-header">
                    <span className="wordsurf-diff-type-label">
                        {diffType === 'write' ? 'Full Post Replacement' : 
                         diffType === 'edit' ? 'Text Edit' : 
                         diffType === 'insert' ? 'Text Insertion' : 'Text Change'}
                    </span>
                    {renderActionButtons()}
                </div>
                
                <div className="wordsurf-diff-content">
                    {/* Use InnerBlocks for editable content with direct ins/del tags */}
                    <InnerBlocks 
                        templateLock={status === 'pending' ? 'all' : false}
                        allowedBlocks={true}
                    />
                </div>
            </div>
        </>
    );
} 