<?php

/**
 * Test script for post creation author attribution
 * 
 * This script tests that translated posts preserve the original post's author.
 */

require_once(dirname(__FILE__) . '/../../../wp-config.php');

// Test post creation author attribution
function test_post_creation_attribution()
{
    echo "<h2>Testing Post Creation Author Attribution</h2>\n";

    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        echo "<p style='color: red;'>You must be logged in with post editing capabilities to run this test.</p>\n";
        return;
    }

    $current_user_id = get_current_user_id();
    echo "<p><strong>Current User:</strong> " . get_current_user_id() . " (" . wp_get_current_user()->display_name . ")</p>\n";

    // Create a test original post with a specific author
    $original_post_data = [
        'post_title' => 'Test Original Post for Attribution - ' . date('Y-m-d H:i:s'),
        'post_content' => 'This is the original content that will be translated.',
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => $current_user_id
    ];

    $original_post_id = wp_insert_post($original_post_data);

    if (is_wp_error($original_post_id)) {
        echo "<p style='color: red;'>Failed to create original post: " . $original_post_id->get_error_message() . "</p>\n";
        return;
    }

    $original_post = get_post($original_post_id);
    $original_author = get_user_by('id', $original_post->post_author);

    echo "<p><strong>Created Original Post:</strong> ID {$original_post_id}</p>\n";
    echo "<p><strong>Original Post Author:</strong> {$original_author->display_name} (ID: {$original_post->post_author})</p>\n";

    // Test the post creator directly
    echo "<h3>Testing PolyTrans_Translation_Post_Creator:</h3>\n";

    $translated_content = [
        'title' => 'Publicación de prueba original para atribución - ' . date('Y-m-d H:i:s'),
        'content' => 'Este es el contenido original que será traducido.',
        'excerpt' => 'Extracto de prueba traducido'
    ];

    try {
        $post_creator = new PolyTrans_Translation_Post_Creator();
        $translated_post_id = $post_creator->create_post($translated_content, $original_post_id);

        if (is_wp_error($translated_post_id)) {
            echo "<p style='color: red;'>Post Creator Error: " . $translated_post_id->get_error_message() . "</p>\n";
        } else {
            $translated_post = get_post($translated_post_id);
            $translated_author = get_user_by('id', $translated_post->post_author);

            echo "<p><strong>Created Translated Post:</strong> ID {$translated_post_id}</p>\n";
            echo "<p><strong>Translated Post Author:</strong> {$translated_author->display_name} (ID: {$translated_post->post_author})</p>\n";

            if ($translated_post->post_author == $original_post->post_author) {
                echo "<p style='color: green;'>✓ Author attribution preserved correctly by Post Creator</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Author attribution NOT preserved by Post Creator</p>\n";
                echo "<p>Expected: {$original_post->post_author}, Got: {$translated_post->post_author}</p>\n";
            }
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception in Post Creator: " . $e->getMessage() . "</p>\n";
    }

    // Test the metadata manager
    if (!is_wp_error($translated_post_id)) {
        echo "<h3>Testing PolyTrans_Translation_Metadata_Manager:</h3>\n";

        // Temporarily change the author to test the metadata manager fix
        wp_update_post([
            'ID' => $translated_post_id,
            'post_author' => 0 // Set to no author to test the fix
        ]);

        $before_post = get_post($translated_post_id);
        echo "<p><strong>Before Metadata Manager:</strong> Author ID {$before_post->post_author}</p>\n";

        try {
            $metadata_manager = new PolyTrans_Translation_Metadata_Manager();
            $metadata_manager->setup_metadata($translated_post_id, $original_post_id, 'en', $translated_content);

            $after_post = get_post($translated_post_id);
            $after_author = get_user_by('id', $after_post->post_author);

            echo "<p><strong>After Metadata Manager:</strong> {$after_author->display_name} (ID: {$after_post->post_author})</p>\n";

            if ($after_post->post_author == $original_post->post_author) {
                echo "<p style='color: green;'>✓ Author attribution fixed correctly by Metadata Manager</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Author attribution NOT fixed by Metadata Manager</p>\n";
                echo "<p>Expected: {$original_post->post_author}, Got: {$after_post->post_author}</p>\n";
            }
        } catch (\Exception $e) {
            echo "<p style='color: red;'>Exception in Metadata Manager: " . $e->getMessage() . "</p>\n";
        }
    }

    // Test complete translation flow
    echo "<h3>Testing Complete Translation Flow:</h3>\n";

    $translation_data = [
        'translated' => [
            'title' => 'Flujo completo de traducción - ' . date('Y-m-d H:i:s'),
            'content' => 'Este es el contenido traducido completo.',
            'excerpt' => 'Extracto completo traducido'
        ],
        'source_language' => 'en',
        'target_language' => 'es',
        'original_post_id' => $original_post_id
    ];

    try {
        $coordinator = new PolyTrans_Translation_Coordinator();
        $result = $coordinator->process_translation($translation_data);

        if ($result['success']) {
            echo "<p style='color: green;'>✓ Translation flow completed successfully</p>\n";
            echo "<p>Check the logs and post list to verify author attribution in the complete flow.</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Translation flow failed: " . ($result['error'] ?? 'Unknown error') . "</p>\n";
        }
    } catch (\Exception $e) {
        echo "<p style='color: red;'>Exception in Translation Flow: " . $e->getMessage() . "</p>\n";
    }

    // Cleanup - delete test posts
    echo "<h3>Cleanup:</h3>\n";
    if (isset($translated_post_id) && !is_wp_error($translated_post_id)) {
        wp_delete_post($translated_post_id, true);
        echo "<p>Deleted translated post {$translated_post_id}</p>\n";
    }
    wp_delete_post($original_post_id, true);
    echo "<p>Deleted original post {$original_post_id}</p>\n";
}

// Show recent attribution logs
function show_recent_post_attribution_logs()
{
    echo "<h3>Recent Post Attribution Logs:</h3>\n";

    if (class_exists('PolyTrans_Logs_Manager')) {
        try {
            // Get recent logs related to post attribution
            $logs = PolyTrans_Logs_Manager::get_logs([
                'limit' => 20,
                'source' => ['translation_post_creator', 'translation_metadata_manager']
            ]);

            if (!empty($logs)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
                echo "<tr><th>Time</th><th>Source</th><th>Level</th><th>Message</th><th>Context</th></tr>\n";

                foreach ($logs as $log) {
                    $context_str = !empty($log['context']) ? json_encode($log['context'], JSON_PRETTY_PRINT) : '';
                    echo "<tr>\n";
                    echo "<td>" . esc_html($log['timestamp']) . "</td>\n";
                    echo "<td>" . esc_html($log['context']['source'] ?? 'unknown') . "</td>\n";
                    echo "<td>" . esc_html($log['level']) . "</td>\n";
                    echo "<td>" . esc_html($log['message']) . "</td>\n";
                    echo "<td><pre style='font-size: 11px;'>" . esc_html($context_str) . "</pre></td>\n";
                    echo "</tr>\n";
                }

                echo "</table>\n";
            } else {
                echo "<p>No recent post attribution logs found.</p>\n";
            }
        } catch (\Exception $e) {
            echo "<p style='color: red;'>Error retrieving logs: " . $e->getMessage() . "</p>\n";
        }
    } else {
        echo "<p>Logs Manager not available.</p>\n";
    }
}

// Run tests if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-post-attribution.php') {
    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_die('You must be logged in with post editing capabilities to run this test.');
    }

    echo "<!DOCTYPE html><html><head><title>Post Attribution Test</title></head><body>\n";
    echo "<h1>PolyTrans Post Creation Author Attribution Test</h1>\n";

    test_post_creation_attribution();
    show_recent_post_attribution_logs();

    echo "</body></html>\n";
}
