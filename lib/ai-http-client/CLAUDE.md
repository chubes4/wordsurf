# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for AI provider communication using pure filter architecture.

## Development Commands

### Static Analysis
```bash
# Run PHPStan static analysis (level 5)
composer analyse

# Check PHP syntax for individual files
php -l src/Providers/LLM/openai.php
```

### Debug Logging
```bash
# Enable debug logging in WordPress (wp-config.php)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

# View debug logs (typical location)
tail -f /wp-content/debug.log

# Disable debug logging for production
define('WP_DEBUG', false);
```

### Git Subtree Operations (Primary Distribution Method)
```bash
# Add as subtree to a WordPress plugin
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Update existing subtree
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Push changes back (from main repo)
git subtree push --prefix=lib/ai-http-client origin main
```

## Core Architecture

### Pure Filter Architecture
The library uses WordPress filters exclusively for all operations:

**Core Filters**:
```php
// Provider discovery
$providers = apply_filters('ai_providers', []);

// HTTP requests 
$result = apply_filters('ai_http', [], 'POST', $url, $args, 'context');

// AI requests
$result = apply_filters('ai_request', null, $request_data, $provider);

// Model discovery
$models = apply_filters('ai_models', $provider, $config);

// API key management
$keys = apply_filters('ai_provider_api_keys', null);
```

### Self-Contained Provider Architecture
**Individual Provider Classes**:
- `AI_HTTP_OpenAI_Provider` - OpenAI Responses API
- `AI_HTTP_Anthropic_Provider` - Anthropic Messages API  
- `AI_HTTP_Gemini_Provider` - Google Gemini API
- `AI_HTTP_Grok_Provider` - xAI Grok API
- `AI_HTTP_OpenRouter_Provider` - OpenRouter API

**Key Design Principles**:
- Self-contained format conversion within each provider
- No external normalizers or utilities
- Filter-based registration for provider discovery
- Standardized interface across all providers

### Simplified Loading System
**Single Loading Strategy**:
- **Composer Autoloader Required** - No fallback loading
- Direct file inclusion for providers and filters
- Error logging if Composer not available

### Request Flow Architecture
```
Filter Request → Provider Class → API Call → Standardized Response

apply_filters('ai_request', null, $request_data, $provider)
├── Provider::request() -> Self-contained format conversion
├── Provider HTTP call via ai_http filter
└── Provider::format_response() -> Standard format return
```

**Simplified Flow**:
- Direct filter-based provider access
- Self-contained format conversion within providers
- No external normalizers or complex routing
- Standardized response format across all providers

## Key Implementation Patterns

### Error Handling Philosophy
- **Fail Fast**: No defaults, explicit configuration required
- **Clear Errors**: Provider-specific error messages with context
- **Graceful Degradation**: Plugins log errors rather than fatal exceptions

### WordPress Integration
- Uses `wp_remote_post()` for HTTP requests (with cURL fallback for streaming)
- WordPress options system for configuration storage
- Plugin-aware admin components with zero styling
- Follows WordPress coding standards and security practices

### Template System
**WordPress-Native Templates**:
- Template-based UI components using PHP includes
- Located in `src/templates/` directory
- Standard WordPress template variables and escaping
- Core templates: core.php, max-tokens.php, system-prompt.php, temperature.php

**Template Rendering**:
```php
// Template variables extracted from configuration
extract($template_vars);
include AI_HTTP_CLIENT_PATH . '/src/templates/core.php';
```

### Debug Logging
- **Conditional Logging**: Debug logs only appear when `WP_DEBUG` is `true` in WordPress configuration
- **Production Safety**: Prevents unnecessary log generation in production environments
- **Comprehensive Coverage**: Provides detailed information for:
  - API request/response cycles in providers (OpenAI, Anthropic, etc.)
  - Tool execution and validation in ToolExecutor
  - Streaming SSE events and connection handling in WordPressSSEHandler
  - System events during development and troubleshooting
- **WordPress Native**: Uses WordPress native `error_log()` function for consistent logging
- **Performance Optimized**: Debug checks use `defined('WP_DEBUG') && WP_DEBUG` pattern to minimize overhead

### Security Considerations
- API keys stored in WordPress options with proper sanitization
- No hardcoded credentials or defaults
- Input sanitization via WordPress functions
- Plugin context validation prevents unauthorized access

## Usage Patterns

### Filter-Based Access
```php
// Core filters for library access
$providers = apply_filters('ai_providers', []);
$result = apply_filters('ai_request', null, $request_data, $provider);
$models = apply_filters('ai_models', $provider, $config);
$api_keys = apply_filters('ai_provider_api_keys', null);
```

### Direct Provider Access
```php
// Get provider instance directly
$providers = apply_filters('ai_providers', []);
if (isset($providers['openai'])) {
    $provider_class = $providers['openai']['class'];
    $provider = new $provider_class($config);
    $result = $provider->request($standard_request);
}
```

## Distribution & Integration

### WordPress Plugin Integration
**Simple Include Pattern**:
```php
// In plugin main file
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// Usage via filters
$result = apply_filters('ai_request', null, [
    'messages' => [['role' => 'user', 'content' => 'Hello']],
    'model' => 'gpt-4',
    'max_tokens' => 100
], 'openai');
```

### Requirements
- Composer autoloader must be available
- WordPress environment for filter system
- Provider-specific API keys via filter configuration

### Production Deployment
**WordPress Configuration Requirements**:
- **Set `WP_DEBUG` to `false`** in production environments to disable debug logging
- Ensure proper WordPress security settings and API key protection
- Verify all provider API keys are properly configured before deployment

## Current Version: 1.1.1

### Adding New Providers
1. Create provider class implementing standardized interface:
   - `is_configured()` - Check if provider has required configuration
   - `request($standard_request)` - Send non-streaming request with internal format conversion
   - `streaming_request($standard_request, $callback)` - Send streaming request
   - `get_raw_models()` - Retrieve available models
   - `upload_file($file_path, $purpose)` - Files API integration
2. Register provider via `ai_providers` WordPress filter
3. Self-contained format conversion within provider class
4. Add provider file to loading in `ai-http-client.php`

**Provider Implementation**: Self-contained classes with internal format conversion (~300-400 lines typical)

## Streaming & Advanced Features

### Streaming Support
- Uses cURL for real-time streaming responses via `ai_http` filter
- WordPress `wp_remote_post()` fallback for non-streaming requests
- Provider-specific streaming implementation within each provider class

### Tool/Function Calling
- Unified tool format across all providers
- Provider-specific tool normalization within each provider
- Self-contained tool format conversion (OpenAI vs Anthropic formats)

### File Upload System
- Files API integration for providers that support it
- Direct file uploads without base64 encoding
- Provider-specific file upload implementation

This simplified architecture enables WordPress plugin developers to integrate AI providers with minimal configuration while maintaining clean separation between providers and standardized responses.