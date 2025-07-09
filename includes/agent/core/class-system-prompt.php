<?php
/**
 * Wordsurf System Prompt Manager
 *
 * Handles the construction and management of system prompts for the AI agent.
 *
 * @package Wordsurf
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Wordsurf_System_Prompt {
    
    /**
     * Get the base system prompt
     *
     * @return string
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

# Workflow

## High-Level Problem Solving Strategy
1. Understand the user's request deeply. Carefully read the request and think critically about what is required.
2. Investigate the current post content using available tools. Read the post to understand what's currently there.
3. Develop a clear, step-by-step plan. Break down the task into manageable, incremental steps.
4. Execute your plan using tools as needed.
5. Provide comprehensive, actionable advice based on what you find.
6. Ensure the user's request is fully addressed before concluding.

## When to Use Tools
- ALWAYS use the read_post tool when a user asks about post content
- Use tools to gather information before making suggestions
- Use tools to verify current state before proposing changes
- If you need to understand what's in a post before making suggestions, use the appropriate tools.
- - Do not use tools to retrieve information that is already present in the initial Context provided to you (like the post title or ID).

## Response Guidelines
- Be specific and detailed in your responses
- Provide actionable advice based on the content you read
- Always reference the actual content you found
- If you can't answer based on available information, say so clearly
- Keep working until the user's request is fully addressed";
    }
    
    /**
     * Build a complete system prompt with context and tools
     *
     * @param array $context Optional context information
     * @param array $available_tools Optional list of available tools
     * @return string The complete system prompt
     */
    public function build_prompt($context = [], $available_tools = []) {
        $prompt = $this->get_base_prompt();
        
        // Add context information if provided
        if (!empty($context)) {
            $prompt .= "\n\n" . $this->format_context($context);
        }
        
        // Add tool information if available
        if (!empty($available_tools)) {
            $prompt .= "\n\n" . $this->format_tools($available_tools);
        }
        
        return $prompt;
    }
    
    /**
     * Format context information for the prompt
     *
     * @param array $context
     * @return string
     */
    private function format_context($context) {
        $parts = [];
        
        if (isset($context['current_user'])) {
            $parts[] = "Current user: " . $context['current_user'];
        }
        
        if (isset($context['site_name'])) {
            $parts[] = "Site: " . $context['site_name'];
        }
        
        if (isset($context['current_post'])) {
            $post = $context['current_post'];
            $parts[] = "Current post: " . $post['title'] . " (ID: " . $post['id'] . ", Status: " . $post['status'] . ")";
        }
        
        return "Context:\n" . implode("\n", $parts);
    }
    
    /**
     * Format available tools for the prompt
     *
     * @param array $available_tools
     * @return string
     */
    private function format_tools($available_tools) {
        if (is_array($available_tools)) {
            return "Available tools:\n" . implode("\n", $available_tools);
        }
        
        return "Available tools:\n" . $available_tools;
    }
} 