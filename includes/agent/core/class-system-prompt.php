<?php
/**
 * Wordsurf System Prompt Manager
 *
 * Integrates with AI HTTP Client's modular prompt system
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Wordsurf_System_Prompt {
    
    /**
     * Track if initialization has already been done
     */
    private static $initialized = false;
    
    /**
     * Constructor - Initialize prompt integration
     */
    public function __construct() {
        self::init();
    }
    
    /**
     * Initialize prompt integration
     */
    public static function init() {
        // Prevent duplicate initialization
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        add_action('init', [__CLASS__, 'register_tool_definitions'], 10);
        add_action('init', [__CLASS__, 'set_default_enabled_tools'], 20);
        add_filter('ai_http_client_current_plugin', [__CLASS__, 'set_current_plugin'], 10);
        add_filter('ai_http_client_inject_context', [__CLASS__, 'inject_post_context'], 10, 2);
        
        error_log('Wordsurf DEBUG: ai_http_client_inject_context filter registered');
    }
    
    /**
     * Set current plugin identifier for tool registration
     */
    public static function set_current_plugin($plugin) {
        return 'wordsurf';
    }
    
    /**
     * Inject post context into system prompt
     *
     * @param string $prompt Current system prompt
     * @param array $context Context data including post information
     * @return string System prompt with post context included
     */
    public static function inject_post_context($prompt, $context) {
        error_log('Wordsurf DEBUG: inject_post_context filter called with context: ' . json_encode($context));
        
        // Only inject context if we have context data
        if (empty($context)) {
            error_log('Wordsurf DEBUG: inject_post_context returning early - context is empty');
            return $prompt;
        }
        
        $context_section = "\n\n# CURRENT CONTEXT (For Your Reference Only - Do Not Echo This Information)\n\n";
        
        // Add site context
        if (!empty($context['site_name'])) {
            $context_section .= "Site: " . $context['site_name'];
            if (!empty($context['site_url'])) {
                $context_section .= " (" . $context['site_url'] . ")";
            }
            $context_section .= "\n";
        }
        
        // Add user context
        if (!empty($context['current_user'])) {
            $context_section .= "Current User: " . $context['current_user'] . "\n";
        }
        
        // Add post context if available
        if (!empty($context['current_post'])) {
            $post = $context['current_post'];
            
            $context_section .= "\nCURRENT POST DETAILS:\n";
            
            if (!empty($post['title'])) {
                $context_section .= "Title: " . $post['title'] . "\n";
            }
            
            if (!empty($post['id'])) {
                $context_section .= "ID: " . $post['id'] . "\n";
            }
            
            if (!empty($post['type'])) {
                $context_section .= "Type: " . $post['type'] . "\n";
            }
            
            if (!empty($post['status'])) {
                $context_section .= "Status: " . $post['status'] . "\n";
            }
            
            if (!empty($post['author'])) {
                $context_section .= "Author: " . $post['author'] . "\n";
            }
            
            if (!empty($post['date'])) {
                $context_section .= "Date: " . $post['date'] . "\n";
            }
            
            if (!empty($post['categories']) && is_array($post['categories'])) {
                $context_section .= "Categories: " . implode(', ', $post['categories']) . "\n";
            }
            
            if (!empty($post['tags']) && is_array($post['tags'])) {
                $context_section .= "Tags: " . implode(', ', $post['tags']) . "\n";
            }
            
            if (!empty($post['excerpt'])) {
                $context_section .= "Excerpt: " . $post['excerpt'] . "\n";
            }
            
            if (!empty($post['content'])) {
                $context_section .= "\nCURRENT POST CONTENT:\n" . $post['content'] . "\n";
            }
            
            $context_section .= "\nIMPORTANT: This context is provided for your reference only. When the user refers to 'this post', 'the current post', or 'the post', they are referring to the post details above. Do NOT repeat this context information back to the user unless they specifically ask for post details. Work with the content naturally and respond to their requests directly.";
        }
        
        return $prompt . $context_section;
    }
    
    /**
     * Register Wordsurf tool definitions with AI HTTP Client
     */
    public static function register_tool_definitions() {
        // Register edit_post tool
        AI_HTTP_Prompt_Manager::register_tool_definition(
            'edit_post',
            "### edit_post tool\n" .
            "- Use to make specific edits to the current post content\n" .
            "- Use when you need to modify existing text, paragraphs, or sections\n" .
            "- Use for surgical changes like fixing typos, improving sentences, or updating specific parts\n" .
            "- The tool will search for the specified text and replace it with your new content\n" .
            "- CRITICAL: NEVER modify URLs, links, images, or embeds unless specifically asked\n" .
            "- PRESERVE all HTML attributes, href values, src values, and link structures\n" .
            "- This is the PRIMARY tool for making content changes",
            ['priority' => 1, 'category' => 'content_editing']
        );
        
        // Register insert_content tool
        AI_HTTP_Prompt_Manager::register_tool_definition(
            'insert_content',
            "### insert_content tool\n" .
            "- Use to add new content at specific positions in the post\n" .
            "- Use when you need to insert new paragraphs, sections, or content blocks\n" .
            "- Use for adding content before or after existing sections\n" .
            "- Specify the position (before/after) and the target text to insert around",
            ['priority' => 2, 'category' => 'content_editing']
        );
        
        // Register write_to_post tool
        AI_HTTP_Prompt_Manager::register_tool_definition(
            'write_to_post',
            "### write_to_post tool\n" .
            "- Use to completely rewrite the entire post content\n" .
            "- Use when you need to create a completely new version of the post\n" .
            "- Use for major rewrites, style changes, or complete content overhauls\n" .
            "- This replaces the entire post content with your new version\n" .
            "- CRITICAL: When using write_to_post, do NOT include the full new content in your response message - just execute the tool",
            ['priority' => 3, 'category' => 'content_editing']
        );
        
        // Register read_post tool
        AI_HTTP_Prompt_Manager::register_tool_definition(
            'read_post',
            "### read_post tool\n" .
            "- Use to read content from OTHER posts or pages (not the current post)\n" .
            "- Use when you need to reference or link to other content on the site\n" .
            "- Use for cross-referencing or gathering information from different posts\n" .
            "- Do NOT use for the current post - its content is already available in the context\n" .
            "- Do not use to retrieve information that is already present in the initial Context provided to you (like the post title or ID)\n" .
            "- CRITICAL: The current post content is ALREADY provided in the context above - DO NOT use read_post for the current post",
            ['priority' => 4, 'category' => 'content_reading']
        );
    }
    
    /**
     * Set default enabled tools for Wordsurf
     */
    public static function set_default_enabled_tools() {
        $current_tools = AI_HTTP_Prompt_Manager::get_enabled_tools('wordsurf');
        
        // Set defaults if no tools are enabled yet
        if (empty($current_tools)) {
            $default_tools = ['edit_post', 'insert_content', 'write_to_post', 'read_post'];
            AI_HTTP_Prompt_Manager::set_enabled_tools($default_tools, 'wordsurf');
        }
    }
    
    /**
     * Get Wordsurf's base system prompt
     */
    public static function get_base_prompt() {
        return "You are a WordPress content assistant that helps users create, edit, and manage WordPress content. You have access to tools that allow you to read and analyze WordPress posts and pages.

# Agentic Instructions

## Persistence
You are an agent - please keep going until the user's query is completely resolved, before ending your turn and yielding back to the user. Only terminate your turn when you are sure that the problem is solved.

## Tool-Calling
If you are not sure about post content or structure pertaining to the user's request, use your tools to read files and gather the relevant information: do NOT guess or make up an answer. Always use tools to get accurate information about the current post content.

## Planning
You MUST plan extensively before each function call, and reflect extensively on the outcomes of the previous function calls. DO NOT do this entire process by making function calls only, as this can impair your ability to solve the problem and think insightfully.

HOWEVER: Keep your planning messages CONCISE. Describe your approach without quoting long passages or including full content. The user needs to know what you're doing, not see duplicate content.

# Workflow

## High-Level Problem Solving Strategy
1. Understand the user's request deeply. Carefully read the request and think critically about what is required.
2. Investigate the current post content using available tools. Read the post to understand what's currently there.
3. Develop a clear, step-by-step plan. Break down the task into manageable, incremental steps.
4. Execute your plan using tools as needed.
5. Provide comprehensive, actionable advice based on what you find.
6. Ensure the user's request is fully addressed before concluding.

## Response Guidelines
- Be specific, detailed, and concise in your responses
- Provide actionable advice based on the content you read
- Always reference the actual content you found
- If you can't answer based on available information, say so clearly
- Keep working until the user's request is fully addressed

## Response and Content Guidelines
- NEVER include full post content in your responses - the user can see it in the editor
- Keep responses concise and focused on what you're doing, not showing content
- When planning rewrites, describe your approach without quoting extensive text
- DO NOT paste paragraphs, articles, or long text blocks in your messages

## Tool Result Guidelines  
- When tools like edit_post, insert_content, or write_to_post return results, DO NOT describe the specific changes or diff details in your response
- Simply provide a brief confirmation that the tool was executed and the user will see the changes highlighted in the editor
- DO NOT repeat or describe the actual text changes, replacements, or insertions
- The UI overlay will handle showing the user the specific changes
- Keep your response brief and focused on the successful execution, not the content details

## Follow-up Guidelines  
- CRITICAL: ALWAYS provide a follow-up message after executing tools, explaining what was done and next steps
- When tools return results, explain that the user will see highlighted changes they can accept or reject
- If any tool calls fail, you MUST explain the failure to the user and suggest alternatives
- Report on ALL tool executions - both successes and failures - in your follow-up response
- If a pattern doesn't match in edit_post, help the user understand why and suggest corrected patterns";
    }
    
    /**
     * Build complete system prompt using modular AI HTTP Client system
     */
    public function build_prompt($post_context = [], $available_tools = []) {
        // Get user's custom system prompt from UI component
        $options_manager = new AI_HTTP_Options_Manager('wordsurf');
        $provider = $options_manager->get_selected_provider();
        $user_system_prompt = $options_manager->get_provider_setting($provider, 'system_prompt', '');
        
        // Use user's system prompt if provided, otherwise fall back to Wordsurf's base prompt
        $base_prompt = !empty($user_system_prompt) ? $user_system_prompt : self::get_base_prompt();
        
        // Pass the original context structure (don't flatten it)
        // The filter expects the nested structure with current_post
        $context = $post_context;
        
        // Debug: Log the context being passed to the prompt builder
        error_log('Wordsurf DEBUG: Context passed to build_modular_system_prompt: ' . json_encode($context));
        
        // Use modular prompt builder with Wordsurf-specific settings
        $final_prompt = AI_HTTP_Prompt_Manager::build_modular_system_prompt(
            $base_prompt,
            $context,
            [
                'include_tools' => true,
                'tool_context' => 'wordsurf',
                'enabled_tools' => array_keys($available_tools),
                'sections' => []
            ]
        );
        
        // Debug: Log the final prompt being sent
        error_log('Wordsurf DEBUG: Final system prompt length: ' . strlen($final_prompt) . ' characters');
        error_log('Wordsurf DEBUG: Final system prompt preview: ' . substr($final_prompt, 0, 500) . '...');
        
        return $final_prompt;
    }
}