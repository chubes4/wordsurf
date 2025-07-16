# Wordsurf Development Plan

## Critical Architecture Issue Identified 🚨

### Root Problem
The AI HTTP Client library has a **fundamental architectural flaw** from day one:
- Providers are "smart" components doing API + normalization + streaming + tools
- Should be "dumb" API clients with centralized normalization
- This violates Single Responsibility Principle and causes debugging nightmares

### Current Status
- Tool execution failing with "Invalid arguments for tool: edit_post"
- No diff previews appearing (result.preview = undefined)
- Architecture makes debugging nearly impossible
- 3000+ lines of duplicated logic across 5 providers

### Clean Slate Refactor Required
**Target Architecture**:
```
WordPress Plugin (unchanged - uses standard format)
    ↓
AI HTTP Client Library (complete rebuild)
├── providers/openai.php (30 lines - dumb API client)
├── providers/gemini.php (30 lines - dumb API client)  
└── providers/normalizers/
    ├── RequestNormalizer.php (switch/case all providers)
    ├── ResponseNormalizer.php (switch/case all providers)
    └── StreamNormalizer.php (switch/case all providers)
```

## Pre-Refactor Status (Bachelor Party Break)

### What We Fixed
- ✅ Removed fake tool results from all provider StreamingModules
- ✅ Identified architectural root cause
- ✅ Current system streams properly but tool execution fails

### Current Issues (Post-Refactor)
- Tool execution: "Invalid arguments for tool: edit_post"  
- No diff previews: `result.preview = undefined`
- Frontend expects proper diff data structure but tool execution failing

### Architecture Analysis Complete
- **Problem**: 3000+ lines of duplicated provider logic
- **Solution**: Clean slate with dumb providers + universal normalizers
- **Benefit**: Single responsibility, easier debugging, cleaner codebase

## Post-Bachelor Party: Clean Slate Refactor Plan

### Phase 1: Architecture Rebuild (1 week)
1. **Nuke existing provider directories** (OpenAI/, Gemini/, etc.)
2. **Create dumb API clients** (providers/openai.php, providers/gemini.php)
3. **Build universal normalizers** with switch/case logic:
   - `providers/normalizers/RequestNormalizer.php`
   - `providers/normalizers/ResponseNormalizer.php` 
   - `providers/normalizers/StreamNormalizer.php`
4. **Test with Gemini first**, then add other providers

### Phase 2: WordPress Integration (2-3 days)
1. **Update main AI_HTTP_Client class** to use new architecture
2. **Test tool execution** with clean request/response flow
3. **Verify diff previews work** with proper data structure
4. **Test all providers** work with new system

### Benefits of Clean Slate
- **Debugging**: Clear separation of API vs normalization issues
- **Maintenance**: Bug fixes in one place, not 5 places
- **Adding providers**: Just add API client + normalizer cases
- **Code quality**: Single responsibility principle followed
- **Performance**: Less duplicate logic, cleaner execution flow

### Salvageable Code
- ✅ WordPress plugin (unchanged - uses standard format)
- ✅ Existing switch/case patterns from GenericStreamNormalizer
- ✅ Basic validation/sanitization logic
- ✅ Tool execution pipeline (WordPress side)

## Refactor Plan (If Decided Later)

### Goal: Clean 3-Layer Architecture
1. **API Layer**: Providers only do raw API communication
2. **Standardization Layer**: Universal normalizers handle all format conversion  
3. **WordPress Layer**: Handle plugin-specific logic and tool execution

### Key Changes:
- Move all `extract_tool_calls` logic to single Universal normalizer
- Move all tool processing logic to single Universal processor  
- Strip providers down to ~50 lines of pure API communication
- Centralize WordPress-specific logic

### Implementation Strategy:
- Create universal components alongside existing ones
- Migrate one provider as test case
- Apply pattern to remaining providers
- Remove old components after validation

### File Structure Target:
```
lib/ai-http-client/src/
├── Providers/
│   ├── Gemini/Provider.php (50 lines - API only)
│   ├── OpenAI/Provider.php (50 lines - API only)
│   └── ... (all slim)
├── Utils/
│   └── Normalizers/
│       └── UniversalNormalizer.php (handles all formats)
└── WordPress/
    └── ToolProcessor.php (WordPress-specific logic)
```

## Success Criteria

### For Minimal Fix:
- [ ] Tool calls execute after streaming completes
- [ ] Diff previews appear in WordPress editor
- [ ] Tool execution works across all providers
- [ ] System is stable and debuggable

### For Full Refactor (if pursued):
- [ ] Tool processing logic exists in exactly 1 place  
- [ ] Each provider under 100 lines
- [ ] Bug fixes only needed in 1 location
- [ ] Clear debugging path through 3 layers

## Next Steps

1. **Implement Path A (minimal fix)**
2. **Get fully working system**  
3. **Use system in production for evaluation**
4. **Reassess refactor necessity after real usage**

The goal is working software first, perfect architecture second.