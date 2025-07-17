<?php

/**
 * Workflow Storage Manager
 * 
 * Handles saving, loading, and managing workflow definitions
 * in the WordPress database.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Workflow_Storage_Manager
{
    const OPTION_NAME = 'polytrans_workflows';
    const SETTINGS_OPTION_NAME = 'polytrans_postprocessing_settings';

    /**
     * Get all workflows
     * 
     * @return array Array of workflow definitions
     */
    public function get_all_workflows()
    {
        $workflows = get_option(self::OPTION_NAME, []);

        // Ensure workflows is an array
        if (!is_array($workflows)) {
            $workflows = [];
        }

        return $workflows;
    }

    /**
     * Get workflows for a specific language
     * 
     * @param string $language Language code
     * @return array Array of workflows for the language
     */
    public function get_workflows_for_language($language)
    {
        $all_workflows = $this->get_all_workflows();
        $language_workflows = [];

        foreach ($all_workflows as $workflow) {
            if (isset($workflow['language']) && $workflow['language'] === $language) {
                $language_workflows[] = $workflow;
            }
        }

        return $language_workflows;
    }

    /**
     * Get a specific workflow by ID
     * 
     * @param string $workflow_id Workflow ID
     * @return array|null Workflow definition or null if not found
     */
    public function get_workflow($workflow_id)
    {
        $workflows = $this->get_all_workflows();

        foreach ($workflows as $workflow) {
            if (isset($workflow['id']) && $workflow['id'] === $workflow_id) {
                return $workflow;
            }
        }

        return null;
    }

    /**
     * Save a workflow
     * 
     * @param array $workflow Workflow definition
     * @return bool Success status
     */
    public function save_workflow($workflow)
    {
        // Validate workflow structure
        $validation = $this->validate_workflow($workflow);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        $workflows = $this->get_all_workflows();
        $workflow_id = $workflow['id'];

        // Find existing workflow or add new one
        $found = false;
        foreach ($workflows as $index => $existing_workflow) {
            if (isset($existing_workflow['id']) && $existing_workflow['id'] === $workflow_id) {
                $workflows[$index] = $workflow;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $workflows[] = $workflow;
        }

        // Only update if workflows have changed
        $existing_workflows = get_option(self::OPTION_NAME, []);
        if ($workflows === $existing_workflows) {
            $success = true;
        } else {
            $success = update_option(self::OPTION_NAME, $workflows);
        }
        $errors = [];

        if (!$success) {
            $errors[] = 'Unknown error updating option';
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Delete a workflow
     * 
     * @param string $workflow_id Workflow ID
     * @return bool Success status
     */
    public function delete_workflow($workflow_id)
    {
        $workflows = $this->get_all_workflows();
        $updated_workflows = [];

        foreach ($workflows as $workflow) {
            if (!isset($workflow['id']) || $workflow['id'] !== $workflow_id) {
                $updated_workflows[] = $workflow;
            }
        }

        return update_option(self::OPTION_NAME, $updated_workflows);
    }

    /**
     * Duplicate a workflow
     * 
     * @param string $workflow_id Source workflow ID
     * @param string $new_name New workflow name
     * @return string|false New workflow ID or false on failure
     */
    public function duplicate_workflow($workflow_id, $new_name = '')
    {
        $workflow = $this->get_workflow($workflow_id);
        if (!$workflow) {
            return false;
        }

        // Generate new ID and name
        $new_id = 'workflow_' . uniqid();
        $workflow['id'] = $new_id;
        $workflow['name'] = $new_name ?: ($workflow['name'] . ' (Copy)');

        // Save the duplicated workflow
        if ($this->save_workflow($workflow)) {
            return $new_id;
        }

        return false;
    }

    /**
     * Validate workflow structure
     * 
     * @param array $workflow Workflow to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validate_workflow($workflow)
    {
        $errors = [];

        // Check required fields
        if (!isset($workflow['id']) || empty($workflow['id'])) {
            $errors[] = 'Workflow ID is required';
        }

        if (!isset($workflow['name']) || empty($workflow['name'])) {
            $errors[] = 'Workflow name is required';
        }

        if (!isset($workflow['language']) || empty($workflow['language'])) {
            $errors[] = 'Workflow language is required';
        }

        if (!isset($workflow['steps']) || !is_array($workflow['steps'])) {
            $errors[] = 'Workflow must have steps array';
        } elseif (empty($workflow['steps'])) {
            $errors[] = 'Workflow must have at least one step';
        }

        // Validate steps
        if (isset($workflow['steps']) && is_array($workflow['steps'])) {
            foreach ($workflow['steps'] as $index => $step) {
                $step_errors = $this->validate_step($step, $index);
                $errors = array_merge($errors, $step_errors);
            }
        }

        // Check for duplicate step IDs
        if (isset($workflow['steps']) && is_array($workflow['steps'])) {
            $step_ids = [];
            foreach ($workflow['steps'] as $step) {
                if (isset($step['id'])) {
                    if (in_array($step['id'], $step_ids)) {
                        $errors[] = "Duplicate step ID: {$step['id']}";
                    }
                    $step_ids[] = $step['id'];
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate individual step
     * 
     * @param array $step Step configuration
     * @param int $index Step index
     * @return array Array of error messages
     */
    private function validate_step($step, $index)
    {
        $errors = [];

        // Check required fields
        if (!isset($step['id']) || empty($step['id'])) {
            $errors[] = "Step {$index}: ID is required";
        }

        if (!isset($step['type']) || empty($step['type'])) {
            $errors[] = "Step {$index}: Type is required";
        }

        if (!isset($step['name']) || empty($step['name'])) {
            $errors[] = "Step {$index}: Name is required";
        }

        // Validate specific step types
        if (isset($step['type'])) {
            switch ($step['type']) {
                case 'ai_assistant':
                    if (!isset($step['system_prompt']) || empty($step['system_prompt'])) {
                        $errors[] = "Step {$index}: System prompt is required for AI assistant steps";
                    }
                    break;

                    // Add validation for other step types as they are implemented
            }
        }

        return $errors;
    }

    /**
     * Get post-processing settings
     * 
     * @return array Settings array
     */
    public function get_settings()
    {
        return get_option(self::SETTINGS_OPTION_NAME, [
            'enabled' => true,
            'max_execution_time' => 300, // 5 minutes
            'retry_attempts' => 3,
            'log_level' => 'info'
        ]);
    }

    /**
     * Save post-processing settings
     * 
     * @param array $settings Settings to save
     * @return bool Success status
     */
    public function save_settings($settings)
    {
        $current_settings = $this->get_settings();
        $updated_settings = array_merge($current_settings, $settings);

        return update_option(self::SETTINGS_OPTION_NAME, $updated_settings);
    }

    /**
     * Export workflows to JSON
     * 
     * @param array $workflow_ids Array of workflow IDs to export (empty for all)
     * @return string JSON export data
     */
    public function export_workflows($workflow_ids = [])
    {
        $workflows = $this->get_all_workflows();

        if (!empty($workflow_ids)) {
            $workflows = array_filter($workflows, function ($workflow) use ($workflow_ids) {
                return isset($workflow['id']) && in_array($workflow['id'], $workflow_ids);
            });
        }

        $export_data = [
            'version' => '1.0',
            'export_date' => current_time('Y-m-d H:i:s'),
            'workflows' => array_values($workflows)
        ];

        return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import workflows from JSON
     * 
     * @param string $json_data JSON import data
     * @param bool $overwrite Whether to overwrite existing workflows
     * @return array Import result with 'success', 'imported', 'skipped', 'errors'
     */
    public function import_workflows($json_data, $overwrite = false)
    {
        $result = [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        $import_data = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = 'Invalid JSON format';
            return $result;
        }

        if (!isset($import_data['workflows']) || !is_array($import_data['workflows'])) {
            $result['errors'][] = 'No workflows found in import data';
            return $result;
        }

        foreach ($import_data['workflows'] as $workflow) {
            // Generate new ID if workflow already exists and not overwriting
            if (!$overwrite && $this->get_workflow($workflow['id'])) {
                $workflow['id'] = 'workflow_' . uniqid();
                $workflow['name'] = $workflow['name'] . ' (Imported)';
            }

            // Validate workflow
            $validation = $this->validate_workflow($workflow);
            if (!$validation['valid']) {
                $result['errors'][] = "Workflow '{$workflow['name']}': " . implode(', ', $validation['errors']);
                $result['skipped']++;
                continue;
            }

            // Save workflow
            if ($this->save_workflow($workflow)) {
                $result['imported']++;
            } else {
                $result['errors'][] = "Failed to save workflow '{$workflow['name']}'";
                $result['skipped']++;
            }
        }

        $result['success'] = $result['imported'] > 0;
        return $result;
    }

    /**
     * Get workflow statistics
     * 
     * @return array Statistics about workflows
     */
    public function get_workflow_statistics()
    {
        $workflows = $this->get_all_workflows();

        $stats = [
            'total_workflows' => count($workflows),
            'enabled_workflows' => 0,
            'disabled_workflows' => 0,
            'languages' => [],
            'step_types' => []
        ];

        foreach ($workflows as $workflow) {
            // Count enabled/disabled
            if (isset($workflow['enabled']) && $workflow['enabled']) {
                $stats['enabled_workflows']++;
            } else {
                $stats['disabled_workflows']++;
            }

            // Count languages
            if (isset($workflow['language'])) {
                $lang = $workflow['language'];
                $stats['languages'][$lang] = ($stats['languages'][$lang] ?? 0) + 1;
            }

            // Count step types
            if (isset($workflow['steps']) && is_array($workflow['steps'])) {
                foreach ($workflow['steps'] as $step) {
                    if (isset($step['type'])) {
                        $type = $step['type'];
                        $stats['step_types'][$type] = ($stats['step_types'][$type] ?? 0) + 1;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Clean up orphaned workflow data
     * 
     * @return int Number of items cleaned up
     */
    public function cleanup_orphaned_data()
    {
        $cleaned = 0;

        // Clean up workflows with invalid structure
        $workflows = $this->get_all_workflows();
        $valid_workflows = [];

        foreach ($workflows as $workflow) {
            $validation = $this->validate_workflow($workflow);
            if ($validation['valid']) {
                $valid_workflows[] = $workflow;
            } else {
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            update_option(self::OPTION_NAME, $valid_workflows);
        }

        return $cleaned;
    }
}
