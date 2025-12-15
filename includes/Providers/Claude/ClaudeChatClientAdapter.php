<?php

namespace PolyTrans\Providers\Claude;

use PolyTrans\Providers\ChatClientInterface;

/**
 * Claude Chat Client Adapter
 * Implements ChatClientInterface for Claude Messages API
 */

if (!defined('ABSPATH')) {
    exit;
}

class ClaudeChatClientAdapter implements ChatClientInterface
{
    private $api_key;
    private $base_url;
    private $api_version;
    
    public function __construct($api_key, $base_url = 'https://api.anthropic.com/v1', $api_version = '2023-06-01')
    {
        $this->api_key = $api_key;
        $this->base_url = rtrim($base_url, '/');
        $this->api_version = $api_version;
    }
    
    public function get_provider_id()
    {
        return 'claude';
    }
    
    public function chat_completion($messages, $parameters)
    {
        // Convert OpenAI format to Claude format
        // Claude uses: messages array (user/assistant only) + separate system field
        $system_message = '';
        $claude_messages = [];
        
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            
            if ($role === 'system') {
                $system_message = $content;
            } elseif ($role === 'user' || $role === 'assistant') {
                $claude_messages[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }
        
        // Build request body
        // Model must be provided - no fallback
        $model = $parameters['model'] ?? '';
        if (empty($model)) {
            // Try to get model from settings
            $settings = get_option('polytrans_settings', []);
            $model = $settings['claude_model'] ?? '';
        }
        
        // Model is required - return error if not set
        if (empty($model)) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('Claude model is not selected. Please select a model in settings.', 'polytrans'),
                'error_code' => 'model_not_selected',
            ];
        }
        
        $body = [
            'model' => $model,
            'max_tokens' => $parameters['max_tokens'] ?? 4096,
            'messages' => $claude_messages,
        ];
        
        // Add system message if present
        if (!empty($system_message)) {
            $body['system'] = $system_message;
        }
        
        // Add other parameters (temperature, top_p, etc.)
        if (isset($parameters['temperature'])) {
            $body['temperature'] = $parameters['temperature'];
        }
        if (isset($parameters['top_p'])) {
            $body['top_p'] = $parameters['top_p'];
        }
        if (isset($parameters['top_k'])) {
            $body['top_k'] = $parameters['top_k'];
        }
        
        // Make API request
        $response = wp_remote_post(
            $this->base_url . '/messages',
            [
                'headers' => [
                    'x-api-key' => $this->api_key,
                    'anthropic-version' => $this->api_version,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
                'timeout' => 120,
            ]
        );
        
        // Handle errors
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle API errors
        if ($status_code !== 200) {
            $error_message = 'Unknown API error';
            
            if (isset($body_data['error'])) {
                if (is_array($body_data['error'])) {
                    $error_message = $body_data['error']['message'] ?? $body_data['error']['type'] ?? 'Unknown API error';
                } else {
                    $error_message = $body_data['error'];
                }
            }
            
            return [
                'success' => false,
                'data' => null,
                'error' => $error_message,
                'error_code' => $status_code === 429 ? 'rate_limit' : 'api_error',
                'status' => $status_code,
            ];
        }
        
        return [
            'success' => true,
            'data' => $body_data,
            'error' => null
        ];
    }
    
    public function extract_content($response)
    {
        // Claude format: content[0].text
        if (!isset($response['content']) || !is_array($response['content'])) {
            return null;
        }
        
        // Find first text block
        foreach ($response['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                return $block['text'];
            }
        }
        
        return null;
    }
}

