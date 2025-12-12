<?php

/**
 * Test the consolidated logging for workflow execution
 */

// Include WordPress if not already loaded
if (!defined('ABSPATH')) {
    // Try different possible WordPress locations
    $wp_config_paths = [
        dirname(__FILE__) . '/../../trans.info/wp-config.php',
        dirname(__FILE__) . '/../../../wp-config.php',
        '/var/www/html/wp-config.php'
    ];

    $wp_loaded = false;
    foreach ($wp_config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }

    if (!$wp_loaded) {
        echo "WordPress not found, testing file syntax only...\n";
        // Just test that our files can be loaded without errors
        require_once dirname(__FILE__) . '/includes/core/class-logs-manager.php';
        require_once dirname(__FILE__) . '/includes/postprocessing/class-workflow-manager.php';
        require_once dirname(__FILE__) . '/includes/postprocessing/class-workflow-executor.php';
        echo "Files loaded successfully - no syntax errors!\n";
        exit(0);
    }
}

// Simulate a simple workflow execution to test the new consolidated logging
echo "Testing Consolidated Logging for Workflow Execution\n";
echo "==================================================\n\n";

// Create a test context similar to what would be generated during translation
$test_context = [
    'title' => 'Test Article Title',
    'content' => 'This is test content for our workflow execution test.',
    'target_language' => 'de',
    'source_language' => 'en',
    'translation_service' => 'openai',
    'post_id' => 123,
    'translated_post' => [
        'ID' => 456,
        'post_title' => 'Test Artikel Titel',
        'post_content' => 'Dies ist Testinhalt für unseren Workflow-Ausführungstest.',
        'meta' => [
            'quality_score' => '0.95',
            'word_count' => '15'
        ]
    ]
];

// Create a simple test workflow
$test_workflow = [
    'id' => 'test-consolidation',
    'name' => 'Test Consolidation Workflow',
    'description' => 'A test workflow to verify consolidated logging',
    'enabled' => true,
    'triggers' => ['translation_completed'],
    'execution_mode' => 'auto',
    'conditions' => [],
    'steps' => [
        [
            'id' => 'test-step-1',
            'name' => 'Test Step 1',
            'type' => 'ai_assistant',
            'enabled' => true,
            'system_prompt' => 'You are a helpful assistant.',
            'user_message' => 'Summarize this: {{content}}',
            'output_variables' => ['summary'],
            'output_actions' => []
        ],
        [
            'id' => 'test-step-2',
            'name' => 'Test Step 2',
            'type' => 'ai_assistant',
            'enabled' => false, // This should be skipped
            'system_prompt' => 'You are a helpful assistant.',
            'user_message' => 'This step should be skipped',
            'output_variables' => ['skipped'],
            'output_actions' => []
        ]
    ]
];

try {
    // Initialize workflow manager
    $workflow_manager = PolyTrans_Workflow_Manager::get_instance();

    echo "Testing workflow execution with consolidated logging...\n\n";

    // Execute workflow in test mode to avoid actual API calls
    $result = $workflow_manager->execute_workflow($test_workflow, $test_context, true);

    echo "Workflow execution result:\n";
    echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "Steps executed: " . $result['steps_executed'] . "\n";
    echo "Execution time: " . round($result['execution_time'], 3) . "s\n";

    if (!$result['success'] && isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n";
    }

    echo "\n=== Check the logs above for consolidated output ===\n";
    echo "You should see:\n";
    echo "1. Single workflow start log with variable count\n";
    echo "2. 'Executing step #1' and 'Skipping step #2' logs\n";
    echo "3. 'Finished step #1' or 'Failed step #1' log\n";
    echo "4. Single workflow completion log\n";
    echo "5. NO 'Merged step output...' or verbose step details\n";
} catch (\Exception $e) {
    echo "Error during test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
