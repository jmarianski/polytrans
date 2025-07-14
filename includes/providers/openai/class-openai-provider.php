<?php

/**
 * OpenAI Provider
 * Implements OpenAI translation integration following the provider interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_OpenAI_Provider implements PolyTrans_Translation_Provider_Interface
{
    /**
     * Get the provider identifier
     */
    public function get_id()
    {
        return 'openai';
    }

    /**
     * Get the provider display name
     */
    public function get_name()
    {
        return __('OpenAI', 'polytrans');
    }

    /**
     * Get the provider description
     */
    public function get_description()
    {
        return __('AI-powered translation with custom assistants. Requires OpenAI API key and configured assistants.', 'polytrans');
    }

    /**
     * Check if the provider is properly configured
     */
    public function is_configured(array $settings)
    {
        $api_key = $settings['openai_api_key'] ?? '';
        $assistants = $settings['openai_assistants'] ?? [];

        // Check if we have an API key and at least one assistant configured
        if (empty($api_key)) {
            return false;
        }

        // Check if we have at least one non-empty assistant (language pair)
        $has_assistant = false;
        foreach ($assistants as $language_pair => $assistant_id) {
            if (!empty($assistant_id)) {
                $has_assistant = true;
                break;
            }
        }

        return $has_assistant;
    }

    /**
     * Translate content using OpenAI
     */
    public function translate(array $content, string $source_lang, string $target_lang, array $settings)
    {
        PolyTrans_Logs_Manager::log("OpenAI: translating from $source_lang to $target_lang", "info");

        $openai_api_key = $settings['openai_api_key'] ?? '';
        $openai_source_language = $settings['openai_source_language'] ?? 'en';
        $openai_assistants = $settings['openai_assistants'] ?? [];

        PolyTrans_Logs_Manager::log("OpenAI settings - Source language: $openai_source_language", "info");
        error_log("[polytrans] OpenAI assistants configuration: " . json_encode($openai_assistants));

        if (!$openai_api_key) {
            return [
                'success' => false,
                'translated_content' => null,
                'error' => 'OpenAI API key not configured'
            ];
        }

        $effective_source_lang = $source_lang;
        $content_to_translate = $content;

        try {
            // Handle multi-step translation if needed
            if ($source_lang !== $openai_source_language) {
                PolyTrans_Logs_Manager::log("Multi-step translation required: $source_lang -> $openai_source_language -> $target_lang", "info");

                // For the intermediate step, look for the specific translation direction
                $intermediate_key = $source_lang . '_to_' . $openai_source_language;
                $intermediate_assistant_id = $openai_assistants[$intermediate_key] ?? null;

                if (!$intermediate_assistant_id || empty($intermediate_assistant_id)) {
                    // Try to find any available assistant as fallback
                    $available_assistants = array_filter($openai_assistants, function ($assistant) {
                        return !empty($assistant);
                    });

                    if (!empty($available_assistants)) {
                        $intermediate_assistant_id = reset($available_assistants);
                        $used_direction = array_search($intermediate_assistant_id, $openai_assistants);
                        PolyTrans_Logs_Manager::log("No specific assistant for $intermediate_key, using fallback: $used_direction ($intermediate_assistant_id)", "info");
                    } else {
                        return [
                            'success' => false,
                            'translated_content' => null,
                            'error' => "No OpenAI assistant configured for intermediate translation ($source_lang -> $openai_source_language). Please configure an assistant for '$intermediate_key' or any other direction."
                        ];
                    }
                } else {
                    PolyTrans_Logs_Manager::log("Using specific assistant for $intermediate_key: $intermediate_assistant_id", "info");
                }

                error_log("[polytrans] Using assistant for intermediate translation: " . $intermediate_assistant_id);
                $intermediate_result = $this->translate_with_openai($content_to_translate, $source_lang, $openai_source_language, $intermediate_assistant_id, $openai_api_key);
                if (!$intermediate_result['success']) {
                    return [
                        'success' => false,
                        'translated_content' => null,
                        'error' => 'Intermediate translation failed: ' . $intermediate_result['error']
                    ];
                }

                $content_to_translate = $intermediate_result['translated_content'];
                $effective_source_lang = $openai_source_language;

                PolyTrans_Logs_Manager::log("Intermediate translation completed: $source_lang -> $openai_source_language", "info");
            } else {
                PolyTrans_Logs_Manager::log("Direct translation: $source_lang -> $target_lang (source matches OpenAI source language)", "info");
            }

            // Check if we need final translation (target different from current effective source)
            if ($effective_source_lang === $target_lang) {
                PolyTrans_Logs_Manager::log("No final translation needed: effective source language ($effective_source_lang) matches target language", "info");
                return [
                    'success' => true,
                    'translated_content' => $content_to_translate,
                    'error' => null
                ];
            }

            // Get the assistant for the final translation step
            $final_key = $effective_source_lang . '_to_' . $target_lang;
            $assistant_id = $openai_assistants[$final_key] ?? null;

            if (!$assistant_id || empty($assistant_id)) {
                // Try to find any available assistant as fallback
                $available_assistants = array_filter($openai_assistants, function ($assistant) {
                    return !empty($assistant);
                });

                if (!empty($available_assistants)) {
                    $assistant_id = reset($available_assistants);
                    $used_direction = array_search($assistant_id, $openai_assistants);
                    PolyTrans_Logs_Manager::log("No specific assistant for $final_key, using fallback: $used_direction ($assistant_id)", "info");
                } else {
                    return [
                        'success' => false,
                        'translated_content' => null,
                        'error' => "No OpenAI assistant configured for final translation ($effective_source_lang -> $target_lang). Please configure an assistant for '$final_key' or any other direction."
                    ];
                }
            } else {
                PolyTrans_Logs_Manager::log("Using specific assistant for $final_key: $assistant_id", "info");
            }

            // Final translation to target language
            PolyTrans_Logs_Manager::log("Performing final translation: $effective_source_lang -> $target_lang", "info");
            $final_result = $this->translate_with_openai($content_to_translate, $effective_source_lang, $target_lang, $assistant_id, $openai_api_key);
            if (!$final_result['success']) {
                return [
                    'success' => false,
                    'translated_content' => null,
                    'error' => 'Final translation failed: ' . $final_result['error']
                ];
            }

            return [
                'success' => true,
                'translated_content' => $final_result['translated_content'],
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("[polytrans] OpenAI translation error: " . $e->getMessage());
            return [
                'success' => false,
                'translated_content' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get supported languages
     */
    public function get_supported_languages()
    {
        // OpenAI supports most languages, return empty array to indicate all languages supported
        return [];
    }

    /**
     * Get the settings provider class name for this translation provider
     */
    public function get_settings_provider_class()
    {
        return 'PolyTrans_OpenAI_Settings_Provider';
    }

    /**
     * Translate text using OpenAI API
     */
    private function translate_with_openai($content, $source_lang, $target_lang, $assistant_id, $api_key)
    {
        // Prepare the content for translation as JSON
        $content_to_translate = [
            'title' => $content['title'] ?? '',
            'content' => $content['content'] ?? '',
            'excerpt' => $content['excerpt'] ?? '',
            'meta' => $content['meta'] ?? []
        ];

        $prompt = "Please translate the following JSON content from $source_lang to $target_lang. Return only a JSON object with the same structure but translated content:\n\n" . json_encode($content_to_translate, JSON_PRETTY_PRINT);

        // Create a thread
        $thread_response = wp_remote_post('https://api.openai.com/v1/threads', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'body' => wp_json_encode([]),
            'timeout' => 30
        ]);

        if (is_wp_error($thread_response)) {
            return [
                'success' => false,
                'error' => 'Failed to create thread: ' . $thread_response->get_error_message()
            ];
        }

        $thread_data = json_decode(wp_remote_retrieve_body($thread_response), true);
        if (!isset($thread_data['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid thread response'
            ];
        }

        $thread_id = $thread_data['id'];

        // Add message to thread
        $message_response = wp_remote_post("https://api.openai.com/v1/threads/$thread_id/messages", [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'body' => wp_json_encode([
                'role' => 'user',
                'content' => $prompt
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($message_response)) {
            return [
                'success' => false,
                'error' => 'Failed to add message: ' . $message_response->get_error_message()
            ];
        }

        // Run the assistant
        $run_response = wp_remote_post("https://api.openai.com/v1/threads/$thread_id/runs", [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'body' => wp_json_encode([
                'assistant_id' => $assistant_id
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($run_response)) {
            return [
                'success' => false,
                'error' => 'Failed to run assistant: ' . $run_response->get_error_message()
            ];
        }

        $run_data = json_decode(wp_remote_retrieve_body($run_response), true);
        if (!isset($run_data['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid run response'
            ];
        }

        $run_id = $run_data['id'];

        // Poll for completion
        $max_attempts = 30;
        $attempts = 0;

        while ($attempts < $max_attempts) {
            sleep(2); // Wait 2 seconds between checks

            $status_response = wp_remote_get("https://api.openai.com/v1/threads/$thread_id/runs/$run_id", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($status_response)) {
                return [
                    'success' => false,
                    'error' => 'Failed to check run status: ' . $status_response->get_error_message()
                ];
            }

            $status_data = json_decode(wp_remote_retrieve_body($status_response), true);
            $status = $status_data['status'] ?? 'unknown';

            PolyTrans_Logs_Manager::log("OpenAI run status: $status (attempt $attempts)", "info");

            if ($status === 'completed') {
                break;
            } elseif ($status === 'failed' || $status === 'cancelled' || $status === 'expired') {
                return [
                    'success' => false,
                    'error' => "OpenAI run $status: " . ($status_data['last_error']['message'] ?? 'Unknown error')
                ];
            }

            $attempts++;
        }

        if ($attempts >= $max_attempts) {
            return [
                'success' => false,
                'error' => 'OpenAI translation timed out'
            ];
        }

        // Get the assistant's response
        $messages_response = wp_remote_get("https://api.openai.com/v1/threads/$thread_id/messages?order=desc&limit=1", [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($messages_response)) {
            return [
                'success' => false,
                'error' => 'Failed to get messages: ' . $messages_response->get_error_message()
            ];
        }

        $messages_data = json_decode(wp_remote_retrieve_body($messages_response), true);
        if (!isset($messages_data['data'][0]['content'][0]['text']['value'])) {
            return [
                'success' => false,
                'error' => 'Invalid message response format'
            ];
        }

        $response_text = $messages_data['data'][0]['content'][0]['text']['value'];

        // Extract JSON from the response
        if (preg_match('/```json\s*(.*?)\s*```/s', $response_text, $matches)) {
            $json_text = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $response_text, $matches)) {
            $json_text = $matches[0];
        } else {
            $json_text = $response_text;
        }

        $translated_content = json_decode($json_text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Failed to parse OpenAI response as JSON: ' . json_last_error_msg() . '. Response: ' . $response_text
            ];
        }

        return [
            'success' => true,
            'translated_content' => $translated_content
        ];
    }
}
