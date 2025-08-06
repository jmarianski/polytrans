<?php

/**
 * Workflow Output Processor
 * 
 * Handles processing workflow step outputs and applying them to posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Workflow_Output_Processor
{
    private static $instance = null;

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
        // Private constructor for singleton
    }

    /**
     * Process step outputs according to configured actions
     * Returns change objects that describe what would happen
     */
    public function process_step_outputs($step_results, $output_actions, $context, $test_mode = false, $workflow = null)
    {
        if (empty($output_actions) || !is_array($output_actions)) {
            return [
                'success' => true,
                'message' => 'No output actions configured',
                'actions_processed' => 0,
                'changes' => [],
                'updated_context' => $context
            ];
        }

        $processed_actions = 0;
        $errors = [];
        $changes = [];
        $updated_context = $context;

        // Ensure context has current post data for accurate "before" values
        $updated_context = $this->ensure_context_has_post_data($updated_context);

        // Handle user attribution if specified in workflow
        $original_user_id = get_current_user_id();
        $attribution_user_id = null;

        if (!$test_mode && $workflow && isset($workflow['attribution_user']) && !empty($workflow['attribution_user'])) {
            $attribution_user_id = intval($workflow['attribution_user']);
            if ($attribution_user_id > 0) {
                $attribution_user = get_user_by('id', $attribution_user_id);
                if ($attribution_user) {
                    wp_set_current_user($attribution_user_id);
                    PolyTrans_Logs_Manager::log("Setting current user to {$attribution_user_id} ({$attribution_user->display_name}) for workflow changes", 'info', [
                        'source' => 'workflow_output_processor',
                        'original_user' => $original_user_id,
                        'attribution_user' => $attribution_user_id,
                        'attribution_user_name' => $attribution_user->display_name,
                        'workflow_name' => $workflow['name'] ?? 'Unknown'
                    ]);
                } else {
                    PolyTrans_Logs_Manager::log("Attribution user {$attribution_user_id} not found, using original user {$original_user_id}", 'warning', [
                        'source' => 'workflow_output_processor',
                        'original_user' => $original_user_id,
                        'invalid_attribution_user' => $attribution_user_id,
                        'workflow_name' => $workflow['name'] ?? 'Unknown'
                    ]);
                    $attribution_user_id = null; // Reset to prevent restoration attempt
                }
            }
        }

        foreach ($output_actions as $action) {
            try {
                $change_result = $this->create_change_object($step_results, $action, $updated_context);
                if ($change_result['success']) {
                    $changes[] = $change_result['change'];

                    // Update context with the new value for subsequent actions
                    if ($test_mode) {
                        $updated_context = $this->apply_change_to_context($updated_context, $change_result['change']);
                    } else {
                        // Execute the change in production mode
                        $execute_result = $this->execute_change($change_result['change'], $context);
                        if (!$execute_result['success']) {
                            $errors[] = $execute_result['error'];
                        }
                    }

                    $processed_actions++;
                } else {
                    $errors[] = $change_result['error'];
                }
            } catch (Exception $e) {
                $errors[] = 'Action processing failed: ' . $e->getMessage();
            }
        }

        // Restore original user if we changed it
        if (!$test_mode && $attribution_user_id && $attribution_user_id !== $original_user_id) {
            wp_set_current_user($original_user_id);
            PolyTrans_Logs_Manager::log("Restored current user to {$original_user_id} after workflow changes", 'info', [
                'source' => 'workflow_output_processor',
                'original_user' => $original_user_id,
                'attribution_user' => $attribution_user_id
            ]);
        }

        // In production mode, refresh context from database to get actual current values
        // In test mode, the context is already updated through apply_change_to_context
        if (!$test_mode && !empty($changes)) {
            $updated_context = $this->refresh_context_from_database($updated_context);
        }

        return [
            'success' => empty($errors),
            'processed_actions' => $processed_actions,
            'errors' => $errors,
            'changes' => $changes,
            'updated_context' => $updated_context,
            'message' => empty($errors)
                ? sprintf('Successfully processed %d output actions', $processed_actions)
                : sprintf('Processed %d actions with %d errors', $processed_actions, count($errors))
        ];
    }

    /**
     * Process a single output action
     */
    private function process_single_action($step_results, $action, $context)
    {
        $action_type = $action['type'] ?? '';
        $source_variable = $action['source_variable'] ?? '';
        $target = $action['target'] ?? '';

        if (empty($action_type)) {
            return [
                'success' => false,
                'error' => 'Action type is required'
            ];
        }

        // Get the value from step results
        // If source_variable is empty, auto-detect the main response
        $value = $this->get_variable_value($step_results, $source_variable);
        if ($value === null) {
            // Get available variables for debugging
            $available_vars = [];
            if (isset($step_results['data']) && is_array($step_results['data'])) {
                $available_vars = array_keys($step_results['data']);
            }

            $error_msg = empty($source_variable)
                ? 'No response data available from step'
                : sprintf('Source variable "%s" not found in step results', $source_variable);

            return [
                'success' => false,
                'error' => $error_msg . sprintf(
                    '. Available variables: %s',
                    empty($available_vars) ? 'none' : implode(', ', $available_vars)
                ),
                'available_variables' => $available_vars,
                'step_data' => $step_results['data'] ?? null
            ];
        }

        // Convert value to string if needed
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        } else {
            $value = (string) $value;
        }

        // Process based on action type
        switch ($action_type) {
            case 'update_post_title':
                return $this->update_post_title($value, $context);

            case 'update_post_content':
                return $this->update_post_content($value, $context);

            case 'update_post_excerpt':
                return $this->update_post_excerpt($value, $context);

            case 'update_post_meta':
                return $this->update_post_meta($value, $target, $context);

            case 'append_to_post_content':
                return $this->append_to_post_content($value, $context);

            case 'prepend_to_post_content':
                return $this->prepend_to_post_content($value, $context);

            case 'save_to_option':
                return $this->save_to_option($value, $target, $context);

            case 'update_post_status':
                return $this->update_post_status($value, $context);

            case 'update_post_date':
                return $this->update_post_date($value, $context);

            default:
                return [
                    'success' => false,
                    'error' => sprintf('Unknown action type: %s', $action_type)
                ];
        }
    }

    /**
     * Get variable value from step results using dot notation
     * If variable_path is empty, automatically detects the best response variable
     */
    private function get_variable_value($step_results, $variable_path)
    {
        $data = $step_results['data'] ?? [];

        // If no variable path specified, auto-detect the main response
        if (empty($variable_path)) {
            return $this->auto_detect_response_value($data);
        }

        $path_parts = explode('.', $variable_path);

        $current = $data;
        foreach ($path_parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Auto-detect the main response value from step results
     * For plain text responses, returns ai_response
     * For JSON responses, tries to find the most relevant content
     */
    private function auto_detect_response_value($data)
    {
        if (!is_array($data) || empty($data)) {
            return null;
        }

        // Priority order for auto-detection:
        // 1. If there's an 'ai_response' (plain text format), use that
        // 2. If there's a 'processed_content' (predefined assistant plain text), use that
        // 3. If there's a 'content' variable, use that  
        // 4. If there's an 'assistant_response', use that
        // 5. If there's only one variable, use that
        // 6. Otherwise, return the first available value

        if (isset($data['ai_response'])) {
            return $data['ai_response'];
        }

        if (isset($data['processed_content'])) {
            return $data['processed_content'];
        }

        if (isset($data['content'])) {
            return $data['content'];
        }

        if (isset($data['assistant_response'])) {
            return $data['assistant_response'];
        }

        // If only one variable, use it
        if (count($data) === 1) {
            return reset($data);
        }

        // Return first available value as fallback
        return reset($data);
    }

    /**
     * Update post title
     */
    private function update_post_title($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_title' => sanitize_text_field($value)
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to update post title: ' . $result->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Updated post title for post ID %d', $post_id)
        ];
    }

    /**
     * Update post content
     */
    private function update_post_content($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => wp_kses_post($value)
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to update post content: ' . $result->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Updated post content for post ID %d', $post_id)
        ];
    }

    /**
     * Update post excerpt
     */
    private function update_post_excerpt($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_excerpt' => sanitize_textarea_field($value)
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to update post excerpt: ' . $result->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Updated post excerpt for post ID %d', $post_id)
        ];
    }

    /**
     * Update post meta
     */
    private function update_post_meta($value, $meta_key, $context)
    {
        if (empty($meta_key)) {
            return [
                'success' => false,
                'error' => 'Meta key is required for update_post_meta action'
            ];
        }

        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        $meta_key = sanitize_key($meta_key);
        $result = update_post_meta($post_id, $meta_key, $value);

        return [
            'success' => true,
            'message' => sprintf('Updated post meta "%s" for post ID %d', $meta_key, $post_id)
        ];
    }

    /**
     * Append to post content
     */
    private function append_to_post_content($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        $post = get_post($post_id);
        if (!$post) {
            return [
                'success' => false,
                'error' => sprintf('Post with ID %d not found', $post_id)
            ];
        }

        $new_content = $post->post_content . "\n\n" . wp_kses_post($value);

        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to append to post content: ' . $result->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Appended content to post ID %d', $post_id)
        ];
    }

    /**
     * Prepend to post content
     */
    private function prepend_to_post_content($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        $post = get_post($post_id);
        if (!$post) {
            return [
                'success' => false,
                'error' => sprintf('Post with ID %d not found', $post_id)
            ];
        }

        $new_content = wp_kses_post($value) . "\n\n" . $post->post_content;

        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to prepend to post content: ' . $result->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Prepended content to post ID %d', $post_id)
        ];
    }

    /**
     * Save to WordPress option
     */
    private function save_to_option($value, $option_name, $context)
    {
        if (empty($option_name)) {
            return [
                'success' => false,
                'error' => 'Option name is required for save_to_option action'
            ];
        }

        $option_name = sanitize_key($option_name);
        $result = update_option($option_name, $value);

        return [
            'success' => true,
            'message' => sprintf('Saved value to option "%s"', $option_name)
        ];
    }

    /**
     * Update post status
     */
    private function update_post_status($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        // Clean and validate the status value
        $status = $this->parse_post_status($value);
        if (!$status) {
            return [
                'success' => false,
                'error' => sprintf('Invalid post status: "%s". Valid statuses are: %s', 
                    $value, 
                    implode(', ', $this->get_valid_post_statuses())
                )
            ];
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => $status
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to update post status: ' . $result->get_error_message()
            ];
        }

        PolyTrans_Logs_Manager::log("Updated post status to '{$status}' for post ID {$post_id}", 'info', [
            'source' => 'workflow_output_processor',
            'post_id' => $post_id,
            'old_status' => get_post_field('post_status', $post_id),
            'new_status' => $status,
            'raw_ai_value' => $value
        ]);

        return [
            'success' => true,
            'message' => sprintf('Updated post status to "%s" for post ID %d', $status, $post_id)
        ];
    }

    /**
     * Update post date (for scheduling)
     */
    private function update_post_date($value, $context)
    {
        $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
        if (!$post_id) {
            return [
                'success' => false,
                'error' => 'No post ID found in context'
            ];
        }

        // Parse and validate the date
        $parsed_date = $this->parse_post_date($value);
        if (!$parsed_date) {
            return [
                'success' => false,
                'error' => sprintf('Invalid date format: "%s". Please provide a valid date/time.', $value)
            ];
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'post_date' => $parsed_date,
            'post_date_gmt' => get_gmt_from_date($parsed_date)
        ]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => 'Failed to update post date: ' . $result->get_error_message()
            ];
        }

        PolyTrans_Logs_Manager::log("Updated post date to '{$parsed_date}' for post ID {$post_id}", 'info', [
            'source' => 'workflow_output_processor',
            'post_id' => $post_id,
            'new_date' => $parsed_date,
            'new_date_gmt' => get_gmt_from_date($parsed_date),
            'raw_ai_value' => $value
        ]);

        return [
            'success' => true,
            'message' => sprintf('Updated post date to "%s" for post ID %d', $parsed_date, $post_id)
        ];
    }

    /**
     * Parse post status from AI response
     * Handles various formats and common mistakes
     */
    private function parse_post_status($value)
    {
        if (empty($value)) {
            return null;
        }

        // Clean the value - remove quotes, trim, lowercase
        $status = strtolower(trim($value, ' "\''));

        $valid_statuses = $this->get_valid_post_statuses();

        // Exact match check
        if (in_array($status, $valid_statuses)) {
            return $status;
        }

        // Common variations and mistakes
        $common_mistakes = [
            'publish' => ['published', 'public', 'live'],
            'draft' => ['drafted', 'drafty'],
            'pending' => ['pending review', 'waiting for review'],
            'private' => ['privat', 'private post'],
            'future' => ['scheduled', 'schedule'],
            'trash' => ['deleted', 'move to trash']
        ];

        foreach ($common_mistakes as $correct_status => $variations) {
            if (in_array($status, $variations)) {
                return $correct_status;
            }
        }

        return null;
    }

    /**
     * Parse post date from AI response
     * Tries to extract a valid date/time string from various possible formats
     */
    private function parse_post_date($value)
    {
        if (empty($value)) {
            return null;
        }

        // Try parsing with DateTime first
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if ($date) {
            return $date->format('Y-m-d H:i:s');
        }

        // Try parsing with custom formats
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y',
            'm/d/Y',
            'Y.m.d',
            'd.m.Y',
            'm.d.Y',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d\TH',
            'Y-m-d\T',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:s.u',
            'Y-m-d\TH:i.u',
            'Y-m-d\TH.u'
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        // Try strtotime as fallback
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        return null;
    }

    /**
     * Create a change object for an action
     */
    private function create_change_object($step_results, $action, $context)
    {
        try {
            $variable_path = $action['source_variable'] ?? '';
            $value = $this->get_variable_value($step_results, $variable_path);
            
            if ($value === null && !empty($variable_path)) {
                $value = $this->auto_detect_response_value($step_results['data'] ?? []);
            }

            // Create base change object
            $change = [
                'action' => $action['type'],
                'value' => $value,
                'context' => $context,
                'original_action' => $action
            ];

            // Add display-friendly fields for frontend
            $change = $this->enhance_change_object_for_display($change, $context);

            // Add specific parameters for certain actions
            if ($action['type'] === 'update_post_meta' && isset($action['target'])) {
                $change['meta_key'] = $action['target'];
            }
            
            if ($action['type'] === 'save_to_option' && isset($action['target'])) {
                $change['option_name'] = $action['target'];
            }

            return [
                'success' => true,
                'change' => $change
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create change object: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enhance change object with display-friendly fields for the frontend
     */
    private function enhance_change_object_for_display($change, $context)
    {
        $action_type = $change['action'];
        $new_value = $change['value'];
        
        // Get current value and target description based on action type
        $current_value = '';
        $target_description = '';
        
        switch ($action_type) {
            case 'update_post_title':
                $current_value = $context['post_title'] ?? '';
                $target_description = 'Post Title';
                break;
                
            case 'update_post_content':
                $current_value = $context['post_content'] ?? '';
                $target_description = 'Post Content';
                break;
                
            case 'update_post_excerpt':
                $current_value = $context['post_excerpt'] ?? '';
                $target_description = 'Post Excerpt';
                break;
                
            case 'update_post_status':
                $current_value = $context['post_status'] ?? '';
                $target_description = 'Post Status';
                // Parse the new value to ensure it's clean
                $new_value = $this->parse_post_status($new_value) ?? $new_value;
                break;
                
            case 'update_post_date':
                $current_value = $context['post_date'] ?? '';
                $target_description = 'Post Date';
                // Parse the new value to ensure it's clean
                $new_value = $this->parse_post_date($new_value) ?? $new_value;
                break;
                
            case 'update_post_meta':
                $meta_key = $change['original_action']['target'] ?? 'unknown';
                $current_value = $context['meta'][$meta_key] ?? '';
                $target_description = "Post Meta: {$meta_key}";
                break;
                
            case 'save_to_option':
                $option_name = $change['original_action']['target'] ?? 'unknown';
                $current_value = get_option($option_name, '');
                $target_description = "WordPress Option: {$option_name}";
                break;
                
            case 'append_to_post_content':
                $current_value = $context['post_content'] ?? '';
                $new_value = $current_value . "\n\n" . $new_value;
                $target_description = 'Post Content (Append)';
                break;
                
            case 'prepend_to_post_content':
                $current_value = $context['post_content'] ?? '';
                $new_value = $new_value . "\n\n" . $current_value;
                $target_description = 'Post Content (Prepend)';
                break;
                
            default:
                $target_description = ucwords(str_replace('_', ' ', $action_type));
                break;
        }
        
        // Add display fields that match JavaScript expectations
        $change['action_type'] = $action_type;
        $change['target_description'] = $target_description;
        $change['current_value'] = $current_value;
        $change['new_value'] = $new_value;
        
        return $change;
    }

    /**
     * Apply a change to the context (for test mode)
     */
    private function apply_change_to_context($context, $change)
    {
        $updated_context = $context;
        
        switch ($change['action']) {
            case 'update_post_title':
                $updated_context['post_title'] = $change['value'];
                break;
            case 'update_post_content':
                $updated_context['post_content'] = $change['value'];
                break;
            case 'update_post_excerpt':
                $updated_context['post_excerpt'] = $change['value'];
                break;
            case 'update_post_status':
                $updated_context['post_status'] = $change['value'];
                break;
            case 'update_post_date':
                $updated_context['post_date'] = $change['value'];
                $updated_context['post_date_gmt'] = get_gmt_from_date($change['value']);
                break;
            case 'update_post_meta':
                if (!isset($updated_context['meta'])) {
                    $updated_context['meta'] = [];
                }
                $updated_context['meta'][$change['meta_key']] = $change['value'];
                break;
            case 'append_to_post_content':
                $updated_context['post_content'] = ($updated_context['post_content'] ?? '') . $change['value'];
                break;
            case 'prepend_to_post_content':
                $updated_context['post_content'] = $change['value'] . ($updated_context['post_content'] ?? '');
                break;
        }
        
        return $updated_context;
    }

    /**
     * Execute a change in production mode
     */
    private function execute_change($change, $context)
    {
        try {
            $action = $change['action'];
            $value = $change['value'];
            
            switch ($action) {
                case 'update_post_title':
                    return $this->update_post_title($value, $context);
                case 'update_post_content':
                    return $this->update_post_content($value, $context);
                case 'update_post_excerpt':
                    return $this->update_post_excerpt($value, $context);
                case 'update_post_status':
                    return $this->update_post_status($value, $context);
                case 'update_post_date':
                    return $this->update_post_date($value, $context);
                case 'update_post_meta':
                    return $this->update_post_meta($value, $change['meta_key'], $context);
                case 'append_to_post_content':
                    return $this->append_to_post_content($value, $context);
                case 'prepend_to_post_content':
                    return $this->prepend_to_post_content($value, $context);
                case 'save_to_option':
                    return $this->save_to_option($value, $change['option_name'], $context);
                default:
                    return [
                        'success' => false,
                        'error' => "Unknown action: {$action}"
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to execute change: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Refresh context from database to get current values
     */
    private function refresh_context_from_database($context)
    {
        if (!isset($context['post_id'])) {
            return $context;
        }

        $post = get_post($context['post_id']);
        if (!$post) {
            return $context;
        }

        $updated_context = $context;
        $updated_context['post_title'] = $post->post_title;
        $updated_context['post_content'] = $post->post_content;
        $updated_context['post_excerpt'] = $post->post_excerpt;
        $updated_context['post_status'] = $post->post_status;
        $updated_context['post_date'] = $post->post_date;
        $updated_context['post_date_gmt'] = $post->post_date_gmt;

        return $updated_context;
    }

    /**
     * Ensure context has current post data for accurate "before" values
     */
    private function ensure_context_has_post_data($context)
    {
        // Find the post ID from various possible fields
        $post_id = $context['translated_post_id'] ?? 
                   $context['original_post_id'] ?? 
                   $context['post_id'] ?? 
                   null;
        
        if (!$post_id) {
            return $context;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return $context;
        }
        
        // Populate context with current post data if not already present
        $context['post_title'] = $context['post_title'] ?? $post->post_title;
        $context['post_content'] = $context['post_content'] ?? $post->post_content;
        $context['post_excerpt'] = $context['post_excerpt'] ?? $post->post_excerpt;
        $context['post_status'] = $context['post_status'] ?? $post->post_status;
        $context['post_date'] = $context['post_date'] ?? $post->post_date;
        $context['post_date_gmt'] = $context['post_date_gmt'] ?? $post->post_date_gmt;
        
        // Ensure post_id is consistently available
        $context['post_id'] = $post_id;
        
        return $context;
    }

    /**
     * Get valid post statuses for validation
     */
    private function get_valid_post_statuses()
    {
        return ['publish', 'draft', 'pending', 'private', 'trash', 'future'];
    }
}
