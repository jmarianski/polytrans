<?php
/**
 * DeepSeek Translation Provider
 * Implements TranslationProviderInterface for DeepSeek API
 */

if (!defined('ABSPATH')) {
    exit;
}

class DeepSeekProvider implements \PolyTrans\Providers\TranslationProviderInterface
{
    public function get_id()
    {
        return 'deepseek';
    }
    
    public function get_name()
    {
        return 'DeepSeek';
    }
    
    public function get_description()
    {
        return 'DeepSeek AI translation provider with assistants support';
    }
    
    public function get_settings_provider_class()
    {
        return DeepSeekSettingsProvider::class;
    }
    
    public function has_settings_ui()
    {
        return true;
    }
    
    /**
     * Translate content using DeepSeek chat API
     * Note: DeepSeek doesn't have direct translation API, so we use chat API
     */
    public function translate($content, $source_lang, $target_lang, $settings)
    {
        $api_key = $settings['deepseek_api_key'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'DeepSeek API key not configured',
                'error_code' => 'api_key_missing'
            ];
        }
        
        // Implementacja translacji przez DeepSeek API
        // Przykład - dostosuj do rzeczywistego API DeepSeek
        try {
            $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $settings['deepseek_model'] ?? 'deepseek-chat',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are a professional translator. Translate the following content from {$source_lang} to {$target_lang}. Return only the translated JSON object with the same structure."
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($content, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                    'temperature' => 0.3,
                ]),
                'timeout' => 60,
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'error_code' => 'api_error'
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                $translated_json = $data['choices'][0]['message']['content'];
                $translated_content = json_decode($translated_json, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($translated_content)) {
                    return [
                        'success' => true,
                        'translated_content' => $translated_content
                    ];
                }
            }
            
            return [
                'success' => false,
                'error' => 'Failed to parse translation response',
                'error_code' => 'parse_error'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'exception'
            ];
        }
    }
    
    public function is_configured($settings)
    {
        $api_key = $settings['deepseek_api_key'] ?? '';
        return !empty($api_key);
    }
}

