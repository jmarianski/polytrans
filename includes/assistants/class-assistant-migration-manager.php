<?php

/**
 * Assistant Migration Manager
 * 
 * Handles automatic migration of legacy workflow steps to managed assistants.
 * Runs on plugin activation/update to ensure consistency between system assistants
 * and workflow configurations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Assistant_Migration_Manager
{
    /**
     * Run migration
     * 
     * Migrates all ai_assistant steps to managed_assistant steps by:
     * 1. Creating managed assistants from ai_assistant configurations
     * 2. Updating workflow steps to reference the new managed assistants
     * 3. Preserving all output actions and settings
     * 
     * @return array Migration result with statistics
     */
    public static function migrate_workflows_to_managed_assistants()
    {
        $stats = [
            'workflows_processed' => 0,
            'steps_migrated' => 0,
            'assistants_created' => 0,
            'errors' => []
        ];

        PolyTrans_Logs_Manager::log("Starting workflow migration to managed assistants", 'info');

        try {
            // Get all workflows
            $storage_manager = new PolyTrans_Workflow_Storage_Manager();
            $workflows = $storage_manager->get_all_workflows();
            
            PolyTrans_Logs_Manager::log("Found " . count($workflows) . " workflows to check", 'info');

            if (empty($workflows)) {
                PolyTrans_Logs_Manager::log("No workflows found, migration skipped", 'info');
                return $stats;
            }

            foreach ($workflows as $workflow) {
                $workflow_modified = false;
                $stats['workflows_processed']++;
                
                PolyTrans_Logs_Manager::log("Checking workflow: {$workflow['name']} (ID: {$workflow['id']})", 'debug');

                if (empty($workflow['steps']) || !is_array($workflow['steps'])) {
                    PolyTrans_Logs_Manager::log("Workflow {$workflow['name']} has no steps, skipping", 'debug');
                    continue;
                }

                foreach ($workflow['steps'] as $step_index => &$step) {
                    $step_type = $step['type'] ?? 'unknown';
                    PolyTrans_Logs_Manager::log("Step {$step_index}: {$step['name']} (type: {$step_type})", 'debug');
                    
                    // Only migrate ai_assistant steps
                    if ($step_type !== 'ai_assistant') {
                        PolyTrans_Logs_Manager::log("Step {$step_index} is not ai_assistant, skipping", 'debug');
                        continue;
                    }
                    
                    PolyTrans_Logs_Manager::log("Migrating step {$step_index}: {$step['name']}", 'info');

                    try {
                        // Create managed assistant from ai_assistant config
                        PolyTrans_Logs_Manager::log("Creating managed assistant from step config", 'debug');
                        $assistant_id = self::create_managed_assistant_from_step($step, $workflow);
                        
                        if ($assistant_id) {
                            PolyTrans_Logs_Manager::log("Assistant created with ID: {$assistant_id}", 'debug');
                            
                            // Update step to use managed assistant
                            $step = self::convert_step_to_managed($step, $assistant_id);
                            $stats['steps_migrated']++;
                            $stats['assistants_created']++;
                            $workflow_modified = true;

                            PolyTrans_Logs_Manager::log(
                                "Migrated workflow '{$workflow['name']}' step '{$step['name']}' to managed assistant (ID: {$assistant_id})",
                                'info',
                                ['workflow_id' => $workflow['id'], 'step_index' => $step_index, 'assistant_id' => $assistant_id]
                            );
                        } else {
                            $error_msg = "Failed to create managed assistant for step '{$step['name']}' in workflow '{$workflow['name']}' - create_managed_assistant_from_step returned false";
                            $stats['errors'][] = $error_msg;
                            PolyTrans_Logs_Manager::log($error_msg, 'error');
                        }
                    } catch (Exception $e) {
                        $error_msg = "Failed to migrate step '{$step['name']}' in workflow '{$workflow['name']}': {$e->getMessage()}";
                        $stats['errors'][] = $error_msg;
                        PolyTrans_Logs_Manager::log($error_msg, 'error');
                        PolyTrans_Logs_Manager::log("Exception trace: " . $e->getTraceAsString(), 'error');
                    }
                }

                // Save workflow if modified
                if ($workflow_modified) {
                    $storage_manager->save_workflow($workflow);
                }
            }

            // Log migration summary
            PolyTrans_Logs_Manager::log(
                "Assistant migration completed: {$stats['workflows_processed']} workflows processed, {$stats['steps_migrated']} steps migrated, {$stats['assistants_created']} assistants created",
                'info',
                $stats
            );

        } catch (Exception $e) {
            $stats['errors'][] = "Migration failed: {$e->getMessage()}";
            PolyTrans_Logs_Manager::log("Assistant migration failed: {$e->getMessage()}", 'error');
        }

        return $stats;
    }

    /**
     * Create managed assistant from ai_assistant step configuration
     * 
     * @param array $step ai_assistant step configuration
     * @param array $workflow Parent workflow (for context in naming)
     * @return int|false Assistant ID or false on failure
     */
    private static function create_managed_assistant_from_step($step, $workflow)
    {
        PolyTrans_Logs_Manager::log("create_managed_assistant_from_step called", 'debug');
        
        // Build assistant name from workflow and step names
        $workflow_name = $workflow['name'] ?? 'Unnamed Workflow';
        $step_name = $step['name'] ?? 'Unnamed Step';
        $assistant_name = "[Migrated] {$workflow_name} - {$step_name}";
        
        PolyTrans_Logs_Manager::log("Assistant name: {$assistant_name}", 'debug');

        // Extract configuration from step
        $system_prompt = $step['system_prompt'] ?? '';
        $user_message = $step['user_message'] ?? '';
        
        PolyTrans_Logs_Manager::log("System prompt length: " . strlen($system_prompt) . ", User message length: " . strlen($user_message), 'debug');
        
        // Combine system prompt and user message into single prompt template
        // Using Twig syntax for better variable handling
        $prompt_template = self::build_prompt_template($system_prompt, $user_message);
        
        PolyTrans_Logs_Manager::log("Prompt template length: " . strlen($prompt_template), 'debug');

        // Extract model (default to gpt-4o-mini if not specified)
        $model = !empty($step['model']) ? $step['model'] : 'gpt-4o-mini';

        // Extract response format
        $response_format = $step['expected_format'] ?? 'text';

        // Extract configuration
        $config = [
            'temperature' => $step['temperature'] ?? 0.7,
            'max_tokens' => $step['max_tokens'] ?? 2000
        ];

        // Add migration metadata
        $config['migrated_from'] = [
            'workflow_id' => $workflow['id'] ?? null,
            'workflow_name' => $workflow_name,
            'step_name' => $step_name,
            'migration_date' => current_time('mysql')
        ];

        // Create assistant data matching Assistant Manager structure
        // Note: Assistant Manager expects system_prompt and api_parameters, not prompt_template and config
        $api_parameters = [
            'model' => $model,
            'temperature' => $config['temperature'],
            'max_tokens' => $config['max_tokens'],
            'migrated_from' => $config['migrated_from']
        ];
        
        $assistant_data = [
            'name' => $assistant_name,
            'provider' => 'openai',
            'system_prompt' => $prompt_template, // Using prompt_template as system_prompt
            'user_message_template' => '', // Empty for migrated assistants (prompt is combined)
            'api_parameters' => json_encode($api_parameters),
            'expected_format' => $response_format,
            'output_variables' => '' // Can be added later if needed
        ];

        // Check if similar assistant already exists (to avoid duplicates)
        PolyTrans_Logs_Manager::log("Checking for existing assistant", 'debug');
        $existing_assistant = self::find_existing_assistant($assistant_name, $prompt_template);
        if ($existing_assistant) {
            PolyTrans_Logs_Manager::log(
                "Reusing existing assistant '{$assistant_name}' (ID: {$existing_assistant['id']})",
                'info'
            );
            return $existing_assistant['id'];
        }

        // Create new assistant
        PolyTrans_Logs_Manager::log("Creating new assistant: {$assistant_name}", 'debug');
        PolyTrans_Logs_Manager::log("Assistant data: " . json_encode($assistant_data), 'debug');
        
        $result = PolyTrans_Assistant_Manager::create_assistant($assistant_data);
        
        // Check if result is WP_Error
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            PolyTrans_Logs_Manager::log(
                "Failed to create assistant '{$assistant_name}': {$error_message}",
                'error',
                ['error_data' => $error_data]
            );
            return false;
        }
        
        PolyTrans_Logs_Manager::log("Assistant created successfully with ID: {$result}", 'info');
        return $result;
    }

    /**
     * Build prompt template from system prompt and user message
     * 
     * Converts legacy {variable} syntax to Twig {{ variable }} syntax
     * 
     * @param string $system_prompt System prompt
     * @param string $user_message User message template
     * @return string Combined prompt template
     */
    private static function build_prompt_template($system_prompt, $user_message)
    {
        $template = '';

        // Add system prompt as context
        if (!empty($system_prompt)) {
            $template .= "# System Instructions\n\n";
            $template .= $system_prompt . "\n\n";
        }

        // Add user message
        if (!empty($user_message)) {
            $template .= "# Task\n\n";
            $template .= $user_message;
        }

        // Convert legacy {variable} syntax to Twig {{ variable }}
        // But only if not already using Twig syntax
        if (strpos($template, '{{') === false && strpos($template, '{%') === false) {
            $template = preg_replace('/\{([a-zA-Z0-9_\.]+)\}/', '{{ $1 }}', $template);
        }

        return $template;
    }

    /**
     * Convert ai_assistant step to managed_assistant step
     * 
     * @param array $step Original step configuration
     * @param int $assistant_id Managed assistant ID
     * @return array Updated step configuration
     */
    private static function convert_step_to_managed($step, $assistant_id)
    {
        // Keep only essential fields for managed_assistant step
        $converted_step = [
            'id' => $step['id'] ?? uniqid('step_'),
            'name' => $step['name'] ?? 'Unnamed Step',
            'type' => 'managed_assistant',
            'enabled' => $step['enabled'] ?? true,
            'assistant_id' => $assistant_id,
            'output_actions' => $step['output_actions'] ?? []
        ];

        // Add migration metadata
        $converted_step['_migration_info'] = [
            'original_type' => 'ai_assistant',
            'migrated_at' => current_time('mysql'),
            'original_config' => [
                'model' => $step['model'] ?? null,
                'temperature' => $step['temperature'] ?? null,
                'max_tokens' => $step['max_tokens'] ?? null,
                'expected_format' => $step['expected_format'] ?? null
            ]
        ];

        return $converted_step;
    }

    /**
     * Find existing assistant with same name and prompt
     * 
     * @param string $name Assistant name
     * @param string $prompt_template Prompt template
     * @return array|null Existing assistant or null
     */
    private static function find_existing_assistant($name, $prompt_template)
    {
        $all_assistants = PolyTrans_Assistant_Manager::get_all_assistants();

        foreach ($all_assistants as $assistant) {
            // Match by name and prompt template
            if ($assistant['name'] === $name && $assistant['prompt_template'] === $prompt_template) {
                return $assistant;
            }
        }

        return null;
    }

    /**
     * Check if migration is needed
     * 
     * @return bool True if there are ai_assistant steps to migrate
     */
    public static function is_migration_needed()
    {
        try {
            $storage_manager = new PolyTrans_Workflow_Storage_Manager();
            $workflows = $storage_manager->get_all_workflows();

            foreach ($workflows as $workflow) {
                if (empty($workflow['steps'])) {
                    continue;
                }

                foreach ($workflow['steps'] as $step) {
                    if (($step['type'] ?? '') === 'ai_assistant') {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            PolyTrans_Logs_Manager::log("Failed to check migration status: {$e->getMessage()}", 'error');
        }

        return false;
    }

    /**
     * Get migration status
     * 
     * @return array Migration status with counts
     */
    public static function get_migration_status()
    {
        $status = [
            'ai_assistant_steps' => 0,
            'managed_assistant_steps' => 0,
            'predefined_assistant_steps' => 0,
            'total_workflows' => 0,
            'migration_needed' => false
        ];

        try {
            $storage_manager = new PolyTrans_Workflow_Storage_Manager();
            $workflows = $storage_manager->get_all_workflows();
            $status['total_workflows'] = count($workflows);

            foreach ($workflows as $workflow) {
                if (empty($workflow['steps'])) {
                    continue;
                }

                foreach ($workflow['steps'] as $step) {
                    $type = $step['type'] ?? '';
                    
                    switch ($type) {
                        case 'ai_assistant':
                            $status['ai_assistant_steps']++;
                            $status['migration_needed'] = true;
                            break;
                        case 'managed_assistant':
                            $status['managed_assistant_steps']++;
                            break;
                        case 'predefined_assistant':
                            $status['predefined_assistant_steps']++;
                            break;
                    }
                }
            }
        } catch (Exception $e) {
            PolyTrans_Logs_Manager::log("Failed to get migration status: {$e->getMessage()}", 'error');
        }

        return $status;
    }
}

