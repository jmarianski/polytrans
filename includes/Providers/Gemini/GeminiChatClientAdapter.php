<?php

namespace PolyTrans\Providers\Gemini;

use PolyTrans\Providers\ChatClientInterface;

/**
 * Gemini Chat Client Adapter
 * Implements ChatClientInterface for Google Gemini API
 */

if (!defined('ABSPATH')) {
    exit;
}

class GeminiChatClientAdapter implements ChatClientInterface
{
    private $api_key;
    private $base_url;
    
    public function __construct($api_key, $base_url = 'https://generativelanguage.googleapis.com/v1beta')
    {
        $this->api_key = $api_key;
        $this->base_url = rtrim($base_url, '/');
    }
    
    public function get_provider_id()
    {
        return 'gemini';
    }
    
    public function chat_completion($messages, $parameters)
    {
        // Convert OpenAI format to Gemini format
        // Gemini uses: contents array with parts (text) + systemInstruction (optional)
        $system_instruction = '';
        $gemini_contents = [];
        
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            
            if ($role === 'system') {
                $system_instruction = $content;
            } elseif ($role === 'user' || $role === 'assistant') {
                // Convert role: OpenAI uses 'assistant', Gemini uses 'model'
                $gemini_role = ($role === 'assistant') ? 'model' : 'user';
                
                $gemini_contents[] = [
                    'role' => $gemini_role,
                    'parts' => [
                        ['text' => $content]
                    ]
                ];
            }
        }
        
        // Model must be provided - no fallback
        $model = $parameters['model'] ?? '';
        if (empty($model)) {
            // Try to get model from settings
            $settings = get_option('polytrans_settings', []);
            $model = $settings['gemini_model'] ?? '';
        }
        
        // Model is required - return error if not set
        if (empty($model)) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('Gemini model is not selected. Please select a model in settings.', 'polytrans'),
                'error_code' => 'model_not_selected',
            ];
        }
        
        // Build request body
        $body = [
            'contents' => $gemini_contents,
        ];
        
        // Add system instruction if present
        if (!empty($system_instruction)) {
            $body['systemInstruction'] = [
                'parts' => [
                    ['text' => $system_instruction]
                ]
            ];
        }
        
        // Add generation config (temperature, maxOutputTokens, etc.)
        $generation_config = [];
        if (isset($parameters['temperature'])) {
            $generation_config['temperature'] = $parameters['temperature'];
        }
        if (isset($parameters['max_tokens'])) {
            $generation_config['maxOutputTokens'] = $parameters['max_tokens'];
        }
        if (isset($parameters['top_p'])) {
            $generation_config['topP'] = $parameters['top_p'];
        }
        if (isset($parameters['top_k'])) {
            $generation_config['topK'] = $parameters['top_k'];
        }
        
        if (!empty($generation_config)) {
            $body['generationConfig'] = $generation_config;
        }
        
        // Make API request
        $response = wp_remote_post(
            $this->base_url . '/models/' . urlencode($model) . ':generateContent?key=' . urlencode($this->api_key),
            [
                'headers' => [
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
                    $error_message = $body_data['error']['message'] ?? $body_data['error']['status'] ?? 'Unknown API error';
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
        // Gemini format: candidates[0].content.parts[0].text
        if (!isset($response['candidates']) || !is_array($response['candidates']) || empty($response['candidates'])) {
            return null;
        }
        
        $candidate = $response['candidates'][0];
        if (!isset($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
            return null;
        }
        
        // Find first text part
        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }
        
        return null;
    }
}

