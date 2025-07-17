<?php

/**
 * Workflow Manager
 * 
 * Main coordinator for post-processing workflows. Handles workflow
 * registration, execution triggering, and coordination between components.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Workflow_Manager
{
    private static $instance = null;
    private PolyTrans_Workflow_Storage_Manager $storage_manager;
    private PolyTrans_Workflow_Executor $executor;
    private PolyTrans_Variable_Manager $variable_manager;
    private array $data_providers = [];
    private array $workflow_steps = [];

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Initialize workflow components
     */
    private function init_components()
    {
        // Initialize managers
        require_once plugin_dir_path(__FILE__) . 'managers/class-workflow-storage-manager.php';
        require_once plugin_dir_path(__FILE__) . 'class-workflow-executor.php';
        require_once plugin_dir_path(__FILE__) . 'class-variable-manager.php';

        $this->storage_manager = new PolyTrans_Workflow_Storage_Manager();
        $this->executor = new PolyTrans_Workflow_Executor();
        $this->variable_manager = new PolyTrans_Variable_Manager();

        // Load data providers
        $this->load_data_providers();

        // Register default workflow steps
        $this->register_default_steps();
    }

    /**
     * Load data providers
     */
    private function load_data_providers()
    {
        $provider_files = [
            'providers/class-post-data-provider.php',
            'providers/class-meta-data-provider.php',
            'providers/class-context-data-provider.php',
            'providers/class-articles-data-provider.php'
        ];

        foreach ($provider_files as $file) {
            $file_path = plugin_dir_path(__FILE__) . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        // Register providers
        $this->register_data_provider(new PolyTrans_Post_Data_Provider());
        $this->register_data_provider(new PolyTrans_Meta_Data_Provider());
        $this->register_data_provider(new PolyTrans_Context_Data_Provider());
        $this->register_data_provider(new PolyTrans_Articles_Data_Provider());
    }

    /**
     * Register default workflow step types
     */
    private function register_default_steps()
    {
        // AI Assistant step will be loaded separately
        // For now, we'll define the structure
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks()
    {
        // Hook into translation completion
        add_action('polytrans_translation_completed', [$this, 'trigger_workflows'], 10, 3);

        // Hook for manual workflow execution
        add_action('wp_ajax_polytrans_execute_workflow', [$this, 'ajax_execute_workflow']);

        // Hook for workflow testing
        add_action('wp_ajax_polytrans_test_workflow', [$this, 'ajax_test_workflow']);
    }

    /**
     * Register a data provider
     * 
     * @param PolyTrans_Variable_Provider_Interface $provider
     */
    public function register_data_provider($provider)
    {
        if ($provider instanceof PolyTrans_Variable_Provider_Interface) {
            $this->data_providers[$provider->get_provider_id()] = $provider;
        }
    }

    /**
     * Register a workflow step type
     * 
     * @param PolyTrans_Workflow_Step_Interface $step
     */
    public function register_workflow_step($step)
    {
        if ($step instanceof PolyTrans_Workflow_Step_Interface) {
            $this->workflow_steps[$step->get_type()] = $step;
        }
    }

    /**
     * Get all registered data providers
     * 
     * @return array
     */
    public function get_data_providers()
    {
        return $this->data_providers;
    }

    /**
     * Get all registered workflow step types
     * 
     * @return array
     */
    public function get_workflow_steps()
    {
        return $this->workflow_steps;
    }

    /**
     * Trigger workflows after translation completion
     * 
     * @param int $original_post_id
     * @param int $translated_post_id
     * @param string $target_language
     */
    public function trigger_workflows($original_post_id, $translated_post_id, $target_language)
    {
        try {

            // Get workflows for this language
            $workflows = $this->storage_manager->get_workflows_for_language($target_language);

            if (empty($workflows)) {
                PolyTrans_Logs_Manager::log("No workflows configured for language '{$target_language}'", 'info', [
                    'source' => 'workflow_manager',
                    'original_post_id' => $original_post_id,
                    'translated_post_id' => $translated_post_id,
                    'target_language' => $target_language,
                    'total_workflows' => 0
                ]);
                return;
            }

            // Create execution context
            $context = [
                'original_post_id' => $original_post_id,
                'translated_post_id' => $translated_post_id,
                'target_language' => $target_language,
                'trigger' => 'translation_completed'
            ];

            // Analyze workflow conditions
            $executed_count = 0;
            $skipped_disabled = 0;
            $skipped_manual_only = 0;
            $skipped_conditions = 0;
            $skipped_no_trigger = 0;

            foreach ($workflows as $workflow) {

                if ($this->should_execute_workflow($workflow, $context)) {
                    $this->schedule_workflow_execution($workflow, $context);
                    $executed_count++;
                } else {
                    // Determine skip reason for summary
                    if (!isset($workflow['enabled']) || !$workflow['enabled']) {
                        $skipped_disabled++;
                    } elseif (isset($workflow['triggers']['manual_only']) && $workflow['triggers']['manual_only']) {
                        $skipped_manual_only++;
                    } elseif (!isset($workflow['triggers']['on_translation_complete']) || !$workflow['triggers']['on_translation_complete']) {
                        $skipped_no_trigger++;
                    } else {
                        $skipped_conditions++;
                    }
                }
            }

            PolyTrans_Logs_Manager::log("Workflow summary for '{$target_language}': {$executed_count} executed, " .
                ($skipped_disabled + $skipped_manual_only + $skipped_conditions + $skipped_no_trigger) . " skipped", 'info', [
                'source' => 'workflow_manager',
                'original_post_id' => $original_post_id,
                'translated_post_id' => $translated_post_id,
                'target_language' => $target_language,
                'total_workflows' => count($workflows),
                'executed_count' => $executed_count,
                'skipped_disabled' => $skipped_disabled,
                'skipped_manual_only' => $skipped_manual_only,
                'skipped_no_trigger' => $skipped_no_trigger,
                'skipped_conditions' => $skipped_conditions
            ]);
        } catch (Throwable $e) {
            PolyTrans_Logs_Manager::log("Error when processing workflows for post $original_post_id: " . $e->getMessage(), 'error', [
                'source' => 'workflow_manager',
                'original_post_id' => $original_post_id,
                'translated_post_id' => $translated_post_id,
                'target_language' => $target_language,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check if workflow should be executed for given context
     * 
     * @param array $workflow
     * @param array $context
     * @return bool
     */
    private function should_execute_workflow($workflow, $context)
    {
        $workflow_name = $workflow['name'] ?? 'Unknown';

        // Check if workflow is enabled
        if (!isset($workflow['enabled']) || !$workflow['enabled']) {
            PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' is disabled", 'info', [
                'source' => 'workflow_manager',
                'workflow_name' => $workflow_name,
                'workflow_id' => $workflow['id'] ?? null
            ]);
            return false;
        }

        // Check trigger conditions
        $triggers = $workflow['triggers'] ?? [];

        if ($context['trigger'] === 'translation_completed') {
            if (!isset($triggers['on_translation_complete']) || !$triggers['on_translation_complete']) {
                PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' not configured for translation completion trigger", 'info', [
                    'source' => 'workflow_manager',
                    'workflow_name' => $workflow_name,
                    'workflow_id' => $workflow['id'] ?? null
                ]);
                return false;
            }
        }

        // Check for manual_only flag
        if (isset($triggers['manual_only']) && $triggers['manual_only']) {
            PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' is set to manual execution only", 'info', [
                'source' => 'workflow_manager',
                'workflow_name' => $workflow_name,
                'workflow_id' => $workflow['id'] ?? null
            ]);
            return false;
        }

        // Check additional conditions (post type, categories, etc.)
        if (isset($triggers['conditions']) && !empty($triggers['conditions'])) {
            $conditions_met = $this->evaluate_workflow_conditions($triggers['conditions'], $context);
            if (!$conditions_met) {
                PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' conditions not met", 'info', [
                    'source' => 'workflow_manager',
                    'workflow_name' => $workflow_name,
                    'workflow_id' => $workflow['id'] ?? null
                ]);
                return false;
            }
        }

        PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' passed all conditions", 'info', [
            'source' => 'workflow_manager',
            'workflow_name' => $workflow_name,
            'workflow_id' => $workflow['id'] ?? null
        ]);
        return true;
    }

    /**
     * Evaluate workflow trigger conditions
     * 
     * @param array $conditions
     * @param array $context
     * @return bool
     */
    private function evaluate_workflow_conditions($conditions, $context)
    {
        $original_post = get_post($context['original_post_id']);
        if (!$original_post) {
            return false;
        }

        // Check post type condition
        if (isset($conditions['post_type']) && !empty($conditions['post_type'])) {
            if (!in_array($original_post->post_type, $conditions['post_type'])) {
                return false;
            }
        }

        // Check category condition
        if (isset($conditions['category']) && !empty($conditions['category'])) {
            $post_categories = wp_get_post_categories($original_post->ID, ['fields' => 'slugs']);
            if (empty(array_intersect($conditions['category'], $post_categories))) {
                return false;
            }
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Schedule workflow execution (background processing)
     * 
     * @param array $workflow
     * @param array $context
     */
    private function schedule_workflow_execution($workflow, $context)
    {
        // For now, execute immediately
        // In production, this should be queued for background processing
        $this->execute_workflow($workflow, $context);
    }

    /**
     * Execute a workflow
     * 
     * @param array $workflow
     * @param array $context
     * @param bool $test_mode Whether to run in test mode
     * @return array Execution result
     */
    public function execute_workflow($workflow, $context, $test_mode = false)
    {
        $workflow_name = $workflow['name'] ?? 'Unknown';
        $workflow_id = $workflow['id'] ?? 'unknown';

        PolyTrans_Logs_Manager::log("Starting execution of workflow '{$workflow_name}' (ID: {$workflow_id})", 'info', [
            'source' => 'workflow_manager',
            'workflow_name' => $workflow_name,
            'workflow_id' => $workflow_id,
            'post_id' => $context['translated_post_id'] ?? null
        ]);

        try {
            // Build variable context
            $variable_context = $this->variable_manager->build_context($context, $this->data_providers);

            // Execute workflow using executor
            $result = $this->executor->execute($workflow, $variable_context, $test_mode);

            // Log execution (only in production mode)
            if (!$test_mode) {
                $this->log_workflow_execution($workflow, $context, $result);
            }

            if ($result['success']) {
                PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' execution completed successfully", 'info', [
                    'source' => 'workflow_manager',
                    'workflow_name' => $workflow_name,
                    'workflow_id' => $workflow_id,
                    'post_id' => $context['translated_post_id'] ?? null
                ]);
            } else {
                $error_msg = $result['error'] ?? 'Unknown error';
                PolyTrans_Logs_Manager::log("Workflow '{$workflow_name}' execution failed: {$error_msg}", 'error', [
                    'source' => 'workflow_manager',
                    'workflow_name' => $workflow_name,
                    'workflow_id' => $workflow_id,
                    'error' => $error_msg,
                    'post_id' => $context['translated_post_id'] ?? null
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            PolyTrans_Logs_Manager::log("Exception during workflow '{$workflow_name}' execution: " . $e->getMessage(), 'error', [
                'source' => 'workflow_manager',
                'workflow_name' => $workflow_name,
                'workflow_id' => $workflow_id,
                'exception' => $e->getMessage(),
                'post_id' => $context['translated_post_id'] ?? null
            ]);
            PolyTrans_Logs_Manager::log("Exception stack trace: " . $e->getTraceAsString(), 'debug', [
                'source' => 'workflow_manager',
                'workflow_name' => $workflow_name,
                'workflow_id' => $workflow_id,
                'trace' => $e->getTraceAsString()
            ]);
            $error_result = [
                'success' => false,
                'error' => $e->getMessage(),
                'workflow_id' => $workflow['id'] ?? 'unknown'
            ];

            $this->log_workflow_execution($workflow, $context, $error_result);

            return $error_result;
        }
    }

    /**
     * Log workflow execution
     * 
     * @param array $workflow
     * @param array $context
     * @param array $result
     */
    private function log_workflow_execution($workflow, $context, $result)
    {
        $log_data = [
            'action' => 'workflow_execution',
            'workflow_id' => $workflow['id'] ?? 'unknown',
            'workflow_name' => $workflow['name'] ?? 'Unknown',
            'context' => $context,
            'success' => $result['success'] ?? false,
            'steps_executed' => $result['steps_executed'] ?? 0,
            'execution_time' => $result['execution_time'] ?? 0,
            'error' => $result['error'] ?? null
        ];

        // Use existing logging system
        if (class_exists('PolyTrans_Logs_Manager')) {
            $logs_manager = new PolyTrans_Logs_Manager();
            $logs_manager->log('info', 'Post-processing workflow executed', $log_data);
        }
    }

    /**
     * AJAX handler for manual workflow execution
     */
    public function ajax_execute_workflow()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $workflow_id = sanitize_text_field($_POST['workflow_id'] ?? '');
        $original_post_id = intval($_POST['original_post_id'] ?? 0);
        $translated_post_id = intval($_POST['translated_post_id'] ?? 0);
        $target_language = sanitize_text_field($_POST['target_language'] ?? '');

        if (empty($workflow_id) || empty($original_post_id) || empty($translated_post_id)) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        // Get workflow
        $workflow = $this->storage_manager->get_workflow($workflow_id);
        if (!$workflow) {
            wp_send_json_error('Workflow not found');
            return;
        }

        // Execute workflow
        $context = [
            'original_post_id' => $original_post_id,
            'translated_post_id' => $translated_post_id,
            'target_language' => $target_language,
            'trigger' => 'manual'
        ];

        $result = $this->execute_workflow($workflow, $context);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for workflow testing
     */
    public function ajax_test_workflow()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Get test data from request
        $workflow_data = $_POST['workflow'] ?? [];
        $test_context = $_POST['test_context'] ?? [];

        // Remove WordPress magic quotes if they exist
        $workflow_data = wp_unslash($workflow_data);
        $test_context = wp_unslash($test_context);

        // Validate workflow data
        if (empty($workflow_data)) {
            wp_send_json_error('No workflow data provided');
            return;
        }

        try {
            // Create test workflow
            $workflow = [
                'id' => 'test_' . uniqid(),
                'name' => 'Test Workflow',
                'language' => $test_context['language'] ?? 'en',
                'enabled' => true,
                'steps' => $workflow_data['steps'] ?? []
            ];

            // Execute test in test mode
            $result = $this->execute_workflow($workflow, $test_context, true);

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get storage manager
     * 
     * @return PolyTrans_Workflow_Storage_Manager
     */
    public function get_storage_manager()
    {
        return $this->storage_manager;
    }

    /**
     * Get executor
     * 
     * @return PolyTrans_Workflow_Executor
     */
    public function get_executor()
    {
        return $this->executor;
    }

    /**
     * Get variable manager
     * 
     * @return PolyTrans_Variable_Manager
     */
    public function get_variable_manager()
    {
        return $this->variable_manager;
    }
}
