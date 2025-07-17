<?php
/**
 * Test script for processed actions count
 * 
 * This script tests that the processed actions count is correctly reported.
 */

require_once(dirname(__FILE__) . '/../../../wp-config.php');

// Test processed actions count
function test_processed_actions_count()
{
    echo "<h2>Testing Processed Actions Count</h2>\n";
    
    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        echo "<p style='color: red;'>You must be logged in with post editing capabilities to run this test.</p>\n";
        return;
    }

    // Create a test post
    $test_post_data = [
        'post_title' => 'Test Post for Actions Count - ' . date('Y-m-d H:i:s'),
        'post_content' => 'This is test content for actions count testing.',
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

    // Create a workflow with multiple output actions
    $workflow = [
        'id' => 'test_actions_count_workflow',
        'name' => 'Test Actions Count Workflow',
        'description' => 'Test workflow to validate actions count reporting',
        'enabled' => true,
        'steps' => [
            [
                'id' => 'multiple_actions_step',
                'name' => 'Multiple Actions Step',
                'type' => 'ai_assistant',
                'enabled' => true,
                'system_prompt' => 'You are a content processor. Return structured data.',
                'user_message' => 'Process this content: {content}. Return JSON with: {"new_title": "Processed Title", "new_content": "Processed content", "new_excerpt": "Processed excerpt"}',
                'expected_format' => 'json',
                'output_variables' => ['new_title', 'new_content', 'new_excerpt'],
                'max_tokens' => 200,
                'temperature' => 0.1,
                'output_actions' => [
                    [
                        'type' => 'update_post_title',
                        'source_variable' => 'new_title',
                        'target' => ''
                    ],
                    [
                        'type' => 'update_post_content',
                        'source_variable' => 'new_content',
                        'target' => ''
                    ],
                    [
                        'type' => 'update_post_excerpt',
                        'source_variable' => 'new_excerpt',
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

    echo "<h3>Expected: 3 actions (title, content, excerpt update)</h3>\n";
    
    // Test in test mode first
    echo "<h4>Test Mode Results:</h4>\n";
    try {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $result = $workflow_manager->execute_workflow($workflow, $context, true);
        
        if ($result['success'] && isset($result['step_results'][0]['output_processing'])) {
            $output_processing = $result['step_results'][0]['output_processing'];
            $processed_count = $output_processing['processed_actions'] ?? 'N/A';
            echo "<p><strong>Processed Actions Count:</strong> {$processed_count}</p>\n";
            
            if ($processed_count === 3) {
                echo "<p style='color: green;'>✓ Correct actions count in test mode</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Incorrect actions count in test mode (expected 3, got {$processed_count})</p>\n";
            }
        } else {
            echo "<p style='color: red;'>✗ Test mode failed or no output processing result</p>\n";
            if (isset($result['step_results'][0]['error'])) {
                echo "<p>Error: " . esc_html($result['step_results'][0]['error']) . "</p>\n";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception in test mode: " . $e->getMessage() . "</p>\n";
    }

    // Test in production mode
    echo "<h4>Production Mode Results:</h4>\n";
    try {
        $result = $workflow_manager->execute_workflow($workflow, $context, false);
        
        if ($result['success'] && isset($result['step_results'][0]['output_processing'])) {
            $output_processing = $result['step_results'][0]['output_processing'];
            $processed_count = $output_processing['processed_actions'] ?? 'N/A';
            echo "<p><strong>Processed Actions Count:</strong> {$processed_count}</p>\n";
            
            if ($processed_count === 3) {
                echo "<p style='color: green;'>✓ Correct actions count in production mode</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Incorrect actions count in production mode (expected 3, got {$processed_count})</p>\n";
            }
            
            // Check if changes were actually applied
            $updated_post = get_post($test_post_id);
            echo "<p><strong>Post Title Changed:</strong> " . ($updated_post->post_title !== $test_post_data['post_title'] ? 'Yes' : 'No') . "</p>\n";
            echo "<p><strong>Post Content Changed:</strong> " . ($updated_post->post_content !== $test_post_data['post_content'] ? 'Yes' : 'No') . "</p>\n";
            echo "<p><strong>Post Excerpt Changed:</strong> " . ($updated_post->post_excerpt !== '' ? 'Yes' : 'No') . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Production mode failed or no output processing result</p>\n";
            if (isset($result['step_results'][0]['error'])) {
                echo "<p>Error: " . esc_html($result['step_results'][0]['error']) . "</p>\n";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception in production mode: " . $e->getMessage() . "</p>\n";
    }

    // Test output processor directly
    echo "<h4>Direct Output Processor Test:</h4>\n";
    try {
        $output_processor = PolyTrans_Workflow_Output_Processor::get_instance();
        
        // Mock step results
        $step_results = [
            'multiple_actions_step' => [
                'success' => true,
                'data' => [
                    'new_title' => 'Direct Test Title',
                    'new_content' => 'Direct test content',
                    'new_excerpt' => 'Direct test excerpt'
                ]
            ]
        ];
        
        $output_actions = $workflow['steps'][0]['output_actions'];
        
        $output_result = $output_processor->process_step_outputs(
            $step_results,
            $output_actions,
            $context,
            true, // test mode
            $workflow
        );
        
        echo "<p><strong>Direct Test - Processed Actions:</strong> " . ($output_result['processed_actions'] ?? 'N/A') . "</p>\n";
        echo "<p><strong>Direct Test - Success:</strong> " . ($output_result['success'] ? 'Yes' : 'No') . "</p>\n";
        
        if (isset($output_result['errors']) && !empty($output_result['errors'])) {
            echo "<p><strong>Direct Test - Errors:</strong> " . implode(', ', $output_result['errors']) . "</p>\n";
        }
        
        if (isset($output_result['changes'])) {
            echo "<p><strong>Direct Test - Changes Created:</strong> " . count($output_result['changes']) . "</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception in direct test: " . $e->getMessage() . "</p>\n";
    }

    // Cleanup
    echo "<h3>Cleanup:</h3>\n";
    wp_delete_post($test_post_id, true);
    echo "<p>Deleted test post {$test_post_id}</p>\n";
}

// Run tests if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-actions-count.php') {
    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_die('You must be logged in with post editing capabilities to run this test.');
    }

    echo "<!DOCTYPE html><html><head><title>Actions Count Test</title></head><body>\n";
    echo "<h1>PolyTrans Processed Actions Count Test</h1>\n";
    
    test_processed_actions_count();
    
    echo "</body></html>\n";
}
