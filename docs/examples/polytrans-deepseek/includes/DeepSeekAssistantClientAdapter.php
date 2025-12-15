<?php
/**
 * DeepSeek Assistant Client Adapter
 * Implements AIAssistantClientInterface for DeepSeek API assistants
 */

if (!defined('ABSPATH')) {
    exit;
}

class DeepSeekAssistantClientAdapter implements \PolyTrans\Providers\AIAssistantClientInterface
{
    private $api_key;
    private $base_url;
    
    public function __construct($api_key, $base_url = 'https://api.deepseek.com/v1')
    {
        $this->api_key = $api_key;
        $this->base_url = $base_url;
    }
    
    public function get_provider_id()
    {
        return 'deepseek';
    }
    
    public function supports_assistant_id($assistant_id)
    {
        // DeepSeek assistants use format: deepseek_xxx
        return strpos($assistant_id, 'deepseek_') === 0;
    }
    
    public function execute_assistant($assistant_id, $content, $source_lang, $target_lang)
    {
        // Remove provider prefix to get actual assistant ID
        $actual_assistant_id = str_replace('deepseek_', '', $assistant_id);
        
        \PolyTrans_Logs_Manager::log(
            "DeepSeek Assistant: executing {$actual_assistant_id} ({$source_lang} -> {$target_lang})",
            "info"
        );
        
        // Prepare content for translation
        $content_to_translate = [
            'title' => $content['title'] ?? '',
            'content' => $content['content'] ?? '',
            'excerpt' => $content['excerpt'] ?? '',
            'meta' => $content['meta'] ?? [],
            'featured_image' => $content['featured_image'] ?? null
        ];
        
        $prompt = "Please translate the following JSON content from {$source_lang} to {$target_lang}. Return only a JSON object with the same structure but translated content:\n\n" . 
                  json_encode($content_to_translate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        try {
            // Call DeepSeek API (example - adjust to actual DeepSeek API)
            $response = wp_remote_post($this->base_url . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => 'deepseek-chat',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a professional translator. Translate JSON content while preserving structure.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3,
                ]),
                'timeout' => 60,
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'translated_content' => null,
                    'error' => 'API request failed: ' . $response->get_error_message()
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['choices'][0]['message']['content'])) {
                return [
                    'success' => false,
                    'translated_content' => null,
                    'error' => 'Invalid API response format'
                ];
            }
            
            $response_text = $data['choices'][0]['message']['content'];
            
            // Parse JSON response
            $parser = new \PolyTrans\PostProcessing\JsonResponseParser();
            $schema = [
                'title' => 'string',
                'content' => 'string',
                'excerpt' => 'string',
                'meta' => 'array',
                'featured_image' => 'array'
            ];
            
            $parse_result = $parser->parse_with_schema($response_text, $schema);
            
            if (!$parse_result['success']) {
                \PolyTrans_Logs_Manager::log(
                    "Failed to parse DeepSeek translation response: " . $parse_result['error'],
                    "error",
                    ['raw_response' => substr($response_text, 0, 500)]
                );
                return [
                    'success' => false,
                    'translated_content' => null,
                    'error' => 'Failed to parse DeepSeek response: ' . $parse_result['error']
                ];
            }
            
            return [
                'success' => true,
                'translated_content' => $parse_result['data'],
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'translated_content' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}

