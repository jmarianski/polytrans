<?php

/**
 * Predefined AI Assistant Workflow Step
 * 
 * Handles communication with predefined OpenAI assistants configured in settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Predefined_Assistant_Step implements PolyTrans_Workflow_Step_Interface
{
    /**
     * Get the step type identifier
     */
    public function get_type()
    {
        return 'predefined_assistant';
    }

    /**
     * Get the step name/title
     */
    public function get_name()
    {
        return __('Predefined AI Assistant', 'polytrans');
    }

    /**
     * Get the step description
     */
    public function get_description()
    {
        return __('Use a predefined OpenAI assistant with pre-configured settings', 'polytrans');
    }

    /**
     * Execute the workflow step
     */
    public function execute($context, $step_config)
    {
        $start_time = microtime(true);

        try {
            // Get assistant ID
            $assistant_id = $step_config['assistant_id'] ?? '';
            if (empty($assistant_id)) {
                return [
                    'success' => false,
                    'error' => 'Assistant ID is required'
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

            // Interpolate variables in user message
            $variable_manager = new PolyTrans_Variable_Manager();
            $interpolated_user_message = $variable_manager->interpolate_template($user_message, $context);

            // Get AI provider settings
            $provider_settings = $this->get_ai_provider_settings();
            if (!$provider_settings) {
                return [
                    'success' => false,
                    'error' => 'AI provider not configured. Please configure OpenAI settings.',
                    'interpolated_user_message' => $interpolated_user_message
                ];
            }

            // Prepare AI request for assistant
            $ai_request = $this->prepare_assistant_request($assistant_id, $interpolated_user_message, $step_config);

            // Make AI API call to assistant
            $ai_response = $this->call_assistant_api($ai_request, $provider_settings);

            if (!$ai_response['success']) {
                return [
                    'success' => false,
                    'error' => $ai_response['error'] ?? 'AI Assistant API call failed',
                    'interpolated_user_message' => $interpolated_user_message
                ];
            }

            // Process response
            $processed_response = $this->process_assistant_response($ai_response['data'], $step_config);

            $execution_time = microtime(true) - $start_time;

            return [
                'success' => true,
                'data' => $processed_response,
                'execution_time' => $execution_time,
                'raw_response' => $ai_response['data'],
                'interpolated_user_message' => $interpolated_user_message,
                'assistant_id' => $assistant_id,
                'tokens_used' => $ai_response['tokens_used'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $start_time,
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
        if (!isset($step_config['assistant_id']) || empty($step_config['assistant_id'])) {
            $errors[] = 'Assistant ID is required';
        }

        if (!isset($step_config['user_message']) || empty($step_config['user_message'])) {
            $errors[] = 'User message is required';
        }

        // Validate expected format if provided
        if (isset($step_config['expected_format']) && !in_array($step_config['expected_format'], ['text', 'json'])) {
            $errors[] = 'Expected format must be either "text" or "json"';
        }

        // Validate output variables if provided
        if (isset($step_config['output_variables']) && !is_array($step_config['output_variables']) && !is_string($step_config['output_variables'])) {
            $errors[] = 'Output variables must be an array or string';
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
            'assistant_response',  // Always available - raw response
            'processed_content'    // Always available - for plain text or fallback
        ];
    }

    /**
     * Get configuration schema for UI
     */
    public function get_config_schema()
    {
        $available_assistants = $this->get_available_assistants();
        $assistant_options = [''];
        
        // Convert assistants to options format
        foreach ($available_assistants as $assistant) {
            $assistant_options[$assistant['id']] = $assistant['name'] . ' (' . $assistant['model'] . ')';
        }

        return [
            'assistant_id' => [
                'type' => 'select',
                'label' => 'OpenAI Assistant',
                'required' => true,
                'options' => $assistant_options,
                'data_attributes' => [
                    'available-assistants' => json_encode($available_assistants)
                ]
            ],
            'user_message' => [
                'type' => 'textarea',
                'label' => 'User Message Template',
                'required' => true,
                'placeholder' => 'Review this content:\nTitle: {title}\nContent: {content}'
            ],
            'expected_format' => [
                'type' => 'select',
                'label' => 'Expected Response Format',
                'required' => false,
                'options' => [
                    'text' => 'Plain Text',
                    'json' => 'JSON Object'
                ],
                'default' => 'text'
            ],
            'output_variables' => [
                'type' => 'text',
                'label' => 'Output Variables (for JSON format)',
                'description' => 'Comma-separated variable names to extract from response'
            ]
        ];
    }

    /**
     * Get available assistants from OpenAI API
     */
    private function get_available_assistants()
    {
        $settings = get_option('polytrans_settings', []);
        $api_key = $settings['openai_api_key'] ?? '';
        
        if (empty($api_key)) {
            return [];
        }

        // Use cached assistants if available and recent (5 minutes)
        $cached_assistants = get_transient('polytrans_openai_assistants');
        if ($cached_assistants !== false) {
            return $cached_assistants;
        }

        // Fetch assistants from OpenAI API
        $response = wp_remote_get('https://api.openai.com/v1/assistants', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => 'PolyTrans/1.0',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $assistants = [];
        foreach ($data['data'] as $assistant) {
            $assistants[$assistant['id']] = [
                'id' => $assistant['id'],
                'name' => $assistant['name'] ?? 'Unnamed Assistant',
                'description' => $assistant['description'] ?? '',
                'model' => $assistant['model'] ?? 'gpt-4'
            ];
        }

        // Cache for 5 minutes
        set_transient('polytrans_openai_assistants', $assistants, 5 * MINUTE_IN_SECONDS);

        return $assistants;
    }

    /**
     * Get AI provider settings
     */
    private function get_ai_provider_settings()
    {
        $settings = get_option('polytrans_settings', []);
        $api_key = $settings['openai_api_key'] ?? '';

        if (empty($api_key)) {
            return false;
        }

        return [
            'api_key' => $api_key,
            'base_url' => $settings['openai_base_url'] ?? 'https://api.openai.com/v1',
            'model' => $settings['openai_model'] ?? 'gpt-3.5-turbo'
        ];
    }

    /**
     * Prepare assistant request
     */
    private function prepare_assistant_request($assistant_id, $user_message, $step_config)
    {
        return [
            'assistant_id' => $assistant_id,
            'thread' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $user_message
                    ]
                ]
            ]
        ];
    }

    /**
     * Call assistant API
     */
    private function call_assistant_api($request, $provider_settings)
    {
        try {
            // Initialize thread
            $thread_response = $this->create_thread($request['thread'], $provider_settings);
            if (!$thread_response['success']) {
                return $thread_response;
            }

            $thread_id = $thread_response['thread_id'];

            // Run assistant
            $run_response = $this->run_assistant($thread_id, $request['assistant_id'], $provider_settings);
            if (!$run_response['success']) {
                return $run_response;
            }

            return [
                'success' => true,
                'data' => $run_response['data'],
                'tokens_used' => $run_response['tokens_used'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Assistant API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create thread
     */
    private function create_thread($thread_data, $provider_settings)
    {
        $url = rtrim($provider_settings['base_url'], '/') . '/threads';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $provider_settings['api_key'],
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'body' => json_encode($thread_data),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to create thread: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => 'Thread creation failed: ' . ($data['error']['message'] ?? 'Unknown error')
            ];
        }

        return [
            'success' => true,
            'thread_id' => $data['id']
        ];
    }

    /**
     * Run assistant
     */
    private function run_assistant($thread_id, $assistant_id, $provider_settings)
    {
        $url = rtrim($provider_settings['base_url'], '/') . "/threads/{$thread_id}/runs";

        $run_data = [
            'assistant_id' => $assistant_id
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $provider_settings['api_key'],
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'body' => json_encode($run_data),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to run assistant: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => 'Assistant run failed: ' . ($data['error']['message'] ?? 'Unknown error')
            ];
        }

        $run_id = $data['id'];

        // Wait for completion
        return $this->wait_for_completion($thread_id, $run_id, $provider_settings);
    }

    /**
     * Wait for assistant completion
     */
    private function wait_for_completion($thread_id, $run_id, $provider_settings)
    {
        $max_attempts = 30; // 30 seconds max
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $url = rtrim($provider_settings['base_url'], '/') . "/threads/{$thread_id}/runs/{$run_id}";

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $provider_settings['api_key'],
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => 'Failed to check run status: ' . $response->get_error_message()
                ];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($data['status'] === 'completed') {
                return $this->get_messages($thread_id, $provider_settings);
            } elseif (in_array($data['status'], ['failed', 'cancelled', 'expired'])) {
                return [
                    'success' => false,
                    'error' => 'Assistant run failed with status: ' . $data['status']
                ];
            }

            sleep(1);
            $attempt++;
        }

        return [
            'success' => false,
            'error' => 'Assistant run timed out'
        ];
    }

    /**
     * Get messages from thread
     */
    private function get_messages($thread_id, $provider_settings)
    {
        $url = rtrim($provider_settings['base_url'], '/') . "/threads/{$thread_id}/messages";

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $provider_settings['api_key'],
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to get messages: ' . $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Get the latest assistant message
        foreach ($data['data'] as $message) {
            if ($message['role'] === 'assistant') {
                $content = '';
                foreach ($message['content'] as $content_block) {
                    if ($content_block['type'] === 'text') {
                        $content .= $content_block['text']['value'];
                    }
                }

                return [
                    'success' => true,
                    'data' => $content
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'No assistant response found'
        ];
    }

    /**
     * Process assistant response
     */
    private function process_assistant_response($response_content, $step_config)
    {
        $result = ['assistant_response' => $response_content];
        $expected_format = $step_config['expected_format'] ?? 'text';

        // Handle JSON format
        if ($expected_format === 'json') {
            $json_data = json_decode($response_content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                // If output variables are specified, extract them
                $output_variables = $step_config['output_variables'] ?? '';
                if (!empty($output_variables)) {
                    // Convert comma-separated string to array
                    if (is_string($output_variables)) {
                        $output_variables = array_map('trim', explode(',', $output_variables));
                    }
                    
                    // Extract specified variables
                    foreach ($output_variables as $var_name) {
                        if (!empty($var_name) && isset($json_data[$var_name])) {
                            $result[$var_name] = $json_data[$var_name];
                        }
                    }
                } else {
                    // If no specific variables, include all JSON data
                    $result = array_merge($result, $json_data);
                }
            } else {
                // JSON parsing failed, treat as text
                $result['processed_content'] = $response_content;
            }
        } else {
            // Plain text format
            $result['processed_content'] = $response_content;
        }

        return $result;
    }

    /**
     * Get required variables for this step
     */
    public function get_required_variables()
    {
        return [
            // Only require basic variables that are commonly available
            'title',
            'content'
        ];
    }
}
