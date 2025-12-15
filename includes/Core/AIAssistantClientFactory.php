<?php

namespace PolyTrans\Core;

use PolyTrans\Providers\AIAssistantClientInterface;
use PolyTrans\Providers\OpenAI\OpenAIAssistantClientAdapter;

/**
 * AI Assistant Client Factory
 * Creates appropriate AI assistant client based on assistant ID format
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIAssistantClientFactory
{
    /**
     * Create AI assistant client for given assistant ID
     * 
     * @param string $assistant_id Assistant ID (asst_xxx, project_xxx, etc.)
     * @param array $settings Translation settings
     * @return AIAssistantClientInterface|null Client instance or null if not supported
     */
    public static function create($assistant_id, $settings)
    {
        // Detect provider from assistant ID format
        if (strpos($assistant_id, 'asst_') === 0) {
            // OpenAI assistant
            $api_key = $settings['openai_api_key'] ?? '';
            if (empty($api_key)) {
                return null;
            }
            return new OpenAIAssistantClientAdapter($api_key);
        }
        
        // Future: Claude assistants (project_xxx)
        // if (strpos($assistant_id, 'project_') === 0) {
        //     $api_key = $settings['claude_api_key'] ?? '';
        //     if (empty($api_key)) {
        //         return null;
        //     }
        //     return new ClaudeAssistantClientAdapter($api_key);
        // }
        
        // Future: Gemini assistants (tuned_model_xxx)
        // if (strpos($assistant_id, 'tuned_model_') === 0) {
        //     $api_key = $settings['gemini_api_key'] ?? '';
        //     if (empty($api_key)) {
        //         return null;
        //     }
        //     return new GeminiAssistantClientAdapter($api_key);
        // }
        
        return null;
    }
    
    /**
     * Get provider ID from assistant ID
     * 
     * @param string $assistant_id Assistant ID
     * @return string|null Provider ID or null if unknown format
     */
    public static function get_provider_id($assistant_id)
    {
        if (strpos($assistant_id, 'asst_') === 0) {
            return 'openai';
        }
        if (strpos($assistant_id, 'project_') === 0) {
            return 'claude';
        }
        if (strpos($assistant_id, 'tuned_model_') === 0) {
            return 'gemini';
        }
        
        return null;
    }
}

