<?php

/**
 * Test script for step context updating
 * 
 * This script tests that workflow steps receive updated context from previous steps.
 */

require_once(dirname(__FILE__) . '/../../../wp-config.php');

// Test step context updating
function test_step_context_updating()
{
    echo "<h2>Testing Step Context Updating</h2>\n";

    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        echo "<p style='color: red;'>You must be logged in with post editing capabilities to run this test.</p>\n";
        return;
    }

    // Create a test post
    $test_post_data = [
        'post_title' => 'Test Post for Context Updating - ' . date('Y-m-d H:i:s'),
        'post_content' => 'This content has an <a href="http://example.com">old link</a> that should be removed and replaced.',
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => get_current_user_id()
    ];

    $test_post_id = wp_insert_post($test_post_data);

    if (is_wp_error($test_post_id)) {
        echo "<p style='color: red;'>Failed to create test post: " . $test_post_id->get_error_message() . "</p>\n";
        return;
    }

    echo "<p><strong>Created test post:</strong> ID {$test_post_id}</p>\n";
    echo "<p><strong>Original content:</strong> " . esc_html($test_post_data['post_content']) . "</p>\n";

    // Create a workflow that simulates the issue: Step 1 removes links, Step 2 adds new links
    $workflow = [
        'id' => 'test_context_workflow',
        'name' => 'Test Context Update Workflow',
        'description' => 'Test workflow to validate context updating between steps',
        'enabled' => true,
        'steps' => [
            [
                'id' => 'remove_links_step',
                'name' => 'Remove Links Step',
                'type' => 'ai_assistant',
                'enabled' => true,
                'system_prompt' => 'You are a content processor. Remove all HTML links from the content but keep the link text.',
                'user_message' => 'Remove all HTML links from this content: {content}. Return only the content without any <a> tags.',
                'expected_format' => 'text',
                'output_variables' => ['cleaned_content'],
                'max_tokens' => 200,
                'temperature' => 0.1,
                'output_actions' => [
                    [
                        'type' => 'update_post_content',
                        'source_variable' => 'cleaned_content',
                        'target' => ''
                    ]
                ]
            ],
            [
                'id' => 'add_links_step',
                'name' => 'Add Links Step',
                'type' => 'ai_assistant',
                'enabled' => true,
                'system_prompt' => 'You are a content processor. Add a new link to the content.',
                'user_message' => 'Add a link to "https://newdomain.com" around the word "replaced" in this content: {content}. If the word "replaced" is not found, add it at the end with the link.',
                'expected_format' => 'text',
                'output_variables' => ['enhanced_content'],
                'max_tokens' => 200,
                'temperature' => 0.1,
                'output_actions' => [
                    [
                        'type' => 'update_post_content',
                        'source_variable' => 'enhanced_content',
                        'target' => ''
                    ]
                ]
            ]
        ]
    ];

    // Sample context
    $context = [
        'translated_post_id' => $test_post_id,
        'original_post_id' => $test_post_id,
        'source_language' => 'en',
        'target_language' => 'en',
        'title' => $test_post_data['post_title'],
        'content' => $test_post_data['post_content'],
        'original_title' => $test_post_data['post_title'],
        'original_content' => $test_post_data['post_content'],
        'post_type' => 'post',
        'author_name' => wp_get_current_user()->display_name,
        'site_url' => get_site_url(),
        'admin_email' => get_option('admin_email'),
        'translated_post' => [
            'ID' => $test_post_id,
            'title' => $test_post_data['post_title'],
            'content' => $test_post_data['post_content'],
            'excerpt' => '',
            'meta' => get_post_meta($test_post_id)
        ]
    ];

    echo "<h3>Testing in Production Mode (Actual Changes):</h3>\n";

    try {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();

        // Execute workflow in production mode
        $result = $workflow_manager->execute_workflow($workflow, $context, false);

        echo "<h4>Production Mode Result:</h4>\n";
        echo "<p><strong>Success:</strong> " . ($result['success'] ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Steps Executed:</strong> " . $result['steps_executed'] . "</p>\n";
        echo "<p><strong>Execution Time:</strong> " . round($result['execution_time'], 3) . "s</p>\n";

        if (isset($result['step_results'])) {
            echo "<h5>Step Results:</h5>\n";
            foreach ($result['step_results'] as $i => $step_result) {
                $step_num = $i + 1;
                echo "<h6>Step {$step_num}: {$step_result['step_name']}</h6>\n";
                echo "<p><strong>Success:</strong> " . ($step_result['success'] ? 'Yes' : 'No') . "</p>\n";

                if ($step_result['success']) {
                    echo "<p><strong>Input Content (first 200 chars):</strong> " . esc_html(substr($step_result['input_variables']['content'] ?? 'N/A', 0, 200)) . "</p>\n";

                    if (isset($step_result['data'])) {
                        echo "<p><strong>Output Variables:</strong></p>\n";
                        echo "<ul>\n";
                        foreach ($step_result['data'] as $key => $value) {
                            $display_value = is_string($value) ? substr($value, 0, 200) : json_encode($value);
                            echo "<li><strong>{$key}:</strong> " . esc_html($display_value) . "</li>\n";
                        }
                        echo "</ul>\n";
                    }

                    if (isset($step_result['output_processing']) && $step_result['output_processing']) {
                        echo "<p><strong>Output Processing:</strong> " . ($step_result['output_processing']['success'] ? 'Success' : 'Failed') . "</p>\n";
                        if (!$step_result['output_processing']['success'] && isset($step_result['output_processing']['errors'])) {
                            echo "<p><strong>Errors:</strong> " . implode(', ', $step_result['output_processing']['errors']) . "</p>\n";
                        }
                    }
                } else {
                    echo "<p><strong>Error:</strong> " . esc_html($step_result['error'] ?? 'Unknown error') . "</p>\n";
                }
                echo "<hr>\n";
            }
        }

        // Check final post content
        $final_post = get_post($test_post_id);
        echo "<h5>Final Post Content:</h5>\n";
        echo "<p>" . esc_html($final_post->post_content) . "</p>\n";

        // Analyze the result
        echo "<h5>Analysis:</h5>\n";
        $original_has_old_link = strpos($test_post_data['post_content'], 'example.com') !== false;
        $final_has_old_link = strpos($final_post->post_content, 'example.com') !== false;
        $final_has_new_link = strpos($final_post->post_content, 'newdomain.com') !== false;

        echo "<p><strong>Original content had old link:</strong> " . ($original_has_old_link ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Final content has old link:</strong> " . ($final_has_old_link ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Final content has new link:</strong> " . ($final_has_new_link ? 'Yes' : 'No') . "</p>\n";

        if (!$final_has_old_link && $final_has_new_link) {
            echo "<p style='color: green;'>✓ Context updating works correctly: Old link removed, new link added</p>\n";
        } elseif ($final_has_old_link && $final_has_new_link) {
            echo "<p style='color: orange;'>⚠ Partial success: New link added but old link not removed (possible context issue)</p>\n";
        } elseif (!$final_has_old_link && !$final_has_new_link) {
            echo "<p style='color: orange;'>⚠ Partial success: Old link removed but new link not added</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Context updating failed: Old link still present, new link not added</p>\n";
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception during workflow execution: " . $e->getMessage() . "</p>\n";
    }

    // Test in test mode as well for comparison
    echo "<h3>Testing in Test Mode (No Actual Changes):</h3>\n";

    try {
        // Reset the post content to original
        wp_update_post([
            'ID' => $test_post_id,
            'post_content' => $test_post_data['post_content']
        ]);

        $test_result = $workflow_manager->execute_workflow($workflow, $context, true);

        echo "<h4>Test Mode Result:</h4>\n";
        echo "<p><strong>Success:</strong> " . ($test_result['success'] ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Steps Executed:</strong> " . $test_result['steps_executed'] . "</p>\n";

        if (isset($test_result['final_context'])) {
            echo "<p><strong>Final Context Content (first 200 chars):</strong> " . esc_html(substr($test_result['final_context']['content'] ?? 'N/A', 0, 200)) . "</p>\n";
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception during test mode execution: " . $e->getMessage() . "</p>\n";
    }

    // Cleanup
    echo "<h3>Cleanup:</h3>\n";
    wp_delete_post($test_post_id, true);
    echo "<p>Deleted test post {$test_post_id}</p>\n";
}

// Show recent logs related to context updating
function show_recent_context_logs()
{
    echo "<h3>Recent Context Update Logs:</h3>\n";

    if (class_exists('PolyTrans_Logs_Manager')) {
        try {
            $logs = PolyTrans_Logs_Manager::get_logs([
                'limit' => 30,
                'source' => ['workflow_executor', 'workflow_output_processor']
            ]);

            if (!empty($logs)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>\n";
                echo "<tr><th>Time</th><th>Source</th><th>Level</th><th>Message</th></tr>\n";

                foreach ($logs as $log) {
                    echo "<tr>\n";
                    echo "<td>" . esc_html($log['timestamp']) . "</td>\n";
                    echo "<td>" . esc_html($log['context']['source'] ?? 'unknown') . "</td>\n";
                    echo "<td>" . esc_html($log['level']) . "</td>\n";
                    echo "<td>" . esc_html($log['message']) . "</td>\n";
                    echo "</tr>\n";
                }

                echo "</table>\n";
            } else {
                echo "<p>No recent context update logs found.</p>\n";
            }
        } catch (\Exception $e) {
            echo "<p style='color: red;'>Error retrieving logs: " . $e->getMessage() . "</p>\n";
        }
    } else {
        echo "<p>Logs Manager not available.</p>\n";
    }
}

// Run tests if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-context-updating.php') {
    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_die('You must be logged in with post editing capabilities to run this test.');
    }

    echo "<!DOCTYPE html><html><head><title>Context Updating Test</title></head><body>\n";
    echo "<h1>PolyTrans Step Context Updating Test</h1>\n";

    test_step_context_updating();
    show_recent_context_logs();

    echo "</body></html>\n";
}
