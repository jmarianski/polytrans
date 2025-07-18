<?php

/**
 * Test script to debug interpolation in workflow execution
 */

// Include WordPress
require_once('/home/jm/projects/trans-info/transinfo-wp-docker/public/wp-config.php');

// Test workflow with AI assistant step
$test_workflow = [
    'id' => 'test_interpolation',
    'name' => 'Test Interpolation',
    'target_language' => 'en',
    'enabled' => true,
    'steps' => [
        [
            'id' => 'step_1',
            'name' => 'Test AI Assistant',
            'type' => 'ai_assistant',
            'system_prompt' => 'You are a helpful assistant working with content titled "{title}". The content is: {content}',
            'user_message' => 'Please analyze this content about "{title}" and provide a summary. The post ID is {post_id}.',
            'temperature' => 0.7,
            'max_tokens' => 500,
            'expected_format' => 'text',
            'output_actions' => []
        ]
    ]
];

$test_context = [
    'post_id' => 123,
    'title' => 'Test Article Title',
    'content' => 'This is a test article content that should be interpolated into the prompts.',
    'excerpt' => 'Test excerpt',
    'target_language' => 'en',
    'translated_post' => [
        'ID' => 123,
        'post_title' => 'Test Article Title',
        'post_content' => 'This is a test article content that should be interpolated into the prompts.',
        'post_excerpt' => 'Test excerpt',
        'meta' => [
            'custom_field_1' => 'Custom Value 1',
            '_yoast_wpseo_title' => 'SEO Title'
        ]
    ],
    'trigger' => 'test'
];

echo "Starting interpolation test...\n";

// Initialize workflow manager
$workflow_manager = new PolyTrans_Workflow_Manager();

// Execute workflow in test mode
$result = $workflow_manager->execute_workflow($test_workflow, $test_context, true);

echo "Test completed.\n";
echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
echo "Steps executed: " . ($result['steps_executed'] ?? 0) . "\n";

if (isset($result['step_results'])) {
    foreach ($result['step_results'] as $index => $step_result) {
        echo "\nStep " . ($index + 1) . ":\n";
        echo "  Type: " . ($step_result['step_type'] ?? 'unknown') . "\n";
        echo "  Success: " . ($step_result['success'] ? 'Yes' : 'No') . "\n";

        if (isset($step_result['interpolated_system_prompt'])) {
            echo "  Has interpolated system prompt: Yes\n";
            echo "  Interpolated system prompt: " . substr($step_result['interpolated_system_prompt'], 0, 200) . "...\n";
        } else {
            echo "  Has interpolated system prompt: No\n";
        }

        if (isset($step_result['interpolated_user_message'])) {
            echo "  Has interpolated user message: Yes\n";
            echo "  Interpolated user message: " . substr($step_result['interpolated_user_message'], 0, 200) . "...\n";
        } else {
            echo "  Has interpolated user message: No\n";
        }

        if (isset($step_result['error'])) {
            echo "  Error: " . $step_result['error'] . "\n";
        }
    }
}

if (isset($result['error'])) {
    echo "\nOverall error: " . $result['error'] . "\n";
}

echo "\nCheck the error log for debug messages.\n";
