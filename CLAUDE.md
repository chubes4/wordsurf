# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Wordsurf is an agentic WordPress plugin that integrates AI agents directly into the WordPress editor, enabling intelligent content creation and management through natural language interactions. The plugin provides a powerful AI-powered content assistant that can read, analyze, and manipulate WordPress content in real-time through a sophisticated tool system with diff preview capabilities.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Node.js 14+ (for development)
- AI provider API key (OpenAI, Anthropic, Gemini, Grok, or OpenRouter)

## Installation & Setup

The plugin is installed as a standard WordPress plugin in `/wp-content/plugins/wordsurf/`. Key setup steps:

1. Build assets with `npm install && npm run build`
2. Activate plugin in WordPress admin
3. Configure AI provider settings in WordPress Admin → Settings → Wordsurf
4. Plugin integrates into WordPress editor sidebar as "Wordsurf" panel

## Build Commands

```bash
# Development mode (watch for changes)
npm run start

# Production build
npm run build

# Linting and Formatting
npm run lint:js          # JavaScript linting
npm run lint:css         # CSS linting
npm run format           # Format code

# Testing
npm run test:unit        # Unit tests
npm run test:e2e         # End-to-end tests

# Other utilities
npm run plugin-zip       # Create distribution zip
npm run lint:md:docs     # Lint markdown documentation
npm run lint:pkg-json    # Lint package.json
npm run packages-update  # Update WordPress dependencies
```

## Architecture Overview

Wordsurf is an agentic WordPress plugin that integrates AI directly into the WordPress block editor. The system enables real-time streaming chat with AI agents that can read and manipulate WordPress content through a sophisticated tool system with diff preview capabilities.

### Key Architecture Patterns

**Singleton Plugin Pattern**: Main plugin class (`wordsurf.php`) uses singleton pattern for initialization. Core classes:
- `Wordsurf` (main plugin class at `wordsurf.php`)
- `Wordsurf_Agent_Core` (`includes/agent/core/class-agent-core.php`)
- `Wordsurf_Tool_Manager` (`includes/agent/core/class-tool-manager.php`)
- `Wordsurf_Chat_Handler` (`includes/api/class-chat-handler.php`)

**Server-Sent Events (SSE)**: All AI interactions use streaming responses for real-time user experience. The `ChatStreamSession` class manages the streaming state and tool execution pipeline.

**Integrated Tool Calling**: Tools execute during the streaming response, not after. This enables real-time feedback and immediate tool results integrated into the conversation flow.

### Tool System Architecture 

**Tool Registration Pattern**: All tools extend `Wordsurf_BaseTool` and implement:
- `get_name()`, `get_description()` - Tool metadata
- `define_parameters()` - Declarative parameter definition with required flags
- `execute($context)` - Tool logic with context from current post/conversation

**Tool Schema Generation**: Base class automatically generates function calling schemas from parameter definitions with proper strict mode handling for all supported AI providers.

**Tool Discovery**: `Wordsurf_Tool_Manager` uses on-demand tool loading - tools are only loaded when the AI actually requests them, eliminating page load overhead.

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

**Standardized Message Format**: System maintains provider-agnostic message history with proper tool message handling. Backend sanitizes messages for API compatibility while frontend maintains UI-friendly message display.

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

**AI HTTP Client Integration**: The ai-http-client library's `ContinuationManager` handles all continuation logic with provider-specific handlers:
- OpenAI: Response ID tracking with automatic extraction from streaming responses
- Other providers: Conversation history-based continuation
- Automatic state storage in WordPress transients (5-minute expiry)

**State Management**: The system maintains separate diff context and chat state to prevent UI conflicts during tool acceptance flows. Continuation state is stored per-user using WordPress transients for persistence across requests.

**EventSource Management**: Multiple EventSource connections are prevented through proper state checking in `ChatHandler` to avoid "Store already registered" errors.

## Development Patterns

**Adding New Tools**:
1. Create class extending `Wordsurf_BaseTool` in `includes/agent/core/tools/`
2. Implement required methods with proper parameter definitions  
3. Add tool to the supported tools array in `Wordsurf_Tool_Manager::load_tool()`
4. Tools are loaded on-demand when AI requests them - no manual registration needed

**Function Calling Implementation**: All tools use strict mode schemas with proper error handling and security validation. Parameters must be explicitly defined with `additionalProperties: false`.

**Streaming Responses**: All AI interactions use streaming for real-time user experience. Tools execute during streaming, not after completion.

**Diff Acceptance Pattern**: When implementing bulk operations (accept/reject all), suppress individual chat continuations and trigger a single continuation after all operations complete to prevent EventSource conflicts.

**Performance Optimization Pattern**: Tool registration uses lazy loading via WordPress filters. Tools are only loaded when the AI actually calls them through the `ai_http_client_execute_tool` filter. This eliminates the performance overhead of loading AI components on every page load, since they're only needed during active AI conversations.

## AI HTTP Client Integration

**Current Implementation**: The plugin uses the `ai-http-client` library (located at `lib/ai-http-client/`) integrated via the main plugin file. The `Wordsurf_Agent_Core` class instantiates `AI_HTTP_Client` and uses methods like `send_streaming_request()` and `continue_with_tool_results()`. This provides:

- **Multi-Provider Support**: Support for OpenAI, Anthropic, Gemini, Grok, and OpenRouter through unified interface
- **Centralized AI Logic**: Standardized request/response handling across different AI providers
- **Enhanced Features**: Built-in request retries, rate limiting, and response caching
- **Streaming Support**: Full streaming capabilities preserved with EventSource-based frontend
- **Tool Calling**: Native function calling support for all compatible providers

**Provider Configuration**:
- Configuration managed through ai-http-client's `AI_HTTP_Options_Manager` class
- Primary options: `ai_http_client_selected_provider` and `ai_http_client_providers` (nested array structure)
- Settings UI rendered via `AI_HTTP_ProviderManager_Component` in admin settings
- Each provider has dedicated normalizers for request/response formatting
- Streaming handled via provider-specific streaming modules

**Tool Integration**: WordPress tools are registered with the ai-http-client library via `Wordsurf_Tool_Manager::register_tools_with_library()` using WordPress filter system. Tool execution flows through `AI_HTTP_Tool_Executor::execute_tool()`.

**Response Continuation Pattern**: The ai-http-client library handles all continuation logic through its `ContinuationManager` and provider-specific handlers. OpenAI continuations use response ID tracking, while other providers use conversation history. The system automatically stores continuation state in WordPress transients and provides `continue_with_tool_results()` for seamless multi-turn conversations with tool interactions.

## Development Guidelines

**AI HTTP Client Changes**: 
- **NEVER make changes directly in the original ai-http-client directory** (`/Users/chubes/Sites/ai-http-client`)
- **ALWAYS make changes in the plugin's subtree copy** (`wordsurf/lib/ai-http-client/`)
- The ai-http-client is included as a git subtree in `lib/ai-http-client/`
- Use git subtree commands to sync changes back to the original repository:

```bash
# From the wordsurf plugin directory:
# Push changes back to original ai-http-client repo
git subtree push --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main

# Pull updates from original ai-http-client repo
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

This ensures changes are properly tracked in both repositories and the original ai-http-client stays up-to-date for other plugins.

**Available Tools**: Current tool implementations in `includes/agent/core/tools/`:
- `read_post.php` - Read and analyze WordPress post content
- `write_to_post.php` - Update post content with diff preview
- `edit_post.php` - Edit specific sections of post content
- `insert_content.php` - Insert new content at specified locations

**Tool Development Pattern**:
1. Create class extending `Wordsurf_BaseTool` in `includes/agent/core/tools/`
2. Implement required methods: `get_name()`, `get_description()`, `define_parameters()`, `execute()`
3. Return results with `preview: true` for diff system integration
4. Add tool name to `$supported_tools` arrays in `Wordsurf_Tool_Manager::load_tool()` and filter handlers
5. Tool Manager uses lazy loading - tools are loaded only when AI requests them via the `ai_http_client_execute_tool` filter

**Debugging**: Use `error_log('Wordsurf DEBUG: message')` for debug logging throughout the system.

## Critical Development Considerations

**Block Indexing**: Both backend and frontend must filter out empty blocks (`blockName: null`) to maintain consistent block indexing when targeting specific blocks. This is critical for the diff preview system to work correctly.

**EventSource Management**: Prevent multiple EventSource connections through proper state checking in `ChatHandler` to avoid "Store already registered" errors. Only one chat session should be active at a time.

**Git Subtree Management**: The `ai-http-client` library at `lib/ai-http-client/` is managed as a git subtree. Always make changes to the local copy, then sync back to the original repository using git subtree commands.

**WordPress Integration**: All AJAX requests must include WordPress nonces for security. Tool execution requires proper capability checks (`edit_posts`, `publish_posts`). Use WordPress sanitization functions (`wp_kses_post`, `sanitize_text_field`).

## Provider Agnostic Design

- The plugin is provider agnostic and the AI HTTP Client handles all provider-specific conversion. The plugin is ONLY responsible for what is happening on the WordPress site.