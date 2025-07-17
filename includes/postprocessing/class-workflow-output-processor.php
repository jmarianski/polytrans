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
    public function process_step_outputs($step_results, $output_actions, $context, $test_mode = false)
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

        if (empty($action_type) || empty($source_variable)) {
            return [
                'success' => false,
                'error' => 'Action type and source variable are required'
            ];
        }

        // Get the value from step results
        $value = $this->get_variable_value($step_results, $source_variable);
        if ($value === null) {
            // Get available variables for debugging
            $available_vars = [];
            if (isset($step_results['data']) && is_array($step_results['data'])) {
                $available_vars = array_keys($step_results['data']);
            }
            
            return [
                'success' => false,
                'error' => sprintf('Source variable "%s" not found in step results. Available variables: %s', 
                    $source_variable, 
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

            default:
                return [
                    'success' => false,
                    'error' => sprintf('Unknown action type: %s', $action_type)
                ];
        }
    }

    /**
     * Get variable value from step results using dot notation
     */
    private function get_variable_value($step_results, $variable_path)
    {
        $data = $step_results['data'] ?? [];
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
     * Get available output action types
     */
    public function get_available_action_types()
    {
        return [
            'update_post_title' => [
                'label' => __('Update Post Title', 'polytrans'),
                'description' => __('Replace the post title with the output value', 'polytrans'),
                'requires_target' => false
            ],
            'update_post_content' => [
                'label' => __('Update Post Content', 'polytrans'),
                'description' => __('Replace the post content with the output value', 'polytrans'),
                'requires_target' => false
            ],
            'update_post_excerpt' => [
                'label' => __('Update Post Excerpt', 'polytrans'),
                'description' => __('Replace the post excerpt with the output value', 'polytrans'),
                'requires_target' => false
            ],
            'update_post_meta' => [
                'label' => __('Update Post Meta', 'polytrans'),
                'description' => __('Save the output value to a post meta field', 'polytrans'),
                'requires_target' => true,
                'target_label' => __('Meta Key', 'polytrans')
            ],
            'append_to_post_content' => [
                'label' => __('Append to Post Content', 'polytrans'),
                'description' => __('Add the output value to the end of the post content', 'polytrans'),
                'requires_target' => false
            ],
            'prepend_to_post_content' => [
                'label' => __('Prepend to Post Content', 'polytrans'),
                'description' => __('Add the output value to the beginning of the post content', 'polytrans'),
                'requires_target' => false
            ],
            'save_to_option' => [
                'label' => __('Save to WordPress Option', 'polytrans'),
                'description' => __('Save the output value to a WordPress option', 'polytrans'),
                'requires_target' => true,
                'target_label' => __('Option Name', 'polytrans')
            ]
        ];
    }

    /**
     * Simulate step outputs for testing - shows what would happen without actually executing
     */
    public function simulate_step_outputs($step_results, $output_actions, $context)
    {
        if (empty($output_actions)) {
            return [
                'success' => true,
                'actions_simulated' => 0,
                'simulations' => [],
                'message' => 'No output actions to simulate'
            ];
        }

        $simulations = [];
        $actions_simulated = 0;

        foreach ($output_actions as $action) {
            try {
                $simulation = $this->simulate_single_action($step_results, $action, $context);
                $simulations[] = $simulation;
                if ($simulation['would_succeed']) {
                    $actions_simulated++;
                }
            } catch (Exception $e) {
                $simulations[] = [
                    'would_succeed' => false,
                    'action_type' => $action['type'] ?? 'unknown',
                    'description' => 'Simulation failed: ' . $e->getMessage(),
                    'details' => null
                ];
            }
        }

        return [
            'success' => true,
            'actions_simulated' => $actions_simulated,
            'simulations' => $simulations,
            'message' => sprintf('Simulated %d output actions', count($simulations))
        ];
    }

    /**
     * Simulate a single output action
     */
    private function simulate_single_action($step_results, $action, $context)
    {
        $action_type = $action['type'] ?? '';
        $source_variable = $action['source_variable'] ?? '';
        $target = $action['target'] ?? '';

        if (empty($action_type) || empty($source_variable)) {
            return [
                'would_succeed' => false,
                'action_type' => $action_type,
                'description' => 'Action missing required fields (type and source_variable)',
                'details' => null
            ];
        }

        // Get the value from step results
        $value = $this->get_variable_value($step_results, $source_variable);
        if ($value === null) {
            return [
                'would_succeed' => false,
                'action_type' => $action_type,
                'description' => sprintf('Source variable "%s" not found in step results', $source_variable),
                'details' => null
            ];
        }

        // Convert value to string for display
        $display_value = is_string($value) ? $value : json_encode($value);
        $preview_value = strlen($display_value) > 100 ? substr($display_value, 0, 100) . '...' : $display_value;

        // Simulate based on action type
        switch ($action_type) {
            case 'save_to_post_title':
                return [
                    'would_succeed' => true,
                    'action_type' => $action_type,
                    'description' => 'Would update post title',
                    'details' => [
                        'target' => 'Post Title',
                        'new_value' => $preview_value,
                        'source_variable' => $source_variable
                    ]
                ];

            case 'save_to_post_content':
                return [
                    'would_succeed' => true,
                    'action_type' => $action_type,
                    'description' => 'Would update post content',
                    'details' => [
                        'target' => 'Post Content',
                        'new_value' => $preview_value,
                        'source_variable' => $source_variable
                    ]
                ];

            case 'save_to_post_excerpt':
                return [
                    'would_succeed' => true,
                    'action_type' => $action_type,
                    'description' => 'Would update post excerpt',
                    'details' => [
                        'target' => 'Post Excerpt',
                        'new_value' => $preview_value,
                        'source_variable' => $source_variable
                    ]
                ];

            case 'save_to_meta':
                $meta_key = $target;
                if (empty($meta_key)) {
                    return [
                        'would_succeed' => false,
                        'action_type' => $action_type,
                        'description' => 'Meta key (target) is required for save_to_meta action',
                        'details' => null
                    ];
                }

                return [
                    'would_succeed' => true,
                    'action_type' => $action_type,
                    'description' => sprintf('Would update meta field "%s"', $meta_key),
                    'details' => [
                        'target' => 'Meta: ' . $meta_key,
                        'new_value' => $preview_value,
                        'source_variable' => $source_variable
                    ]
                ];

            case 'append_to_post_content':
                return [
                    'would_succeed' => true,
                    'action_type' => $action_type,
                    'description' => 'Would append to post content',
                    'details' => [
                        'target' => 'Post Content (append)',
                        'append_value' => $preview_value,
                        'source_variable' => $source_variable
                    ]
                ];

            case 'save_to_custom_field':
                $field_name = $target;
                if (empty($field_name)) {
                    return [
                        'would_succeed' => false,
                        'action_type' => $action_type,
                        'description' => 'Custom field name (target) is required',
                        'details' => null
                    ];
                }

                return [
                    'would_succeed' => true,
                    'action_type' => $action_type,
                    'description' => sprintf('Would update custom field "%s"', $field_name),
                    'details' => [
                        'target' => 'Custom Field: ' . $field_name,
                        'new_value' => $preview_value,
                        'source_variable' => $source_variable
                    ]
                ];

            default:
                return [
                    'would_succeed' => false,
                    'action_type' => $action_type,
                    'description' => sprintf('Unknown action type "%s"', $action_type),
                    'details' => null
                ];
        }
    }

    /**
     * Create a change object that describes what would happen
     */
    private function create_change_object($step_results, $action, $context)
    {
        $action_type = $action['type'] ?? '';
        $source_variable = $action['source_variable'] ?? '';
        $target = $action['target'] ?? '';

        if (empty($action_type) || empty($source_variable)) {
            return [
                'success' => false,
                'error' => 'Action type and source variable are required'
            ];
        }

        // Get the value from step results
        $value = $this->get_variable_value($step_results, $source_variable);
        if ($value === null) {
            // Get available variables for debugging
            $available_vars = [];
            if (isset($step_results['data']) && is_array($step_results['data'])) {
                $available_vars = array_keys($step_results['data']);
            }
            
            return [
                'success' => false,
                'error' => sprintf('Source variable "%s" not found in step results. Available variables: %s', 
                    $source_variable, 
                    empty($available_vars) ? 'none' : implode(', ', $available_vars)
                ),
                'available_variables' => $available_vars
            ];
        }

        // Convert value to string if needed
        if (is_array($value) || is_object($value)) {
            $formatted_value = json_encode($value);
        } else {
            $formatted_value = (string) $value;
        }

        // Get current state for comparison
        $current_state = $this->get_current_state($action_type, $target, $context);

        // Create change object
        $change = [
            'action_type' => $action_type,
            'source_variable' => $source_variable,
            'target' => $target,
            'new_value' => $formatted_value,
            'raw_value' => $value,
            'current_value' => $current_state['current_value'],
            'target_description' => $current_state['target_description'],
            'change_description' => $this->get_change_description($action_type, $target, $current_state['current_value'], $formatted_value)
        ];

        return [
            'success' => true,
            'change' => $change
        ];
    }

    /**
     * Get current state of the target
     */
    private function get_current_state($action_type, $target, $context)
    {
        switch ($action_type) {
            case 'update_post_title':
                $post_id = $context['translated_post_id'] ?? null;
                if ($post_id && get_post($post_id)) {
                    $current_value = get_post_field('post_title', $post_id);
                } else {
                    // Test mode or post doesn't exist
                    $current_value = $context['translated_post']['title'] ?? $context['title'] ?? '';
                }
                return [
                    'current_value' => $current_value,
                    'target_description' => $post_id ? "Post #{$post_id} title" : "Post title (test mode)"
                ];

            case 'update_post_content':
                $post_id = $context['translated_post_id'] ?? null;
                if ($post_id && get_post($post_id)) {
                    $current_value = get_post_field('post_content', $post_id);
                } else {
                    // Test mode or post doesn't exist
                    $current_value = $context['translated_post']['content'] ?? $context['content'] ?? '';
                }
                return [
                    'current_value' => $current_value,
                    'target_description' => $post_id ? "Post #{$post_id} content" : "Post content (test mode)"
                ];

            case 'update_post_excerpt':
                $post_id = $context['translated_post_id'] ?? null;
                if ($post_id && get_post($post_id)) {
                    $current_value = get_post_field('post_excerpt', $post_id);
                } else {
                    // Test mode or post doesn't exist
                    $current_value = $context['translated_post']['excerpt'] ?? $context['excerpt'] ?? '';
                }
                return [
                    'current_value' => $current_value,
                    'target_description' => $post_id ? "Post #{$post_id} excerpt" : "Post excerpt (test mode)"
                ];

            case 'update_post_meta':
                $post_id = $context['translated_post_id'] ?? null;
                if ($post_id && get_post($post_id)) {
                    $current_value = get_post_meta($post_id, $target, true);
                } else {
                    // Test mode or post doesn't exist
                    $current_value = $context['translated_post']['meta'][$target] ?? '';
                }
                return [
                    'current_value' => $current_value,
                    'target_description' => $post_id ? "Post #{$post_id} meta field '{$target}'" : "Post meta field '{$target}' (test mode)"
                ];

            case 'save_to_option':
                $current_value = get_option($target, '');
                return [
                    'current_value' => $current_value,
                    'target_description' => "WordPress option '{$target}'"
                ];

            default:
                return [
                    'current_value' => '',
                    'target_description' => "Unknown target ({$action_type})"
                ];
        }
    }

    /**
     * Generate a human-readable description of the change
     */
    private function get_change_description($action_type, $target, $current_value, $new_value)
    {
        $current_preview = strlen($current_value) > 100 ? substr($current_value, 0, 100) . '...' : $current_value;
        $new_preview = strlen($new_value) > 100 ? substr($new_value, 0, 100) . '...' : $new_value;

        switch ($action_type) {
            case 'update_post_title':
                return "Change title from '{$current_preview}' to '{$new_preview}'";

            case 'update_post_content':
                return "Replace content (current: " . strlen($current_value) . " chars) with new content (" . strlen($new_value) . " chars)";

            case 'update_post_excerpt':
                return "Change excerpt from '{$current_preview}' to '{$new_preview}'";

            case 'update_post_meta':
                return "Set meta field '{$target}' from '{$current_preview}' to '{$new_preview}'";

            case 'append_to_post_content':
                return "Append to content: '{$new_preview}'";

            case 'prepend_to_post_content':
                return "Prepend to content: '{$new_preview}'";

            case 'save_to_option':
                return "Set option '{$target}' from '{$current_preview}' to '{$new_preview}'";

            default:
                return "Unknown action: {$action_type}";
        }
    }

    /**
     * Apply change to context (for test mode)
     */
    private function apply_change_to_context($context, $change)
    {
        // For test mode, we update the context variables to reflect the changes
        switch ($change['action_type']) {
            case 'update_post_title':
                if (isset($context['translated_post'])) {
                    $context['translated_post']['title'] = $change['new_value'];
                }
                // Also update convenience alias
                $context['title'] = $change['new_value'];
                break;

            case 'update_post_content':
                if (isset($context['translated_post'])) {
                    $context['translated_post']['content'] = $change['new_value'];
                }
                // Also update convenience alias
                $context['content'] = $change['new_value'];
                break;

            case 'update_post_excerpt':
                if (isset($context['translated_post'])) {
                    $context['translated_post']['excerpt'] = $change['new_value'];
                }
                // Also update convenience alias
                $context['excerpt'] = $change['new_value'];
                break;

            case 'update_post_meta':
                if (isset($context['translated_post'])) {
                    if (!isset($context['translated_post']['meta'])) {
                        $context['translated_post']['meta'] = [];
                    }
                    $context['translated_post']['meta'][$change['target']] = $change['new_value'];
                }
                break;
        }

        return $context;
    }

    /**
     * Execute a change (for production mode)
     */
    private function execute_change($change, $context)
    {
        switch ($change['action_type']) {
            case 'update_post_title':
                return $this->update_post_title($change['new_value'], $context);

            case 'update_post_content':
                return $this->update_post_content($change['new_value'], $context);

            case 'update_post_excerpt':
                return $this->update_post_excerpt($change['new_value'], $context);

            case 'update_post_meta':
                return $this->update_post_meta($change['new_value'], $change['target'], $context);

            case 'append_to_post_content':
                return $this->append_to_post_content($change['new_value'], $context);

            case 'prepend_to_post_content':
                return $this->prepend_to_post_content($change['new_value'], $context);

            case 'save_to_option':
                return $this->save_to_option($change['new_value'], $change['target'], $context);

            default:
                return [
                    'success' => false,
                    'error' => sprintf('Unknown action type: %s', $change['action_type'])
                ];
        }
    }
}
