<?php

/**
 * Test script to verify post-processing logging
 * This can be accessed via WP-CLI or by including it in a test
 */

if (!defined('ABSPATH')) {
    // Include WordPress if running standalone
    require_once(dirname(__FILE__) . '/../../../wp-config.php');
}

// Include required classes
require_once plugin_dir_path(__FILE__) . 'includes/postprocessing/class-workflow-manager.php';

echo "=== Testing Post-Processing Workflow Logging ===\n\n";

// Get the workflow manager
$workflow_manager = PolyTrans_Workflow_Manager::get_instance();

// Simulate a translation completion event
echo "1. Simulating translation completion event...\n";
echo "Check the error_log for PolyTrans Workflow Manager messages.\n\n";

// Test with sample data
$original_post_id = 123;
$translated_post_id = 456;
$target_language = 'pl';

// Trigger workflows
$workflow_manager->trigger_workflows($original_post_id, $translated_post_id, $target_language);

echo "2. Translation completion event processed.\n";
echo "Check your WordPress error log for detailed workflow execution logs.\n\n";

// Test workflow execution directly (if any workflows exist)
$storage_manager = $workflow_manager->get_storage_manager();
$workflows = $storage_manager->get_all_workflows();

if (!empty($workflows)) {
    echo "3. Testing direct workflow execution...\n";

    $test_workflow = $workflows[0]; // Take the first workflow
    $test_context = [
        'original_post_id' => $original_post_id,
        'translated_post_id' => $translated_post_id,
        'target_language' => $target_language,
        'trigger' => 'manual_test'
    ];

    echo "Executing workflow: " . ($test_workflow['name'] ?? 'Unknown') . "\n";
    echo "Check the error_log for detailed step-by-step execution logs.\n\n";

    $result = $workflow_manager->execute_workflow($test_workflow, $test_context, true);

    if ($result['success']) {
        echo "✅ Workflow executed successfully!\n";
        echo "Steps executed: " . $result['steps_executed'] . "\n";
        echo "Execution time: " . round($result['execution_time'], 3) . "s\n";
    } else {
        echo "❌ Workflow execution failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "3. No workflows found to test.\n";
    echo "Create a workflow in the admin interface first.\n";
}

echo "\n=== Test Complete ===\n";
echo "Check your WordPress error log (usually in wp-content/debug.log or server error logs)\n";
echo "Look for log entries starting with 'PolyTrans Workflow'.\n";
