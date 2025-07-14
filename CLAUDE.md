# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands

```bash
# Development mode (watch for changes)
npm run start

# Production build
npm run build

# Linting
npm run lint:js      # JavaScript linting
npm run lint:css     # CSS linting

# Testing
npm run test:unit    # Unit tests
npm run test:e2e     # End-to-end tests

# Other utilities
npm run format       # Format code
npm run plugin-zip   # Create distribution zip
```

## Architecture Overview

Wordsurf is an agentic WordPress plugin that integrates AI directly into the WordPress block editor. The system enables real-time chat with AI agents that can manipulate WordPress content through a sophisticated tool system.

### Core Components

**Agent Core** (`includes/agent/core/`):
- `class-agent-core.php` - Main orchestrator handling streaming chat requests
- `class-tool-manager.php` - Discovers and manages available tools
- `class-system-prompt.php` - Generates context-aware system prompts
- `class-context-manager.php` - Handles post context and conversation state

**Tool System** (`includes/agent/core/tools/`):
- `basetool.php` - Abstract base class for all tools
- `read_post.php` - Reads and analyzes WordPress posts
- `edit_post.php` - Performs surgical text edits with diff preview
- `insert_content.php` - Inserts content at specific locations
- `write_to_post.php` - Replaces entire post content

**Frontend Architecture** (`src/js/`):
- `editor/` - WordPress block editor integration
- `agent/core/` - Chat streaming and message handling
- `context/` - State management for chat history and tools

### Key Architectural Patterns

**Streaming Architecture**: 
- Uses Server-Sent Events (SSE) for real-time AI responses
- `ChatStreamSession` manages streaming state and tool execution
- Backend processes streaming with integrated tool calling

**Diff System**:
- Block-level diff preview using custom `wordsurf/diff` blocks
- Backend wraps target blocks, frontend renders diffs
- Smart text replacement preserves HTML attributes (URLs, links)
- User can accept/reject changes at block level

**Tool Execution Flow**:
1. AI decides to use tool during streaming
2. Backend executes tool and returns result immediately
3. If tool returns `preview: true`, frontend shows diff blocks
4. User accepts/rejects diffs which updates actual content

**Message Format**:
- OpenAI API format with proper tool message handling
- Backend sanitizes messages for API compatibility
- Frontend maintains UI-friendly message history

### Critical Implementation Details

**Block Indexing**: Backend and frontend must filter out empty blocks (`blockName: null`) to maintain consistent indexing when finding target blocks.

**Smart Text Replacement**: Uses `wordsurf_smart_text_replace()` function that:
- Parses HTML into tags vs text nodes
- Only replaces visible text content
- Preserves all HTML attributes and structure
- Prevents breaking URLs and links during edits

**Tool Registration**: New tools must:
1. Extend `Wordsurf_BaseTool` 
2. Implement `get_name()`, `get_description()`, `define_parameters()`, `execute()`
3. Be registered in `Wordsurf_Tool_Manager::load_tools()`

**WordPress Integration**:
- Uses `wp-scripts` for build system
- Integrates with WordPress block editor data store
- Respects WordPress user capabilities and nonces
- Compatible with WordPress 5.0+ and PHP 7.4+

## OpenAI API Configuration

The plugin uses OpenAI's streaming API with function calling. Key settings:
- Model: `gpt-4.1` 
- Streaming: `true`
- Tools are dynamically loaded from tool system
- System prompt includes current post context and tool descriptions

## Security Considerations

- All AJAX requests require valid WordPress nonces
- Tool execution checks user capabilities (`edit_posts`, `publish_posts`)
- Content is sanitized using WordPress functions (`wp_kses_post`, `sanitize_text_field`)
- API keys stored in WordPress options with proper escaping

## Development Notes

- Frontend uses React with WordPress data stores
- Build system uses `@wordpress/scripts` for consistency
- All JavaScript follows WordPress coding standards
- PHP follows WordPress plugin development best practices
- Debug logging available via `error_log()` with "Wordsurf DEBUG:" prefix