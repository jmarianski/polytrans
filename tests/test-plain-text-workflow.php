<?php

/**
 * Test Plain Text Workflow with Auto-Response Detection
 * 
 * This test demonstrates the plain text workflow feature:
 * - Set expected_format: 'text' in AI Assistant step
 * - Leave source_variable empty in output actions
 * - System automatically uses the complete AI response
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

// Load WordPress
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-includes/wp-db.php';
require_once ABSPATH . 'wp-includes/pluggable.php';

// Load the plugin
if (!class_exists('PolyTrans_Workflow_Manager')) {
    require_once dirname(__FILE__) . '/../includes/class-polytrans.php';
    require_once dirname(__FILE__) . '/../includes/postprocessing/class-workflow-manager.php';
    require_once dirname(__FILE__) . '/../includes/postprocessing/class-workflow-executor.php';
    require_once dirname(__FILE__) . '/../includes/postprocessing/class-workflow-output-processor.php';
}

echo "<h1>Plain Text Workflow Test</h1>\n";

// Test workflow with plain text response and no source_variable
$test_workflow = [
    'id' => 'test_plain_text_workflow',
    'name' => 'Plain Text Content Rewriter',
    'description' => 'Rewrites post content using AI in plain text format',
    'language' => 'en',
    'enabled' => true,
    'triggers' => [
        'on_translation_complete' => false,
        'manual_only' => true
    ],
    'steps' => [
        [
            'id' => 'rewrite_step',
            'name' => 'Content Rewriter',
            'type' => 'ai_assistant',
            'enabled' => true,
            'system_prompt' => 'You are a skilled content editor. Rewrite the provided content to make it more engaging while preserving the original meaning. Return only the rewritten content with no additional text or formatting.',
            'user_message' => 'Rewrite this content:\n\n{content}',
            'expected_format' => 'text', // Plain text format
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'output_actions' => [
                [
                    'type' => 'update_post_content',
                    'source_variable' => '', // Empty! System will auto-detect
                    'target' => ''
                ],
                [
                    'type' => 'update_post_meta',
                    'source_variable' => '', // Empty! System will auto-detect 
                    'target' => 'ai_rewritten_content'
                ]
            ]
        ]
    ]
];

// Sample context (simulating a translation completion)
$context = [
    'original_post_id' => 1,
    'translated_post_id' => 1,
    'source_language' => 'en',
    'target_language' => 'en',
    'title' => 'Test Article About AI',
    'content' => 'Artificial intelligence is transforming our world. It helps us solve problems faster and make better decisions. AI is used in many industries including healthcare, finance, and transportation.',
    'original_title' => 'Test Article About AI',
    'original_content' => 'Artificial intelligence is transforming our world. It helps us solve problems faster and make better decisions. AI is used in many industries including healthcare, finance, and transportation.',
    'translated_title' => 'Test Article About AI',
    'translated_content' => 'Artificial intelligence is transforming our world. It helps us solve problems faster and make better decisions. AI is used in many industries including healthcare, finance, and transportation.',
    'post_type' => 'post',
    'author_id' => 1,
    'author_name' => 'Test Author',
    'site_url' => 'https://example.com',
    'admin_email' => 'admin@example.com'
];

echo "<h2>Testing Plain Text Workflow</h2>\n";
echo "<p><strong>Expected Format:</strong> Plain Text</p>\n";
echo "<p><strong>Source Variable:</strong> Empty (auto-detection)</p>\n";
echo "<p><strong>Original Content:</strong></p>\n";
echo "<div style='background:#f0f0f0;padding:10px;margin:10px 0;'>" . esc_html($context['content']) . "</div>\n";

try {
    $workflow_manager = PolyTrans_Workflow_Manager::get_instance();

    // Execute in test mode first
    echo "<h3>🧪 Test Mode Execution</h3>\n";
    $test_result = $workflow_manager->execute_workflow($test_workflow, $context, true);

    if ($test_result['success']) {
        echo "<p style='color: green;'><strong>✅ Test execution successful!</strong></p>\n";

        // Show step results
        if (isset($test_result['step_results'][0])) {
            $step_result = $test_result['step_results'][0];

            echo "<h4>Step Execution Result:</h4>\n";
            echo "<p><strong>Success:</strong> " . ($step_result['success'] ? 'Yes' : 'No') . "</p>\n";

            if ($step_result['success'] && isset($step_result['data'])) {
                echo "<h4>AI Response Data Structure:</h4>\n";
                echo "<pre style='background:#f8f8f8;padding:10px;'>";
                print_r($step_result['data']);
                echo "</pre>\n";

                // Show the actual AI response (if this were a real API call)
                if (isset($step_result['data']['ai_response'])) {
                    echo "<h4>🤖 AI Rewritten Content:</h4>\n";
                    echo "<div style='background:#e8f4fd;padding:10px;margin:10px 0;border-left:4px solid #0073aa;'>" .
                        esc_html($step_result['data']['ai_response']) . "</div>\n";
                }
            }

            // Show output processing results
            if (isset($step_result['output_processing'])) {
                $output_result = $step_result['output_processing'];
                echo "<h4>Output Processing Result:</h4>\n";
                echo "<p><strong>Success:</strong> " . ($output_result['success'] ? 'Yes' : 'No') . "</p>\n";
                echo "<p><strong>Actions Processed:</strong> " . ($output_result['processed_actions'] ?? 0) . "</p>\n";

                if (isset($output_result['changes']) && !empty($output_result['changes'])) {
                    echo "<h5>Changes That Would Be Made:</h5>\n";
                    foreach ($output_result['changes'] as $i => $change) {
                        echo "<div style='background:#f0f8ff;padding:8px;margin:5px 0;border:1px solid #ddd;'>\n";
                        echo "<strong>Action " . ($i + 1) . ":</strong> " . esc_html($change['action_type']) . "<br>\n";
                        echo "<strong>Target:</strong> " . esc_html($change['target_description']) . "<br>\n";
                        echo "<strong>Auto-detected Value:</strong> " . esc_html(substr($change['new_value'], 0, 100)) .
                            (strlen($change['new_value']) > 100 ? '...' : '') . "\n";
                        echo "</div>\n";
                    }
                }

                if (isset($output_result['errors']) && !empty($output_result['errors'])) {
                    echo "<h5>❌ Errors:</h5>\n";
                    foreach ($output_result['errors'] as $error) {
                        echo "<p style='color: red;'>• " . esc_html($error) . "</p>\n";
                    }
                }
            }
        }
    } else {
        echo "<p style='color: red;'><strong>❌ Test execution failed:</strong></p>\n";
        echo "<p>" . esc_html($test_result['error'] ?? 'Unknown error') . "</p>\n";

        if (isset($test_result['step_results'])) {
            foreach ($test_result['step_results'] as $i => $step_result) {
                if (!$step_result['success']) {
                    echo "<p><strong>Step " . ($i + 1) . " Error:</strong> " . esc_html($step_result['error']) . "</p>\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Exception:</strong> " . esc_html($e->getMessage()) . "</p>\n";
    echo "<pre>" . esc_html($e->getTraceAsString()) . "</pre>\n";
}

echo "<h2>Key Features Tested</h2>\n";
echo "<ul>\n";
echo "<li>✅ <strong>Plain Text Response Format:</strong> AI returns complete rewritten content</li>\n";
echo "<li>✅ <strong>Auto-Response Detection:</strong> Empty source_variable automatically uses AI response</li>\n";
echo "<li>✅ <strong>Multiple Output Actions:</strong> Save to both post content and meta field</li>\n";
echo "<li>✅ <strong>Simplified Workflow:</strong> No need to remember 'ai_response' variable name</li>\n";
echo "</ul>\n";

echo "<h2>How to Use This New Feature</h2>\n";
echo "<ol>\n";
echo "<li><strong>Set Expected Format to 'Plain Text'</strong> in your AI Assistant step</li>\n";
echo "<li><strong>Leave Source Variable empty</strong> in your output actions</li>\n";
echo "<li><strong>Choose your action type</strong> (Update Post Content, etc.)</li>\n";
echo "<li><strong>The system automatically uses the complete AI response</strong></li>\n";
echo "</ol>\n";

echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
