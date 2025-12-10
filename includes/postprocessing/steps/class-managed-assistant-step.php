<?php

/**
 * Managed AI Assistant Workflow Step
 * 
 * Uses the centralized AI Assistants Management System (Phase 1).
 * Executes assistants configured via Admin UI with Twig variable interpolation.
 * 
 * This step type uses the new Assistant Executor which provides:
 * - Centralized assistant management
 * - Multi-provider support (OpenAI, Claude, Gemini)
 * - Twig template engine for variable interpolation
 * - Comprehensive error handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Managed_Assistant_Step implements PolyTrans_Workflow_Step_Interface
{
    /**
     * Get the step type identifier
     */
    public function get_type()
    {
        return 'managed_assistant';
    }

    /**
     * Get the step name/title
     */
    public function get_name()
    {
        return __('Managed AI Assistant', 'polytrans');
    }

    /**
     * Get the step description
     */
    public function get_description()
    {
        return __('Use a centrally managed AI assistant with Twig variable interpolation', 'polytrans');
    }

    /**
     * Execute the workflow step
     */
    public function execute($context, $step_config)
    {
        $start_time = microtime(true);

        try {
            // Get assistant ID from config
            $assistant_id = $step_config['assistant_id'] ?? 0;
            if (empty($assistant_id)) {
                return [
                    'success' => false,
                    'error' => 'Assistant ID is required'
                ];
            }

            // Verify assistant exists
            $assistant = PolyTrans_Assistant_Manager::get_assistant($assistant_id);
            if (!$assistant) {
                return [
                    'success' => false,
                    'error' => "Assistant with ID {$assistant_id} not found"
                ];
            }

            // Execute assistant with context as variables
            $result = PolyTrans_Assistant_Executor::execute($assistant_id, $context);

            $execution_time = microtime(true) - $start_time;

            // Check if result is WP_Error
            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'error' => $result->get_error_message(),
                    'execution_time' => $execution_time,
                    'assistant_id' => $assistant_id,
                    'assistant_name' => $assistant['name']
                ];
            }

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Assistant execution failed',
                    'execution_time' => $execution_time,
                    'assistant_id' => $assistant_id,
                    'assistant_name' => $assistant['name']
                ];
            }

            // Return successful result
            return [
                'success' => true,
                'data' => $result['output'] ?? $result['data'] ?? null,
                'execution_time' => $execution_time,
                'assistant_id' => $assistant_id,
                'assistant_name' => $assistant['name'],
                'provider' => $result['provider'] ?? 'unknown',
                'model' => $result['model'] ?? 'unknown',
                'usage' => $result['usage'] ?? []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
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
        } else {
            // Verify assistant exists
            $assistant = PolyTrans_Assistant_Manager::get_assistant($step_config['assistant_id']);
            if (!$assistant) {
                $errors[] = 'Selected assistant does not exist';
            }
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
            'data',
            'result'
        ];
    }

    /**
     * Get required input variables for this step
     */
    public function get_required_variables()
    {
        // No strict requirements - depends on assistant's prompt template
        return [];
    }

    /**
     * Get step configuration schema for UI generation
     */
    public function get_config_schema()
    {
        // Get all available assistants
        $assistants = PolyTrans_Assistant_Manager::get_all_assistants();
        $assistant_options = ['' => __('Select an assistant...', 'polytrans')];
        
        foreach ($assistants as $assistant) {
            $label = $assistant['name'];
            if (!empty($assistant['provider'])) {
                $label .= ' (' . ucfirst($assistant['provider']) . ' - ' . $assistant['model'] . ')';
            }
            $assistant_options[$assistant['id']] = $label;
        }

        return [
            'assistant_id' => [
                'type' => 'select',
                'label' => __('AI Assistant', 'polytrans'),
                'description' => __('Select a managed AI assistant configured in the Assistants menu', 'polytrans'),
                'options' => $assistant_options,
                'required' => true
            ],
            'info' => [
                'type' => 'info',
                'content' => __('This step uses assistants configured in PolyTrans > AI Assistants. The assistant\'s prompt template will be rendered with Twig using the workflow context variables.', 'polytrans')
            ]
        ];
    }

    /**
     * Get additional info for the step (shown in workflow editor)
     */
    public function get_step_info($step_config)
    {
        $assistant_id = $step_config['assistant_id'] ?? 0;
        if (empty($assistant_id)) {
            return __('No assistant selected', 'polytrans');
        }

        $assistant = PolyTrans_Assistant_Manager::get_assistant($assistant_id);
        if (!$assistant) {
            return __('Assistant not found', 'polytrans');
        }

        $info = '<strong>' . esc_html($assistant['name']) . '</strong><br>';
        $info .= __('Provider:', 'polytrans') . ' ' . esc_html(ucfirst($assistant['provider'])) . '<br>';
        $info .= __('Model:', 'polytrans') . ' ' . esc_html($assistant['model']) . '<br>';
        $info .= __('Response Format:', 'polytrans') . ' ' . esc_html($assistant['response_format']);

        return $info;
    }
}

