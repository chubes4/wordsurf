import React, { useEffect, useRef, useState } from 'react';
import { createDiffHandler } from './diff';

/**
 * Simplified Inline Diff Highlight Component
 * Uses the tool system for modular diff handling
 */
const InlineDiffHighlight = ({ diffData, onAccept, onReject, onClose }) => {
  const [isInjected, setIsInjected] = useState(false);
  const diffHandlerRef = useRef(null);

  useEffect(() => {
    if (!diffData) return;

    // Find the editor content area
    const editorFrame = document.querySelector('.edit-post-visual-editor__content-area iframe');
    let editorDocument = document;
    
    if (editorFrame) {
      // If in iframe (visual editor)
      editorDocument = editorFrame.contentDocument || editorFrame.contentWindow.document;
    }

    // Create and execute the appropriate diff handler
    const injectDiff = () => {
      try {
        // Create the diff handler using the tool system
        const diffHandler = createDiffHandler(
          diffData, 
          editorDocument, 
          onAccept, 
          onReject
        );
        
        // Store reference for cleanup
        diffHandlerRef.current = diffHandler;
        
        // Create the highlight
        diffHandler.createHighlight();
        setIsInjected(true);
        
      } catch (error) {
        console.error('Failed to create diff handler:', error);
        // Fallback: just call onReject to close the diff
        onReject(diffData);
      }
    };

    // Wait a bit for the editor to be ready, then inject
    const timeout = setTimeout(injectDiff, 100);

    return () => {
      clearTimeout(timeout);
      // Cleanup will be handled in the cleanup effect
    };
  }, [diffData, onAccept, onReject]);

  // Cleanup when component unmounts or diff data changes
  useEffect(() => {
    return () => {
      if (diffHandlerRef.current) {
        diffHandlerRef.current.cleanup();
        diffHandlerRef.current = null;
      }
      setIsInjected(false);
    };
  }, []);

  // Don't render anything - we're injecting directly into the editor
  return null;
};

export default InlineDiffHighlight; 