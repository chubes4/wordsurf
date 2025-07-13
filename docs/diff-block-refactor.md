# Wordsurf Diff Block Refactor Plan

## Rationale

To support robust, granular, and persistent diff acceptance/rejection in Gutenberg, we will implement a custom diff block (`wordsurf/diff`) that wraps each individual diff (edit, insert, etc.) in the post content. This enables granular actions, persistent metadata, and extensibility for future diff types.

## Step-by-Step Plan

1. **✅ Design the Custom Diff Block**
   - Block name: `wordsurf/diff`
   - Attributes: `diffId`, `type`, `original`, `replacement`, `status`, etc.

2. **✅ Backend – Output Diff Blocks**
   - Updated `edit_post.php`, `insert_content.php` to output diff blocks with metadata.
   - For edits: wrap changed text with a diff block.
   - For insertions: insert a diff block at the correct position.
   - For full replacements: optionally break into diff blocks, or use a single block.

3. **✅ Register the Custom Block in JS**
   - Created `src/js/blocks/wordsurf-diff.js`.
   - Registered the block, defined attributes, and implemented `edit`/`save` logic for UI and actions.

4. **✅ Update Frontend Diff Handling**
   - Updated `InlineDiffManager.js`, `WordsurfPlugin.js` to use diff blocks instead of inline markup.
   - On receiving a diff, update post content with new diff blocks.

5. **✅ Implement Accept/Reject Logic in the Block**
   - On accept: replace the block with its `replacement` content.
   - On reject: replace the block with its `original` content.
   - Communicate with backend/tool system as needed.

6. **✅ Update CSS and UI Components**
   - Created `assets/css/editor/diff-block.css` for styling.
   - Updated editor interface to include diff block CSS.

7. **✅ Update Documentation**
   - Updated this file as a persistent reference.

8. **🔄 Testing and Edge Cases**
   - Test multiple diffs in one block, different block types, accept/reject in any order, undo/redo, copy/paste, reload, and persistence.

## Summary Table

| Step | Task | Files |
|------|------|-------|
| 1 | Design block | N/A |
| 2 | Backend output | `edit_post.php`, `insert_content.php`, `write_to_post.php` |
| 3 | Register block | `wordsurf-diff.js`, `WordsurfPlugin.js` |
| 4 | Frontend diff handling | `ChatHandler.js`, `ChatStreamSession.js`, `InlineDiffManager.js`, `WordsurfPlugin.js` |
| 5 | Accept/reject logic | `wordsurf-diff.js` |
| 6 | CSS/UI | `inline-diff-highlight.css`, `UIComponents.js` |
| 7 | Documentation | `.mdc` files, `docs/` |
| 8 | Testing | N/A |

## Notes
- Each diff gets its own block for maximum granularity and persistence.
- The diff block can be extended to support multiple diff types and rich metadata.
- This approach is robust, future-proof, and aligns with best practices for collaborative and AI-driven editing in Gutenberg. 