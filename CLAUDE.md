# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## KNOWN ISSUES

- None currently identified

## FUTURE PLANS

- Unit test implementation for AI tools and streaming functionality
- End-to-end test coverage for complete editor workflow
- Additional AI tool development for advanced content manipulation

## Development Commands

### Core Development Workflow
```bash
npm install              # Install dependencies
npm run start           # Development with hot reload
npm run build           # Production build
npm run lint:js         # JavaScript linting
npm run lint:css        # CSS linting
npm run format          # Code formatting
```

### Testing Commands
```bash
npm run test:unit       # Unit tests (Jest-based)
npm run test:e2e        # End-to-end tests
```

### AI HTTP Client Library Updates
```bash
# Update embedded AI client library (git subtree)
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

## Code Architecture

### Core Architecture Pattern
Wordsurf is an AI-powered WordPress content editing plugin built around:
- **Agent-Based Architecture**: AI agent with specialized tools for content manipulation
- **Streaming Communication**: Real-time AI responses via Server-Sent Events
- **Tool System**: Extensible AI capabilities through modular tools
- **Multi-Provider AI Support**: OpenAI, Anthropic, Google Gemini, Grok, OpenRouter

### Key Architectural Components

**AI Agent Core** (`/includes/agent/core/`)
- `class-agent-core.php` - Main AI orchestrator with tool management
- `class-chat-handler.php` - Streaming chat and conversation management
- `class-system-prompt.php` - Dynamic AI prompt building with WordPress context
- `class-tool-manager.php` - Tool registration and execution

**Tool System** (`/includes/agent/core/tools/`)
- Abstract `basetool.php` base class for all AI tools
- `read_post.php` - Read content from WordPress posts
- `edit_post.php` - Surgical content editing with diff visualization
- `write_to_post.php` - Write new content to posts
- `insert_content.php` - Insert content at specific locations

**Frontend Architecture** (`/src/js/`)
- `WordsurfPlugin.js` - WordPress Gutenberg sidebar integration
- `ChatHandler.js` - Streaming chat session management
- `DiffTracker.js` - Pending change tracking and state management
- `InlineDiffManager.js` - Visual diff blocks with accept/reject workflow

### WordPress Integration Patterns
- **Hook-Based Dependencies**: Uses WordPress filters for extensibility
- **PSR-4 Alternative**: Follows WordPress coding standards instead of PSR-4
- **Security**: WordPress nonces, sanitization, and escaping throughout
- **Gutenberg Integration**: Custom diff block and sidebar plugin

### AI HTTP Client Integration
The plugin includes a sophisticated AI client library (`/lib/ai-http-client/`) with:
- **Multi-Type AI Architecture**: LLM, Upscaling, Generative AI support via required `ai_type` parameter
- **Plugin-Scoped Configuration**: Isolated settings per WordPress plugin
- **Unified Provider Interface**: Same client class for all AI providers
- **WordPress-Native**: Uses `wp_remote_post()` and WordPress options system
- **No Fallbacks**: Explicit `ai_type` parameter required for all instantiations

### AI Type Architecture
The AI HTTP Client library requires explicit `ai_type` parameter specification for all operations. This enables multi-type AI support within a single unified interface.

**Supported AI Types**:
- `'llm'` - Large Language Models (OpenAI GPT, Anthropic Claude, Google Gemini, etc.)
- `'upscaling'` - Image upscaling and enhancement services
- `'generative'` - Generative AI for image/content creation

**Required Configuration Pattern**:
```php
// ALL instantiations require ai_type parameter
$client = new AI_HTTP_Client([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm'  // REQUIRED: no defaults
]);
```

**Wordsurf Implementation**:
Wordsurf exclusively uses `'llm'` type for content editing and manipulation:
```php
// Agent Core configuration (includes/agent/core/class-agent-core.php)
$config['ai_type'] = 'llm';
$this->ai_client = new AI_HTTP_Client($config);

// Settings integration (includes/admin/class-settings.php)
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm',
    'components' => ['provider_selector', 'api_key_input', 'model_selector']
]);
```

## Build System

### Webpack Configuration
Three entry points managed by `@wordpress/scripts`:
- `editor` → `assets/js/editor.js` (Gutenberg sidebar plugin)
- `admin` → `assets/js/admin.js` (Admin interface)
- `diff-block` → `assets/js/diff-block.js` (Custom Gutenberg block)

### Development Server
- Hot reload enabled via `npm run start`
- Automatic asset building and WordPress integration
- Live CSS/JS updates without page refresh

## WordPress Development Standards

### Tool Development Pattern
All AI tools extend the abstract `basetool.php` class:
```php
abstract class Wordsurf_Agent_Tool_BaseTool {
    abstract public function get_name();
    abstract public function get_description();
    abstract public function get_parameters();
    abstract public function execute($args);
}
```

### API Endpoint Pattern
REST API endpoints follow WordPress conventions:
```php
register_rest_route('wordsurf/v1', '/endpoint', [
    'methods' => 'POST',
    'callback' => [$this, 'handle_request'],
    'permission_callback' => [$this, 'check_permissions']
]);
```

### Settings Integration
AI provider settings use the embedded AI HTTP Client components:
```php
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm',
    'components' => ['provider_selector', 'api_key_input', 'model_selector']
]);
```

## Key File Structure

```
wordsurf.php                 # Main plugin file with singleton pattern
includes/
├── admin/                   # WordPress admin interface
├── agent/core/             # AI agent and tool system
├── api/                    # REST API handlers
└── blocks/diff/            # Custom Gutenberg diff block
src/js/                     # Source JavaScript (React/WordPress components)
assets/                     # Built assets (generated, don't edit)
lib/ai-http-client/         # AI client library (git subtree)
```

## Development Notes

### Critical Architecture Decisions
- **No Fallbacks**: Clean architecture without legacy support code
- **Filter-Based Dependencies**: WordPress filters preferred over complex DI
- **Streaming-First**: All AI communication designed for real-time streaming
- **Tool Extensibility**: New AI capabilities added via tool registration
- **Explicit AI Types**: All AI client instantiations require mandatory `ai_type` parameter
- **LLM-Only Integration**: Wordsurf exclusively uses `'llm'` type for content operations

### WordPress-Specific Patterns
- All database operations use WordPress functions (`get_option`, `update_post_meta`)
- Security through WordPress nonces and capability checks
- Gutenberg integration via `@wordpress/components` and hooks
- Admin interfaces use WordPress admin UI components

### AI Provider Architecture
The multi-provider system supports:
- **OpenAI**: GPT models with Responses API streaming
- **Anthropic**: Claude models with conversation rebuilding for streaming
- **Google Gemini**: 2025 API with native streaming support
- **Grok/X.AI**: With reasoning_effort parameter support
- **OpenRouter**: 100+ models via unified streaming API

### AI Type Configuration Requirements

**Mandatory Parameters**:
All AI HTTP Client usage requires both `plugin_context` and `ai_type`:
```php
// CORRECT - Both parameters specified
$client = new AI_HTTP_Client([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm'
]);

// ERROR - Missing ai_type parameter
$client = new AI_HTTP_Client([
    'plugin_context' => 'wordsurf'
]); // Throws Exception: ai_type parameter is required
```

**Component Integration**:
All ProviderManager components require both parameters:
```php
// Settings UI components
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'wordsurf',  // REQUIRED
    'ai_type' => 'llm',             // REQUIRED
    'components' => ['provider_selector', 'api_key_input', 'model_selector']
]);
```

**Testing Components**:
Connection testing requires explicit ai_type specification:
```php
// Test connection component
echo AI_HTTP_Test_Connection_Component::render([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm'  // REQUIRED for proper provider routing
]);
```

### Testing Considerations
- Unit tests should be added to `/tests/` directory (currently empty)
- E2E tests should cover AI tool functionality and streaming
- AI HTTP Client library has comprehensive testing suite

### Security Requirements
- All AI provider API keys stored in WordPress options (encrypted)
- User capability checks for all admin operations
- WordPress nonces for all AJAX/API requests
- Content sanitization and escaping for all outputs