<?php

/**
 * Test script for status and date workflow output actions
 * 
 * This script tests the new update_post_status and update_post_date actions
 * with AI response containing status: "future" and date: "2025-07-08"
 */

// Ensure this is only run in WordPress context
if (!defined('ABSPATH')) {
    die('This script must be run within WordPress');
}

echo "<h2>Testing Status and Date Workflow Actions</h2>\n";

// Simulate the AI response data structure that the user is seeing
$step_results = [
    'success' => true,
    'data' => [
        'reasoning' => 'This post should be scheduled for the future to align with the planned marketing campaign.',
        'status' => 'future',
        'date' => '2025-07-08'
    ]
];

// Simulate the workflow output actions configuration
$output_actions = [
    [
        'type' => 'update_post_status',
        'source_variable' => 'status',
        'target' => ''
    ],
    [
        'type' => 'update_post_date',
        'source_variable' => 'date',
        'target' => ''
    ]
];

// Mock context (normally this would be actual post data)
$context = [
    'post_id' => 123,
    'post_title' => 'Test Post',
    'post_content' => 'Test content'
];

echo "<h3>Input Data:</h3>\n";
echo "<strong>Step Results:</strong><br>\n";
echo "<pre>" . htmlspecialchars(json_encode($step_results, JSON_PRETTY_PRINT)) . "</pre>\n";

echo "<strong>Output Actions:</strong><br>\n";
echo "<pre>" . htmlspecialchars(json_encode($output_actions, JSON_PRETTY_PRINT)) . "</pre>\n";

// Test the workflow output processor
$output_processor = new PolyTrans_Workflow_Output_Processor();

echo "<h3>Processing Actions...</h3>\n";

$result = $output_processor->process_step_outputs(
    $step_results,
    $output_actions,
    $context,
    true, // test mode
    null
);

echo "<h3>Results:</h3>\n";
echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>\n";

if ($result['success']) {
    if (isset($result['changes']) && !empty($result['changes'])) {
        echo "<h3>Changes Generated:</h3>\n";
        foreach ($result['changes'] as $i => $change) {
            echo "<h4>Change " . ($i + 1) . ":</h4>\n";
            echo "<pre>" . htmlspecialchars(json_encode($change, JSON_PRETTY_PRINT)) . "</pre>\n";
        }
    } else {
        echo "<div style='color: red;'><strong>❌ No changes generated!</strong></div>\n";
    }
} else {
    echo "<div style='color: red;'><strong>❌ Processing failed:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</div>\n";
}

echo "<h3>Debug Information:</h3>\n";
echo "<p>Check your WordPress error log for detailed debug output from the workflow processor.</p>\n";
echo "<p>The processor will log each step of the change object creation process.</p>\n";

?>
