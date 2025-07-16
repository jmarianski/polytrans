<?php

/**
 * Workflow Executor
 * 
 * Handles the execution of individual workflow steps and manages
 * the flow of data between steps.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Workflow_Executor
{
    private $step_registry = [];
    private $output_processor;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->register_default_steps();
        $this->output_processor = PolyTrans_Workflow_Output_Processor::get_instance();
    }

    /**
     * Register default step types
     */
    private function register_default_steps()
    {
        // AI Assistant step
        require_once plugin_dir_path(__FILE__) . 'steps/class-ai-assistant-step.php';
        $this->register_step(new PolyTrans_AI_Assistant_Step());

        // Predefined Assistant step
        require_once plugin_dir_path(__FILE__) . 'steps/class-predefined-assistant-step.php';
        $this->register_step(new PolyTrans_Predefined_Assistant_Step());
    }

    /**
     * Register a workflow step type
     * 
     * @param PolyTrans_Workflow_Step_Interface $step
     */
    public function register_step($step)
    {
        if ($step instanceof PolyTrans_Workflow_Step_Interface) {
            $this->step_registry[$step->get_type()] = $step;
        }
    }

    /**
     * Execute a complete workflow
     * 
     * @param array $workflow Workflow definition
     * @param array $context Variable context
     * @param bool $test_mode Whether to run in test mode (returns change objects instead of executing)
     * @return array Execution result
     */
    public function execute($workflow, $context, $test_mode = false)
    {
        $start_time = microtime(true);
        $steps_executed = 0;
        $step_results = [];
        $execution_context = $context; // Copy context for modification

        try {
            // Validate workflow
            $validation = $this->validate_workflow($workflow);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Workflow validation failed: ' . implode(', ', $validation['errors']),
                    'steps_executed' => 0,
                    'execution_time' => 0,
                    'test_mode' => $test_mode
                ];
            }

            // Execute each step
            foreach ($workflow['steps'] as $step_index => $step_config) {
                // Skip disabled steps
                if (isset($step_config['enabled']) && !$step_config['enabled']) {
                    continue;
                }

                // Execute step
                $step_result = $this->execute_step($step_config, $execution_context);

                $steps_executed++;
                $step_results[] = [
                    'step_id' => $step_config['id'] ?? "step_{$step_index}",
                    'step_name' => $step_config['name'] ?? 'Unnamed Step',
                    'step_type' => $step_config['type'] ?? 'unknown',
                    'step_config' => $step_config, // Include full step configuration
                    'input_variables' => $this->extract_input_variables($execution_context),
                    'success' => $step_result['success'],
                    'data' => $step_result['data'] ?? null,
                    'error' => $step_result['error'] ?? null,
                    'execution_time' => $step_result['execution_time'] ?? 0,
                    // Include interpolated prompts if available
                    'interpolated_system_prompt' => $step_result['interpolated_system_prompt'] ?? null,
                    'interpolated_user_message' => $step_result['interpolated_user_message'] ?? null,
                    'tokens_used' => $step_result['tokens_used'] ?? null,
                    'raw_response' => $step_result['raw_response'] ?? null
                ];

                // Process output actions if step succeeded
                if ($step_result['success'] && isset($step_config['output_actions']) && !empty($step_config['output_actions'])) {
                    $output_result = $this->output_processor->process_step_outputs(
                        $step_result,
                        $step_config['output_actions'],
                        $execution_context,
                        $test_mode
                    );

                    // Add output processing result to step result
                    $step_results[count($step_results) - 1]['output_processing'] = $output_result;

                    // In test mode, update the execution context with the changes
                    if ($test_mode && isset($output_result['updated_context'])) {
                        $execution_context = $output_result['updated_context'];
                    }

                    // If output processing failed but we want to continue, log the error
                    if (!$output_result['success']) {
                        error_log('PolyTrans: Output processing failed for step ' . $step_config['id'] . ': ' . implode(', ', $output_result['errors'] ?? []));
                    }
                }

                // If step failed and we're not continuing on error, stop execution
                if (!$step_result['success']) {
                    $continue_on_error = $step_config['continue_on_error'] ?? false;
                    if (!$continue_on_error) {
                        break;
                    }
                }

                // Merge step output into execution context for next steps
                if ($step_result['success'] && isset($step_result['data'])) {
                    $this->merge_step_output($execution_context, $step_config, $step_result['data']);
                }
            }

            $execution_time = microtime(true) - $start_time;

            // Determine overall success
            $overall_success = true;
            foreach ($step_results as $result) {
                if (!$result['success']) {
                    $overall_success = false;
                    break;
                }
            }

            return [
                'success' => $overall_success,
                'steps_executed' => $steps_executed,
                'execution_time' => $execution_time,
                'step_results' => $step_results,
                'final_context' => $execution_context,
                'test_mode' => $test_mode
            ];
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'steps_executed' => $steps_executed,
                'execution_time' => $execution_time,
                'step_results' => $step_results
            ];
        }
    }

    /**
     * Execute a single workflow step
     * 
     * @param array $step_config Step configuration
     * @param array $context Execution context
     * @return array Step execution result
     */
    private function execute_step($step_config, $context)
    {
        $start_time = microtime(true);

        try {
            // Get step type
            $step_type = $step_config['type'] ?? '';
            if (empty($step_type)) {
                return [
                    'success' => false,
                    'error' => 'Step type not specified',
                    'execution_time' => microtime(true) - $start_time
                ];
            }

            // Get step handler
            if (!isset($this->step_registry[$step_type])) {
                return [
                    'success' => false,
                    'error' => "Unknown step type: {$step_type}",
                    'execution_time' => microtime(true) - $start_time
                ];
            }

            $step_handler = $this->step_registry[$step_type];

            // Validate step configuration
            $validation = $step_handler->validate_config($step_config);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Step configuration validation failed: ' . implode(', ', $validation['errors']),
                    'execution_time' => microtime(true) - $start_time
                ];
            }

            // Check required variables
            $required_vars = $step_handler->get_required_variables();
            $missing_vars = [];
            foreach ($required_vars as $var_name) {
                if (!$this->variable_exists_in_context($var_name, $context)) {
                    $missing_vars[] = $var_name;
                }
            }

            if (!empty($missing_vars)) {
                return [
                    'success' => false,
                    'error' => 'Missing required variables: ' . implode(', ', $missing_vars),
                    'execution_time' => microtime(true) - $start_time
                ];
            }

            // Execute step
            $result = $step_handler->execute($context, $step_config);
            $result['execution_time'] = microtime(true) - $start_time;

            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Check if a variable exists in the context (supports dot notation)
     * 
     * @param string $var_name Variable name (supports dot notation like 'original_post.title')
     * @param array $context Execution context
     * @return bool
     */
    private function variable_exists_in_context($var_name, $context)
    {
        $parts = explode('.', $var_name);
        $current = $context;

        foreach ($parts as $part) {
            if (!is_array($current) || !isset($current[$part])) {
                return false;
            }
            $current = $current[$part];
        }

        return true;
    }

    /**
     * Merge step output into execution context
     * 
     * @param array &$context Execution context (passed by reference)
     * @param array $step_config Step configuration
     * @param mixed $step_output Step output data
     */
    private function merge_step_output(&$context, $step_config, $step_output)
    {
        $step_id = $step_config['id'] ?? 'unknown_step';

        // Initialize previous_steps if not exists
        if (!isset($context['previous_steps'])) {
            $context['previous_steps'] = [];
        }

        // Store step output
        $context['previous_steps'][$step_id] = $step_output;

        // If step specifies output variables, extract them to top level
        if (isset($step_config['output_variables']) && is_array($step_config['output_variables'])) {
            foreach ($step_config['output_variables'] as $var_name) {
                if (is_array($step_output) && isset($step_output[$var_name])) {
                    $context[$var_name] = $step_output[$var_name];
                }
            }
        }
    }

    /**
     * Validate workflow structure
     * 
     * @param array $workflow Workflow to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    private function validate_workflow($workflow)
    {
        $errors = [];

        // Check basic structure
        if (empty($workflow['steps']) || !is_array($workflow['steps'])) {
            $errors[] = 'Workflow must have at least one step';
        }

        // Validate each step
        foreach ($workflow['steps'] as $index => $step) {
            $step_errors = $this->validate_step_structure($step, $index);
            $errors = array_merge($errors, $step_errors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate individual step structure
     * 
     * @param array $step Step configuration
     * @param int $index Step index
     * @return array Array of error messages
     */
    private function validate_step_structure($step, $index)
    {
        $errors = [];

        // Check required fields
        if (!isset($step['type']) || empty($step['type'])) {
            $errors[] = "Step {$index}: Type is required";
        }

        if (!isset($step['id']) || empty($step['id'])) {
            $errors[] = "Step {$index}: ID is required";
        }

        // Check if step type is registered
        if (isset($step['type']) && !isset($this->step_registry[$step['type']])) {
            $errors[] = "Step {$index}: Unknown step type '{$step['type']}'";
        }

        return $errors;
    }

    /**
     * Get registered step types
     * 
     * @return array Array of registered step handlers
     */
    public function get_registered_steps()
    {
        return $this->step_registry;
    }

    /**
     * Get step handler by type
     * 
     * @param string $type Step type
     * @return PolyTrans_Workflow_Step_Interface|null
     */
    public function get_step_handler($type)
    {
        return $this->step_registry[$type] ?? null;
    }

    /**
     * Extract relevant input variables for display purposes
     */
    private function extract_input_variables($context)
    {
        $input_vars = [];

        // Core post variables
        if (isset($context['title'])) {
            $input_vars['title'] = $context['title'];
        }
        if (isset($context['content'])) {
            $input_vars['content'] = strlen($context['content']) > 300
                ? substr($context['content'], 0, 300) . '...'
                : $context['content'];
        }
        if (isset($context['excerpt'])) {
            $input_vars['excerpt'] = $context['excerpt'];
        }
        if (isset($context['author_name'])) {
            $input_vars['author_name'] = $context['author_name'];
        }
        if (isset($context['date'])) {
            $input_vars['date'] = $context['date'];
        }

        // Post meta variables
        if (isset($context['translated_post']['meta']) && is_array($context['translated_post']['meta'])) {
            $input_vars['meta'] = $context['translated_post']['meta'];
        }

        // Context variables
        if (isset($context['target_language'])) {
            $input_vars['target_language'] = $context['target_language'];
        }
        if (isset($context['source_language'])) {
            $input_vars['source_language'] = $context['source_language'];
        }
        if (isset($context['translation_service'])) {
            $input_vars['translation_service'] = $context['translation_service'];
        }
        if (isset($context['quality_score'])) {
            $input_vars['quality_score'] = $context['quality_score'];
        }
        if (isset($context['word_count'])) {
            $input_vars['word_count'] = $context['word_count'];
        }

        return $input_vars;
    }
}
