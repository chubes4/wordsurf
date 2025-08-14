<?php
/**
 * Wordsurf System Prompt Manager
 *
 * Simplified system prompt building for filter-based architecture
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Wordsurf_System_Prompt {
    
    /**
     * Constructor - Simplified for filter-based architecture
     */
    public function __construct() {
        // No initialization needed in filter-based architecture
    }

    /**
     * Add post context to a system prompt
     *
     * @param string $prompt Base system prompt
     * @param array $context Context data including post information
     * @return string System prompt with post context included
     */
    private function inject_post_context($prompt, $context) {
        // Only inject context if we have context data
        if (empty($context)) {
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
     * Get Wordsurf's base system prompt
     */
    public function get_base_prompt() {
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
     * Build complete system prompt for filter-based architecture
     * 
     * @param array $post_context Context data including post information
     * @param array $available_tools Available tool definitions
     * @return string Complete system prompt with context
     */
    public function build_prompt($post_context = [], $available_tools = []) {
        // Get user's custom system prompt from WordPress options
        $user_system_prompt = get_option('wordsurf_ai_system_prompt', '');
        
        // Use user's system prompt if provided, otherwise fall back to Wordsurf's base prompt
        $base_prompt = !empty($user_system_prompt) ? $user_system_prompt : $this->get_base_prompt();
        
        // Add tool descriptions if available
        if (!empty($available_tools)) {
            $base_prompt .= "\n\n# Available Tools\n\nYou have access to the following tools:";
            foreach ($available_tools as $tool_name => $tool_info) {
                $base_prompt .= "\n- **{$tool_name}**: " . ($tool_info['description'] ?? 'No description available');
            }
        }
        
        // Inject post context
        $final_prompt = $this->inject_post_context($base_prompt, $post_context);
        
        error_log('Wordsurf DEBUG: Final system prompt length: ' . strlen($final_prompt) . ' characters');
        
        return $final_prompt;
    }
}