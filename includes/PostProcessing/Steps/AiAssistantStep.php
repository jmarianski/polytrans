<?php

/**
 * AI Assistant Workflow Step
 * 
 * Handles communication with AI assistants (like OpenAI) for post-processing tasks.
 */

namespace PolyTrans\PostProcessing\Steps;

use PolyTrans\PostProcessing\WorkflowStepInterface;

if (!defined('ABSPATH')) {
    exit;
}

class AiAssistantStep implements WorkflowStepInterface
{
    /**
     * Get the step type identifier
     */
    public function get_type()
    {
        return 'ai_assistant';
    }

    /**
     * Get the step name/title
     */
    public function get_name()
    {
        return __('AI Assistant', 'polytrans');
    }

    /**
     * Get the step description
     */
    public function get_description()
    {
        return __('Process content using an AI assistant with custom system prompt and user message templates', 'polytrans');
    }

    /**
     * Execute the workflow step
     */
    public function execute($context, $step_config)
    {
        $start_time = microtime(true);

        try {
            // Get system prompt and interpolate variables
            $system_prompt = $step_config['system_prompt'] ?? '';
            if (empty($system_prompt)) {
                return [
                    'success' => false,
                    'error' => 'System prompt is required'
                ];
            }

            // Get user message and interpolate variables
            $user_message = $step_config['user_message'] ?? '';
            if (empty($user_message)) {
                return [
                    'success' => false,
                    'error' => 'User message is required'
                ];
            }

            // Interpolate variables in both prompts
            $variable_manager = new \PolyTrans_Variable_Manager();
            $interpolated_system_prompt = $variable_manager->interpolate_template($system_prompt, $context);
            $interpolated_user_message = $variable_manager->interpolate_template($user_message, $context);

            // Get AI provider settings
            $provider_settings = $this->get_ai_provider_settings($step_config);
            if (!$provider_settings) {
                return [
                    'success' => false,
                    'error' => 'AI provider not configured. Please configure OpenAI settings.',
                    'interpolated_system_prompt' => $interpolated_system_prompt,
                    'interpolated_user_message' => $interpolated_user_message
                ];
            }

            // Prepare AI request with separate system and user messages
            $ai_request = $this->prepare_ai_request($interpolated_system_prompt, $interpolated_user_message, $step_config, $provider_settings);

            // Make AI API call
            $ai_response = $this->call_ai_api($ai_request, $provider_settings);

            if (!$ai_response['success']) {
                return [
                    'success' => false,
                    'error' => $ai_response['error'] ?? 'AI API call failed',
                    'interpolated_system_prompt' => $interpolated_system_prompt,
                    'interpolated_user_message' => $interpolated_user_message
                ];
            }

            // Process response based on expected format
            $expected_format = $step_config['expected_format'] ?? 'text';
            $processed_response = $this->process_ai_response($ai_response['data'], $expected_format);

            $execution_time = microtime(true) - $start_time;

            return [
                'success' => true,
                'data' => $processed_response,
                'interpolated_prompts' => [
                    'system_prompt' => $interpolated_system_prompt,
                    'user_message' => $interpolated_user_message
                ],
                'execution_time' => $execution_time,
                'raw_response' => $ai_response['data'],
                'interpolated_system_prompt' => $interpolated_system_prompt,
                'interpolated_user_message' => $interpolated_user_message,
                'tokens_used' => $ai_response['tokens_used'] ?? 0
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $start_time,
                'interpolated_system_prompt' => $interpolated_system_prompt ?? '',
                'interpolated_user_message' => $interpolated_user_message ?? ''
            ];
        }
    }

    /**
     * Validate step configuration
     */
    public function validate_config($step_config)
    {
        $errors = [];

        // Check required fields
        if (!isset($step_config['system_prompt']) || empty($step_config['system_prompt'])) {
            $errors[] = 'System prompt is required';
        }

        if (!isset($step_config['user_message']) || empty($step_config['user_message'])) {
            $errors[] = 'User message is required';
        }

        // Validate expected format
        $valid_formats = ['text', 'json'];
        $expected_format = $step_config['expected_format'] ?? 'text';
        if (!in_array($expected_format, $valid_formats)) {
            $errors[] = 'Expected format must be one of: ' . implode(', ', $valid_formats);
        }

        // Validate model if provided
        if (isset($step_config['model']) && !empty($step_config['model'])) {
            $valid_models = $this->get_all_available_models();
            if (!in_array($step_config['model'], $valid_models)) {
                $errors[] = 'Invalid model. Must be one of the available OpenAI models.';
            }
        }

        // Validate output variables if provided
        if (isset($step_config['output_variables']) && !is_array($step_config['output_variables'])) {
            $errors[] = 'Output variables must be an array';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get available output variables this step can produce
     */
    public function get_output_variables()
    {
        return [
            'ai_response',
            'processed_content',
            'suggestions',
            'score',
            'feedback'
        ];
    }

    /**
     * Get required input variables for this step
     */
    public function get_required_variables()
    {
        // No strict requirements - depends on system prompt
        return [];
    }

    /**
     * Get step configuration schema for UI generation
     */
    public function get_config_schema()
    {
        return [
            'system_prompt' => [
                'type' => 'textarea',
                'label' => __('System Prompt', 'polytrans'),
                'description' => __('Instructions for the AI assistant. Use {variable_name} to include data from context.', 'polytrans'),
                'required' => true,
                'rows' => 6
            ],
            'user_message' => [
                'type' => 'textarea',
                'label' => __('User Message Template', 'polytrans'),
                'description' => __('Template for the user message. Use {variable_name} to include data from context.', 'polytrans'),
                'required' => true,
                'rows' => 4,
                'placeholder' => 'Title: {title}\nContent: {content}\nTarget Language: {target_language}\n\nPlease review this translated content and provide your analysis.'
            ],
            'model' => [
                'type' => 'select',
                'label' => __('AI Model', 'polytrans'),
                'description' => __('OpenAI model to use for this step (overrides global setting)', 'polytrans'),
                'options' => [
                    '' => __('Use Global Setting', 'polytrans'),
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                    'gpt-4' => 'GPT-4',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-4o' => 'GPT-4o',
                    'gpt-4o-mini' => 'GPT-4o Mini'
                ],
                'default' => ''
            ],
            'expected_format' => [
                'type' => 'select',
                'label' => __('Expected Response Format', 'polytrans'),
                'description' => __('How the AI should format its response', 'polytrans'),
                'options' => [
                    'text' => __('Plain Text', 'polytrans'),
                    'json' => __('JSON Object', 'polytrans')
                ],
                'default' => 'text'
            ],
            'output_variables' => [
                'type' => 'text',
                'label' => __('Output Variables', 'polytrans'),
                'description' => __('Comma-separated list of variable names to extract from JSON response', 'polytrans'),
                'placeholder' => 'reviewed_content, suggestions, score'
            ],
            'max_tokens' => [
                'type' => 'number',
                'label' => __('Max Tokens', 'polytrans'),
                'description' => __('Maximum tokens for AI response (leave empty for default)', 'polytrans'),
                'min' => 1,
                'max' => 4000
            ],
            'temperature' => [
                'type' => 'number',
                'label' => __('Temperature', 'polytrans'),
                'description' => __('AI creativity level (0.0 = focused, 1.0 = creative)', 'polytrans'),
                'min' => 0.0,
                'max' => 1.0,
                'step' => 0.1,
                'default' => 0.7
            ]
        ];
    }

    /**
     * Get AI provider settings
     */
    private function get_ai_provider_settings($step_config = [])
    {
        // Get PolyTrans settings to find which AI provider is configured
        $polytrans_settings = get_option('polytrans_settings', []);
        $translation_provider = $polytrans_settings['translation_provider'] ?? 'openai';

        // For now, we'll use OpenAI settings from the translation provider
        if ($translation_provider === 'openai') {
            $api_key = $polytrans_settings['openai_api_key'] ?? '';
            if (empty($api_key)) {
                return false;
            }

            // Check for step-specific model override, otherwise use global setting
            $model = $step_config['model'] ?? $polytrans_settings['openai_model'] ?? 'gpt-4o-mini';

            // If step config has empty model, use global setting
            if (empty($model)) {
                $model = $polytrans_settings['openai_model'] ?? 'gpt-4o-mini';
            }

            return [
                'provider' => 'openai',
                'api_key' => $api_key,
                'model' => $model,
                'base_url' => $polytrans_settings['openai_base_url'] ?? 'https://api.openai.com/v1'
            ];
        }

        return false;
    }

    /**
     * Prepare AI request
     */
    private function prepare_ai_request($system_prompt, $user_message, $step_config, $provider_settings)
    {
        // Ensure temperature is a float
        $temperature = $step_config['temperature'] ?? 0.7;
        if (is_string($temperature)) {
            $temperature = floatval($temperature);
        }
        // Clamp temperature to valid range
        $temperature = max(0.0, min(1.0, $temperature));

        $request = [
            'model' => $provider_settings['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => $user_message
                ]
            ],
            'temperature' => $temperature
        ];

        // Add max tokens if specified
        if (isset($step_config['max_tokens']) && !empty($step_config['max_tokens'])) {
            $request['max_tokens'] = intval($step_config['max_tokens']);
        }

        // For JSON format, ensure system prompt includes JSON instruction
        if (($step_config['expected_format'] ?? 'text') === 'json') {
            // Only add JSON instruction if not already present in system prompt
            if (stripos($system_prompt, 'json') === false) {
                $request['messages'][0]['content'] .= "\n\nPlease respond with valid JSON only.";
            }
        }

        return $request;
    }

    /**
     * Call AI API
     */
    private function call_ai_api($request, $provider_settings)
    {
        $api_url = rtrim($provider_settings['base_url'], '/') . '/chat/completions';

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $provider_settings['api_key']
            ],
            'body' => json_encode($request),
            'timeout' => 120
        ];

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['error']['message'] ?? "API returned status code {$status_code}";

            return [
                'success' => false,
                'error' => $error_message
            ];
        }

        $response_data = json_decode($body, true);

        if (!$response_data || !isset($response_data['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response format'
            ];
        }

        return [
            'success' => true,
            'data' => $response_data['choices'][0]['message']['content'],
            'tokens_used' => $response_data['usage']['total_tokens'] ?? 0
        ];
    }

    /**
     * Process AI response based on expected format
     */
    private function process_ai_response($response_text, $expected_format)
    {
        if ($expected_format === 'json') {
            $variable_manager = new \PolyTrans_Variable_Manager();
            $parsed_json = $variable_manager->parse_json_response($response_text);

            if (!empty($parsed_json)) {
                return $parsed_json;
            } else {
                // If JSON parsing failed, return as text
                return [
                    'ai_response' => $response_text,
                    '_parsing_error' => 'Failed to parse as JSON'
                ];
            }
        }

        // For text format, return as simple response
        return [
            'ai_response' => $response_text
        ];
    }

    /**
     * Get all available OpenAI models (using same source as OpenAI settings provider)
     */
    private function get_all_available_models()
    {
        // Get the OpenAI settings provider to ensure we use the same model list
        $provider = new \PolyTrans_OpenAI_Settings_Provider();
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('get_grouped_models');
        $method->setAccessible(true);
        $grouped_models = $method->invoke($provider);

        $models = [];
        foreach ($grouped_models as $group => $group_models) {
            $models = array_merge($models, array_keys($group_models));
        }
        return $models;
    }
}
