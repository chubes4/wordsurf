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

Wordsurf is an agentic WordPress plugin that integrates AI directly into the WordPress block editor. The system enables real-time streaming chat with AI agents that can read and manipulate WordPress content through a sophisticated tool system with diff preview capabilities.

### Core Streaming Architecture

**Server-Sent Events (SSE)**: All AI interactions use streaming responses for real-time user experience. The `ChatStreamSession` class manages the streaming state and tool execution pipeline.

**Integrated Tool Calling**: Tools execute during the streaming response, not after. This enables real-time feedback and immediate tool results integrated into the conversation flow.

### Tool System Architecture 

**Tool Registration Pattern**: All tools extend `Wordsurf_BaseTool` and implement:
- `get_name()`, `get_description()` - Tool metadata
- `define_parameters()` - Declarative parameter definition with required flags
- `execute($context)` - Tool logic with context from current post/conversation

**Tool Schema Generation**: Base class automatically generates OpenAI function calling schemas from parameter definitions with proper strict mode handling.

**Tool Discovery**: `Wordsurf_Tool_Manager::load_tools()` automatically discovers and registers tools from `includes/agent/core/tools/`.

### Diff Preview System

**Block-Level Diffs**: When tools return `preview: true`, the system:
1. Backend wraps target blocks with custom `wordsurf/diff` blocks
2. Frontend renders side-by-side diffs with accept/reject controls
3. User approval triggers actual content updates
4. Smart text replacement preserves HTML structure and attributes

**Critical Block Indexing**: Both backend and frontend must filter out empty blocks (`blockName: null`) to maintain consistent block indexing when targeting specific blocks.

### Frontend Architecture

**Build System**: Uses `@wordpress/scripts` with custom webpack config:
- `editor.js` - Main WordPress editor integration 
- `admin.js` - Admin settings interface
- `diff-block.js` - Custom diff block rendering

**React Integration**: Integrates with WordPress data stores and follows WordPress component patterns. Key modules:
- `ChatStreamSession` - Manages streaming state
- `InlineDiffManager` - Handles diff block rendering
- `MessageFormatUtils` - Formats OpenAI messages for UI

### Message Flow Architecture

**OpenAI Message Format**: System maintains OpenAI-compatible message history with proper tool message handling. Backend sanitizes messages for API compatibility while frontend maintains UI-friendly message display.

**Context Management**: System prompt is dynamically generated with current post context and available tool descriptions for each request.

### Critical Implementation Details

**Smart Text Replacement**: `wordsurf_smart_text_replace()` function parses HTML into tags vs text nodes, only replacing visible text content while preserving all HTML attributes and structure to prevent breaking URLs and links.

**Security Model**: 
- All AJAX requests require valid WordPress nonces
- Tool execution checks user capabilities (`edit_posts`, `publish_posts`) 
- Content sanitized using WordPress functions (`wp_kses_post`, `sanitize_text_field`)

**WordPress Integration**:
- Respects WordPress user capabilities and nonces
- Compatible with WordPress 5.0+ and PHP 7.4+
- Debug logging available via `error_log()` with "Wordsurf DEBUG:" prefix

### Chat Continuation Architecture

**Event-Driven Continuation**: After tool acceptance/rejection, chat continuation is triggered via custom DOM events (`wordsurf-continue-chat`). This enables seamless conversation flow after user decisions.

**State Management**: The system maintains separate diff context and chat state to prevent UI conflicts during tool acceptance flows.

**EventSource Management**: Multiple EventSource connections are prevented through proper state checking in `ChatHandler` to avoid "Store already registered" errors.

## Development Patterns

**Adding New Tools**:
1. Create class extending `Wordsurf_BaseTool` in `includes/agent/core/tools/`
2. Implement required methods with proper parameter definitions
3. Register in `Wordsurf_Tool_Manager::load_tools()`
4. Tool schema generation and registration is automatic

**Function Calling Implementation**: All tools use strict mode schemas with proper error handling and security validation. Parameters must be explicitly defined with `additionalProperties: false`.

**Streaming Responses**: All AI interactions use streaming for real-time user experience. Tools execute during streaming, not after completion.

**Diff Acceptance Pattern**: When implementing bulk operations (accept/reject all), suppress individual chat continuations and trigger a single continuation after all operations complete to prevent EventSource conflicts.

## Planned Architecture Migration

**AI HTTP Client Integration**: The plugin is planned to be migrated to use the `ai-http-client` library located at `/Users/chubes/Sites/ai-http-client` for AI API communication. This migration will:

- **Centralize AI API Logic**: Replace the current direct OpenAI API calls with the standardized ai-http-client
- **Improve Maintainability**: Use a shared, tested library for AI interactions across projects  
- **Enhanced Features**: Leverage advanced features like request retries, rate limiting, and response caching
- **Better Error Handling**: Benefit from comprehensive error handling and logging built into the client
- **Multi-Provider Support**: Future support for different AI providers through the unified client interface

**Migration Considerations**:
- The streaming architecture should be preserved during migration
- Tool calling functionality must remain compatible with the current diff preview system
- EventSource-based streaming may need adaptation to work with the new client
- Chat continuation flow should be maintained for seamless user experience