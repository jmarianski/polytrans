<?php

/**
 * Test script for workflow output actions
 * 
 * This script tests the integration between workflow execution and output processing.
 * Run this script from WordPress admin or via CLI to test the implementation.
 */

// Ensure this is only run in WordPress context
if (!defined('ABSPATH')) {
    die('This script must be run within WordPress');
}

// Test workflow with output actions
function test_workflow_output_actions()
{
    // Sample workflow configuration with output actions
    $workflow = [
        'id' => 'test_workflow',
        'name' => 'Test Workflow with Output Actions',
        'description' => 'Test workflow to validate output action processing',
        'enabled' => true,
        'trigger_events' => ['translation_completed'],
        'target_languages' => ['en', 'es'],
        'steps' => [
            [
                'id' => 'test_step',
                'name' => 'Test AI Step',
                'type' => 'ai_assistant',
                'enabled' => true,
                'system_prompt' => 'You are a helpful assistant. Always respond with valid JSON.',
                'user_message' => 'Analyze this title: {title}. Respond with JSON containing "analysis" and "improved_title" fields.',
                'expected_format' => 'json',
                'output_variables' => ['analysis', 'improved_title'],
                'max_tokens' => 150,
                'temperature' => 0.3,
                'output_actions' => [
                    [
                        'type' => 'update_post_meta',
                        'source_variable' => 'analysis',
                        'target' => 'ai_analysis'
                    ],
                    [
                        'type' => 'update_post_title',
                        'source_variable' => 'improved_title',
                        'target' => ''
                    ]
                ]
            ]
        ]
    ];

    // Sample context (would normally come from translation completion)
    $context = [
        'original_post_id' => 1, // Assuming post ID 1 exists
        'translated_post_id' => 1, // Same for simplicity
        'source_language' => 'en',
        'target_language' => 'es',
        'title' => 'Sample Post Title',
        'content' => 'Sample post content for testing.',
        'original_title' => 'Original Post Title',
        'original_content' => 'Original post content.',
        'post_type' => 'post',
        'author_name' => 'Test Author',
        'site_url' => get_site_url(),
        'admin_email' => get_option('admin_email')
    ];

    echo "<h2>Testing Workflow Output Actions</h2>\n";
    echo "<h3>Workflow Configuration:</h3>\n";
    echo "<pre>" . print_r($workflow, true) . "</pre>\n";

    echo "<h3>Context:</h3>\n";
    echo "<pre>" . print_r($context, true) . "</pre>\n";

    // Test workflow execution
    try {
        $executor = new PolyTrans_Workflow_Executor();
        $result = $executor->execute($workflow, $context);

        echo "<h3>Execution Result:</h3>\n";
        echo "<pre>" . print_r($result, true) . "</pre>\n";

        if ($result['success']) {
            echo "<p style='color: green;'><strong>✅ Workflow executed successfully!</strong></p>\n";

            // Check if output processing was performed
            if (isset($result['step_results'][0]['output_processing'])) {
                $output_result = $result['step_results'][0]['output_processing'];
                echo "<h3>Output Processing Result:</h3>\n";
                echo "<pre>" . print_r($output_result, true) . "</pre>\n";

                if ($output_result['success']) {
                    echo "<p style='color: green;'><strong>✅ Output actions processed successfully!</strong></p>\n";
                } else {
                    echo "<p style='color: red;'><strong>❌ Output action processing failed:</strong></p>\n";
                    if (isset($output_result['errors'])) {
                        foreach ($output_result['errors'] as $error) {
                            echo "<p style='color: red;'>• " . esc_html($error) . "</p>\n";
                        }
                    }
                }
            } else {
                echo "<p style='color: orange;'><strong>⚠️ No output processing result found</strong></p>\n";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ Workflow execution failed: " . esc_html($result['error']) . "</strong></p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>❌ Exception during testing: " . esc_html($e->getMessage()) . "</strong></p>\n";
        echo "<pre>" . esc_html($e->getTraceAsString()) . "</pre>\n";
    }
}

// Test post meta variable resolution
function test_post_meta_variables()
{
    echo "<h2>Testing Post Meta Variable Resolution</h2>\n";

    // Create test post with meta
    $test_post_id = wp_insert_post([
        'post_title' => 'Test Post for Meta Variables',
        'post_content' => 'Test content',
        'post_status' => 'publish',
        'post_type' => 'post'
    ]);

    if ($test_post_id) {
        // Add some test meta
        update_post_meta($test_post_id, 'seo_title', 'SEO Optimized Title');
        update_post_meta($test_post_id, 'custom_field', 'Custom Value');
        update_post_meta($test_post_id, 'numeric_field', 42);

        echo "<p>Created test post ID: {$test_post_id}</p>\n";

        // Test variable provider
        $provider = new PolyTrans_Post_Data_Provider();
        $context = [
            'original_post_id' => $test_post_id,
            'translated_post_id' => $test_post_id
        ];

        $variables = $provider->get_variables($context);

        echo "<h3>Generated Variables (showing meta section):</h3>\n";
        if (isset($variables['original_post']['meta'])) {
            echo "<pre>" . print_r($variables['original_post']['meta'], true) . "</pre>\n";
            echo "<p style='color: green;'><strong>✅ Post meta successfully included in variables!</strong></p>\n";
        } else {
            echo "<p style='color: red;'><strong>❌ Post meta not found in variables</strong></p>\n";
        }

        // Clean up
        wp_delete_post($test_post_id, true);
        echo "<p>Cleaned up test post</p>\n";
    } else {
        echo "<p style='color: red;'><strong>❌ Failed to create test post</strong></p>\n";
    }
}

// Only run if this is a direct request (not included from other files)
if (basename($_SERVER['PHP_SELF']) === 'test-workflow-output.php') {
    // Check if user has admin capabilities
    if (!current_user_can('manage_options')) {
        die('Access denied. Admin capabilities required.');
    }

    echo "<!DOCTYPE html><html><head><title>Workflow Output Actions Test</title></head><body>\n";

    test_post_meta_variables();
    echo "<hr>\n";
    test_workflow_output_actions();

    echo "</body></html>\n";
}
