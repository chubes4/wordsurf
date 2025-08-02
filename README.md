# Wordsurf

An AI-powered WordPress content editing plugin that integrates sophisticated AI agents directly into the WordPress Gutenberg editor for real-time content creation and manipulation.

## Overview

Wordsurf transforms WordPress content creation by embedding AI agents with specialized tools directly into the editor interface. It provides a streaming chat experience where AI agents can read, edit, and create content using a sophisticated tool system while maintaining full visibility of changes through visual diff interfaces.

## Key Features

- **AI Agent Integration**: Sophisticated AI orchestrator with specialized tools for content manipulation
- **Real-time Streaming Chat**: Server-Sent Events for immediate AI responses within the editor
- **Visual Diff System**: Accept/reject workflow for AI-proposed content changes with inline diff visualization
- **Multi-Provider AI Support**: OpenAI, Anthropic Claude, Google Gemini, Grok, and OpenRouter integration
- **Tool-Based Architecture**: Extensible AI capabilities through modular tool system
- **WordPress Native**: Deep Gutenberg integration with custom blocks and sidebar components
- **Content-Aware Context**: AI agents understand WordPress post structure and metadata

### AI Capabilities

The plugin includes specialized AI tools for:
- **Reading Post Content**: Extract and analyze existing WordPress post content
- **Surgical Content Editing**: Precise content modifications with diff visualization
- **Content Generation**: Create new content sections and complete posts
- **Content Insertion**: Insert content at specific locations within posts

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Node.js 16+ (for development)
- AI provider API key (OpenAI, Anthropic, Google, etc.)

## Installation

1. **Download and Install**
   ```bash
   # Clone or download to your WordPress plugins directory
   cd wp-content/plugins/
   git clone [repository-url] wordsurf
   ```

2. **Install Dependencies**
   ```bash
   cd wordsurf
   npm install
   ```

3. **Build Assets**
   ```bash
   npm run build
   ```

4. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "Wordsurf"

5. **Configure AI Provider**
   - Navigate to WordPress Admin → Wordsurf
   - Select your AI provider (OpenAI, Anthropic, etc.)
   - Enter your API key
   - Choose your preferred model

## Quick Start

1. **Open WordPress Editor**
   - Create or edit any post/page
   - Open the Gutenberg editor

2. **Access Wordsurf**
   - Look for the Wordsurf panel in the editor sidebar
   - Click to open the AI chat interface

3. **Start Interacting**
   - Type natural language instructions about content changes
   - AI agent will propose changes with visual diffs
   - Accept or reject individual changes as needed

### Example Usage

```
User: "Rewrite the introduction paragraph to be more engaging"
AI: [Analyzes content and proposes rewrite with visual diff]
User: [Accepts/rejects changes via diff interface]

User: "Add a conclusion section about the benefits of this approach"
AI: [Generates new content and shows insertion point]
User: [Reviews and accepts the new content]
```

## Architecture

### Core Components

**AI Agent System** (`/includes/agent/`)
- `Agent Core`: Main AI orchestrator with tool management
- `Chat Handler`: Streaming communication and session management
- `Tool Manager`: Registration and execution of AI capabilities
- `System Prompt`: Dynamic AI prompt building with WordPress context

**Frontend Integration** (`/src/js/`)
- `WordsurfPlugin.js`: Gutenberg sidebar integration
- `ChatHandler.js`: Real-time streaming chat interface
- `DiffTracker.js`: Change tracking and state management
- `InlineDiffManager.js`: Visual diff blocks with user controls

**AI HTTP Client Library** (`/lib/ai-http-client/`)
- Multi-type AI architecture supporting LLM, Upscaling, and Generative AI
- Unified interface for multiple AI providers
- WordPress-native implementation using `wp_remote_post()`

### Tool System

All AI tools extend the base tool class:

```php
abstract class Wordsurf_Agent_Tool_BaseTool {
    abstract public function get_name();
    abstract public function get_description();
    abstract public function get_parameters();
    abstract public function execute($args);
}
```

Available tools:
- `read_post.php`: Read content from WordPress posts
- `edit_post.php`: Surgical content editing with diff visualization
- `write_to_post.php`: Write new content to posts
- `insert_content.php`: Insert content at specific locations

## Development

### Development Commands

```bash
# Install dependencies
npm install

# Development with hot reload
npm run start

# Production build
npm run build

# Linting
npm run lint:js
npm run lint:css

# Code formatting
npm run format

# Testing
npm run test:unit
npm run test:e2e
```

### File Structure

```
wordsurf.php                 # Main plugin file
includes/
├── admin/                   # WordPress admin interface
├── agent/core/             # AI agent and tool system
├── api/                    # REST API handlers
└── blocks/diff/            # Custom Gutenberg diff block
src/js/                     # Source JavaScript (React components)
assets/                     # Built assets (generated)
lib/ai-http-client/         # AI client library (git subtree)
```

### Adding New AI Tools

1. Create new tool file in `/includes/agent/core/tools/`
2. Extend `Wordsurf_Agent_Tool_BaseTool`
3. Implement required abstract methods
4. Register tool in `Wordsurf_Tool_Manager`

### AI Provider Configuration

The plugin uses a multi-type AI architecture requiring explicit `ai_type` specification:

```php
// LLM Configuration (for content editing)
$client = new AI_HTTP_Client([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm'
]);

// Settings Integration
echo AI_HTTP_ProviderManager_Component::render([
    'plugin_context' => 'wordsurf',
    'ai_type' => 'llm',
    'components' => ['provider_selector', 'api_key_input', 'model_selector']
]);
```

## AI Provider Support

### Supported Providers

- **OpenAI**: GPT models with Responses API streaming
- **Anthropic**: Claude models with conversation rebuilding
- **Google Gemini**: 2025 API with native streaming support
- **Grok (X.AI)**: With reasoning_effort parameter support
- **OpenRouter**: 100+ models via unified streaming API

### Provider Features

| Provider | Streaming | Tool Calling | Reasoning |
|----------|-----------|--------------|-----------|
| OpenAI | ✅ | ✅ | ✅ |
| Anthropic | ✅ | ✅ | ✅ |
| Google Gemini | ✅ | ✅ | ✅ |
| Grok | ✅ | ✅ | ✅ |
| OpenRouter | ✅ | ✅ | Varies |

## Security

- WordPress capability checks for all admin operations
- Nonce verification for all AJAX/API requests
- Content sanitization and escaping for all outputs
- API keys stored securely in WordPress options
- User permission validation for content modifications

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Add/update tests as needed
5. Submit a pull request

### Coding Standards

- Follow WordPress PHP coding standards
- Use WordPress native functions when available
- Implement proper security measures (nonces, sanitization, escaping)
- Add translator comments for all translatable strings
- Maintain modular architecture with single responsibility principle

### AI HTTP Client Updates

The plugin includes the AI HTTP Client as a git subtree:

```bash
# Update embedded AI client library
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

## Troubleshooting

### Common Issues

**AI Not Responding**
- Verify API key configuration
- Check provider service status
- Review browser console for errors

**Editor Integration Issues**
- Clear browser cache
- Rebuild assets with `npm run build`
- Check WordPress version compatibility

**Diff Blocks Not Showing**
- Ensure Gutenberg is enabled
- Verify plugin assets are loaded
- Check for JavaScript errors

### Debug Mode

Enable WordPress debug mode for detailed error reporting:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Support

- **Documentation**: [CLAUDE.md](CLAUDE.md) for technical details
- **Issues**: Report bugs and feature requests via GitHub issues
- **Developer**: Chris Huber - https://chubes.net

---

**Version**: 0.1.0  
**Author**: Chris Huber  
**Plugin URI**: https://chubes.net/wordsurf