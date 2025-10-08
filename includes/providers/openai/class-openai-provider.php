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
     * Resolve translation path using openai_path_rules.
     * Returns an array of language codes representing the path, e.g. [source, intermediate, target] or [source, target].
     */
    private function resolve_translation_path($source_lang, $target_lang, $openai_path_rules)
    {
        // Find the most specific rule, breaking ties by last-in-list
        $best_rule = null;
        $best_score = 0;
        $best_index = -1;
        foreach ($openai_path_rules as $idx => $rule) {
            $score = 0;
            if ($rule['source'] === $source_lang && $rule['target'] === $target_lang) {
                $score = 3; // exact match
            } elseif (
                ($rule['source'] === $source_lang && $rule['target'] === 'all') ||
                ($rule['source'] === 'all' && $rule['target'] === $target_lang)
            ) {
                $score = 2; // semi-wildcard
            } elseif ($rule['source'] === 'all' && $rule['target'] === 'all') {
                $score = 1; // full wildcard
            }
            if ($score > 0 && ($score > $best_score || ($score === $best_score && $idx > $best_index))) {
                $best_rule = $rule;
                $best_score = $score;
                $best_index = $idx;
            }
        }
        if ($best_rule) {
            $intermediate = isset($best_rule['intermediate']) ? trim($best_rule['intermediate']) : '';
            PolyTrans_Logs_Manager::log(
                "resolve_translation_path: selected rule (score $best_score, index $best_index): " . json_encode($best_rule) . ", intermediate: '" . $intermediate . "'",
                "debug"
            );
            if ($intermediate === '' || strtolower($intermediate) === 'none' || $intermediate === $source_lang || $intermediate === $target_lang) {
                return [$source_lang, $target_lang];
            } else {
                return [$source_lang, $intermediate, $target_lang];
            }
        }
        PolyTrans_Logs_Manager::log("resolve_translation_path: no rule found for $source_lang -> $target_lang, using direct path", "debug");
        return [$source_lang, $target_lang];
    }

    /**
     * Translate content using OpenAI
     */
    public function translate(array $content, string $source_lang, string $target_lang, array $settings)
    {
        PolyTrans_Logs_Manager::log("OpenAI: translating from $source_lang to $target_lang", "info");

        $openai_api_key = $settings['openai_api_key'] ?? '';
        $openai_assistants = $settings['openai_assistants'] ?? [];
        $openai_path_rules = $settings['openai_path_rules'] ?? [];

        if (!$openai_api_key) {
            return [
                'success' => false,
                'translated_content' => null,
                'error' => 'OpenAI API key not configured'
            ];
        }

        try {
            PolyTrans_Logs_Manager::log("OpenAI: openai_path_rules: " . json_encode($openai_path_rules), "debug");
            $path = $this->resolve_translation_path($source_lang, $target_lang, $openai_path_rules);
            PolyTrans_Logs_Manager::log("OpenAI: translation path resolved: " . implode(' -> ', $path), "info");

            $content_to_translate = $content;
            for ($i = 0; $i < count($path) - 1; $i++) {
                $step_source = $path[$i];
                $step_target = $path[$i + 1];
                $assistant_key = $step_source . '_to_' . $step_target;
                $assistant_id = $openai_assistants[$assistant_key] ?? null;
                PolyTrans_Logs_Manager::log("OpenAI: step $i: $step_source -> $step_target, assistant_key: $assistant_key, assistant_id: " . ($assistant_id ?: 'none'), "debug");
                if (!$assistant_id || empty($assistant_id)) {
                    // Fallback: any available assistant
                    $available_assistants = array_filter($openai_assistants, function ($assistant) {
                        return !empty($assistant);
                    });
                    if (!empty($available_assistants)) {
                        $assistant_id = reset($available_assistants);
                        $used_direction = array_search($assistant_id, $openai_assistants);
                        PolyTrans_Logs_Manager::log("No specific assistant for $assistant_key, using fallback: $used_direction ($assistant_id)", "info");
                    } else {
                        PolyTrans_Logs_Manager::log("No OpenAI assistant configured for translation step ($step_source -> $step_target). Please configure an assistant for '$assistant_key' or any other direction.", "error");
                        return [
                            'success' => false,
                            'translated_content' => null,
                            'error' => "No OpenAI assistant configured for translation step ($step_source -> $step_target). Please configure an assistant for '$assistant_key' or any other direction."
                        ];
                    }
                }
                PolyTrans_Logs_Manager::log("OpenAI: translating step $step_source -> $step_target with assistant $assistant_id", "info");
                $result = $this->translate_with_openai($content_to_translate, $step_source, $step_target, $assistant_id, $openai_api_key);
                if (!$result['success']) {
                    PolyTrans_Logs_Manager::log("OpenAI: step $i failed ($step_source -> $step_target): " . $result['error'], "error");
                    return [
                        'success' => false,
                        'translated_content' => null,
                        'error' => "Translation step failed ($step_source -> $step_target): " . $result['error']
                    ];
                }
                PolyTrans_Logs_Manager::log("OpenAI: step $i completed ($step_source -> $step_target)", "debug");
                $content_to_translate = $result['translated_content'];
            }
            PolyTrans_Logs_Manager::log("OpenAI: all steps completed, returning final translation", "info");
            return [
                'success' => true,
                'translated_content' => $content_to_translate,
                'error' => null
            ];
        } catch (Exception $e) {
            error_log("[polytrans] OpenAI translation error: " . $e->getMessage());
            PolyTrans_Logs_Manager::log("OpenAI: exception: " . $e->getMessage(), "error");
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
        PolyTrans_Logs_Manager::log("OpenAI run status: started ($source_lang -> $target_lang)", "info");

        // Create OpenAI client
        $client = new PolyTrans_OpenAI_Client($api_key);

        // Prepare the content for translation as JSON
        $content_to_translate = [
            'title' => $content['title'] ?? '',
            'content' => $content['content'] ?? '',
            'excerpt' => $content['excerpt'] ?? '',
            'meta' => $content['meta'] ?? [],
            'featured_image' => $content['featured_image'] ?? null
        ];

        $prompt = "Please translate the following JSON content from $source_lang to $target_lang. Return only a JSON object with the same structure but translated content:\n\n" . json_encode($content_to_translate, JSON_PRETTY_PRINT);

        // Create a thread
        $thread_result = $client->create_thread();
        if (!$thread_result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to create thread: ' . $thread_result['error']
            ];
        }

        $thread_id = $thread_result['thread_id'];

        // Add message to thread
        $message_result = $client->add_message($thread_id, 'user', $prompt);
        if (!$message_result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to add message: ' . $message_result['error']
            ];
        }

        // Run the assistant
        $run_result = $client->run_assistant($thread_id, $assistant_id);
        if (!$run_result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to run assistant: ' . $run_result['error']
            ];
        }

        $run_id = $run_result['run_id'];

        // Wait for completion using the client's built-in method
        // This will poll up to 30 times with 1 second between checks (30 seconds total)
        sleep(10); // Initial wait to allow processing to start

        $completion_result = $client->wait_for_run_completion($thread_id, $run_id, 120, 1);

        if (!$completion_result['success']) {
            PolyTrans_Logs_Manager::log("OpenAI run status: " . ($completion_result['status'] ?? 'failed') . " ($source_lang -> $target_lang)", "error");
            return [
                'success' => false,
                'error' => $completion_result['error']
            ];
        }

        PolyTrans_Logs_Manager::log("OpenAI run status: completed ($source_lang -> $target_lang)", "info");

        // Get the assistant's response
        $message_result = $client->get_latest_assistant_message($thread_id);
        if (!$message_result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to get messages: ' . $message_result['error']
            ];
        }

        $response_text = $message_result['content'];

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
