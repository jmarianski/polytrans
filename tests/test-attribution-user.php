<?php

/**
 * Test script for workflow attribution user functionality
 * 
 * This script tests the user attribution feature in workflow execution.
 */

require_once(dirname(__FILE__) . '/../../../wp-config.php');

// Test workflow with attribution user
function test_workflow_attribution_user()
{
    // Get current user for comparison
    $current_user_id = get_current_user_id();
    $current_user = get_user_by('id', $current_user_id);

    // Get first admin user (if different from current)
    $admin_users = get_users(['role' => 'administrator', 'number' => 2]);
    $attribution_user = null;
    foreach ($admin_users as $user) {
        if ($user->ID !== $current_user_id) {
            $attribution_user = $user;
            break;
        }
    }

    // If no different admin found, use current user for demonstration
    if (!$attribution_user) {
        $attribution_user = $current_user;
    }

    echo "<h2>Testing Workflow Attribution User Functionality</h2>\n";
    echo "<p><strong>Current User:</strong> {$current_user->display_name} (ID: {$current_user_id})</p>\n";
    echo "<p><strong>Attribution User:</strong> {$attribution_user->display_name} (ID: {$attribution_user->ID})</p>\n";

    // Sample workflow configuration with attribution user
    $workflow = [
        'id' => 'test_attribution_workflow',
        'name' => 'Test Attribution Workflow',
        'description' => 'Test workflow to validate user attribution',
        'enabled' => true,
        'attribution_user' => (string)$attribution_user->ID, // Convert to string as it comes from form
        'attribution_user_label' => $attribution_user->display_name . ' (' . $attribution_user->user_email . ')',
        'trigger_events' => ['translation_completed'],
        'target_languages' => ['en', 'es'],
        'steps' => [
            [
                'id' => 'test_step',
                'name' => 'Test Attribution Step',
                'type' => 'ai_assistant',
                'enabled' => true,
                'system_prompt' => 'You are a helpful assistant. Always respond with valid JSON.',
                'user_message' => 'Process this title: {title}. Respond with JSON containing "processed_title" field.',
                'expected_format' => 'json',
                'output_variables' => ['processed_title'],
                'max_tokens' => 50,
                'temperature' => 0.1,
                'output_actions' => [
                    [
                        'type' => 'update_post_title',
                        'source_variable' => 'processed_title',
                        'target' => ''
                    ]
                ]
            ]
        ]
    ];

    // Sample context
    $context = [
        'original_post_id' => 1,
        'translated_post_id' => 1,
        'source_language' => 'en',
        'target_language' => 'es',
        'title' => 'Test Post Title for Attribution',
        'content' => 'Test content for attribution workflow.',
        'original_title' => 'Original Test Title',
        'original_content' => 'Original test content.',
        'post_type' => 'post',
        'author_name' => 'Test Author',
        'site_url' => get_site_url(),
        'admin_email' => get_option('admin_email')
    ];

    echo "<h3>Workflow Configuration:</h3>\n";
    echo "<pre>" . print_r($workflow, true) . "</pre>\n";

    echo "<h3>Test Context:</h3>\n";
    echo "<pre>" . print_r($context, true) . "</pre>\n";

    // Test in test mode (no actual changes)
    echo "<h3>Testing in Test Mode (No Actual Changes):</h3>\n";
    try {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $result = $workflow_manager->execute_workflow($workflow, $context, true);

        echo "<h4>Test Mode Result:</h4>\n";
        echo "<pre>" . print_r($result, true) . "</pre>\n";

        if ($result['success']) {
            echo "<p style='color: green;'>✓ Test mode execution successful</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Test mode execution failed</p>\n";
            if (isset($result['errors'])) {
                echo "<p>Errors: " . implode(', ', $result['errors']) . "</p>\n";
            }
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception in test mode: " . $e->getMessage() . "</p>\n";
    }

    // Test output processor directly
    echo "<h3>Testing Output Processor Directly:</h3>\n";
    try {
        $output_processor = PolyTrans_Workflow_Output_Processor::get_instance();

        // Mock step results
        $step_results = [
            'test_step' => [
                'success' => true,
                'output_variables' => [
                    'processed_title' => 'Processed: Test Post Title for Attribution'
                ]
            ]
        ];

        // Get output actions from workflow
        $output_actions = $workflow['steps'][0]['output_actions'];

        echo "<p><strong>Before processing - Current User:</strong> " . get_current_user_id() . "</p>\n";

        // Test with attribution user
        $output_result = $output_processor->process_step_outputs(
            $step_results,
            $output_actions,
            $context,
            true, // test mode
            $workflow
        );

        echo "<p><strong>After processing - Current User:</strong> " . get_current_user_id() . "</p>\n";

        echo "<h4>Output Processing Result:</h4>\n";
        echo "<pre>" . print_r($output_result, true) . "</pre>\n";

        if ($output_result['success']) {
            echo "<p style='color: green;'>✓ Output processing successful</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Output processing failed</p>\n";
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception in output processing: " . $e->getMessage() . "</p>\n";
    }

    // Test with invalid attribution user
    echo "<h3>Testing with Invalid Attribution User:</h3>\n";
    $invalid_workflow = $workflow;
    $invalid_workflow['attribution_user'] = '99999'; // Non-existent user ID

    try {
        $output_result = $output_processor->process_step_outputs(
            $step_results,
            $output_actions,
            $context,
            true, // test mode
            $invalid_workflow
        );

        echo "<h4>Invalid User Result:</h4>\n";
        echo "<pre>" . print_r($output_result, true) . "</pre>\n";

        if ($output_result['success']) {
            echo "<p style='color: green;'>✓ Invalid user handled gracefully</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ Invalid user caused processing failure (expected behavior)</p>\n";
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception with invalid user: " . $e->getMessage() . "</p>\n";
    }
}

// Check if logs manager is available for viewing recent logs
function show_recent_attribution_logs()
{
    echo "<h3>Recent Attribution Logs:</h3>\n";

    if (class_exists('PolyTrans_Logs_Manager')) {
        try {
            // Get recent logs related to attribution
            $logs = PolyTrans_Logs_Manager::get_logs([
                'limit' => 10,
                'source' => 'workflow_output_processor'
            ]);

            if (!empty($logs)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
                echo "<tr><th>Time</th><th>Level</th><th>Message</th><th>Context</th></tr>\n";

                foreach ($logs as $log) {
                    $context_str = !empty($log['context']) ? json_encode($log['context'], JSON_PRETTY_PRINT) : '';
                    echo "<tr>\n";
                    echo "<td>" . esc_html($log['timestamp']) . "</td>\n";
                    echo "<td>" . esc_html($log['level']) . "</td>\n";
                    echo "<td>" . esc_html($log['message']) . "</td>\n";
                    echo "<td><pre>" . esc_html($context_str) . "</pre></td>\n";
                    echo "</tr>\n";
                }

                echo "</table>\n";
            } else {
                echo "<p>No recent attribution logs found.</p>\n";
            }
        } catch (\Exception $e) {
            echo "<p style='color: red;'>Error retrieving logs: " . $e->getMessage() . "</p>\n";
        }
    } else {
        echo "<p>Logs Manager not available.</p>\n";
    }
}

// Run tests if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-attribution-user.php') {
    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('You must be logged in as an administrator to run this test.');
    }

    echo "<!DOCTYPE html><html><head><title>Attribution User Test</title></head><body>\n";
    echo "<h1>PolyTrans Workflow Attribution User Test</h1>\n";

    test_workflow_attribution_user();
    show_recent_attribution_logs();

    echo "</body></html>\n";
}
