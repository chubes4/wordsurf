# AI HTTP Client Migration Completion Plan

## Overview
Complete the migration from legacy OpenAI-specific implementation to the ai-http-client library for full multi-provider support.

## High Priority Tasks

### 1. Fix Legacy Provider Option Detection
- **File**: `includes/agent/core/class-agent-core.php:258`
- **Issue**: Still uses `get_option('wordsurf_ai_provider', 'openai')`
- **Solution**: Replace with `AI_HTTP_Options_Manager::get_selected_provider()`
- **Impact**: Ensures proper provider detection across all supported AI providers

### 2. Replace OpenAI-Specific Tool Extraction
- **File**: `includes/agent/core/class-agent-core.php:305`
- **Issue**: Direct call to `AI_HTTP_OpenAI_Streaming_Module::extract_tool_calls()`
- **Solution**: Use provider-agnostic extraction method from ai-http-client
- **Impact**: Enables tool calling to work with all providers, not just OpenAI

## Medium Priority Tasks

### 3. Fix JavaScript Parameter Naming
- **File**: `src/js/agent/core/ChatStreamSession.js:26`
- **Issue**: Parameter named `openaiMessages` 
- **Solution**: Rename to `messages` for provider neutrality
- **Impact**: Frontend API becomes provider-agnostic

### 4. Test Multi-Provider Functionality
- **Goal**: Verify migration works with different AI providers
- **Tasks**:
  - Test with OpenAI (baseline)
  - Test with Anthropic Claude
  - Test with other supported providers
  - Verify tool calling works across providers
  - Check streaming functionality

## Low Priority Tasks

### 5. Clean Up Deprecated Code
- **File**: `includes/api/class-api-base.php`
- **Action**: Remove if no longer used, or mark clearly as deprecated
- **Impact**: Reduces codebase confusion and maintenance burden

### 6. Update Documentation
- **File**: `docs/project-architecture.md:85`
- **Issue**: References OpenAI-specific Responses API
- **Solution**: Update to reflect multi-provider architecture
- **Impact**: Documentation accuracy for future developers

### 7. Update CLAUDE.md
- **Action**: Document completed migration and add guidelines
- **Content**: Add section about ensuring provider-agnostic development
- **Impact**: Future development maintains provider neutrality

## Validation Criteria

### Migration Complete When:
- [ ] No direct references to specific provider options
- [ ] All tool extraction uses provider-agnostic methods
- [ ] JavaScript APIs use provider-neutral naming
- [ ] Multi-provider testing passes
- [ ] Documentation reflects current architecture
- [ ] No deprecated API classes remain

### Success Metrics:
- Plugin works with OpenAI, Anthropic, and other providers
- Tool calling functions across all providers
- Streaming works regardless of provider choice
- No provider-specific code outside ai-http-client library
- Frontend maintains provider-agnostic interface

## Technical Notes

### Provider-Agnostic Patterns:
- Use `AI_HTTP_Options_Manager` for configuration
- Use ai-http-client's unified tool extraction
- Maintain provider-neutral naming conventions
- Delegate provider-specific logic to ai-http-client library

### Testing Approach:
- Test each provider individually
- Verify tool calling with complex multi-step scenarios
- Check streaming performance across providers
- Validate error handling works uniformly