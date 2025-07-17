<?php

/**
 * Test script for post creation author attribution and revisions
 * 
 * This script tests the author attribution during post creation and checks revisions.
 */

require_once(dirname(__FILE__) . '/../../../wp-config.php');

// Test post creation author attribution and revisions
function test_post_creation_revisions()
{
    echo "<h2>Testing Post Creation Author Attribution and Revisions</h2>\n";

    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        echo "<p style='color: red;'>You must be logged in with post editing capabilities to run this test.</p>\n";
        return;
    }

    $current_user_id = get_current_user_id();
    echo "<p><strong>Current User:</strong> " . get_current_user_id() . " (" . wp_get_current_user()->display_name . ")</p>\n";

    // Create a test original post
    $original_post_data = [
        'post_title' => 'Original Post for Revision Test - ' . date('Y-m-d H:i:s'),
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

    // Check original post revisions
    $original_revisions = wp_get_post_revisions($original_post_id, ['posts_per_page' => 10]);
    echo "<p><strong>Original Post Revisions:</strong> " . count($original_revisions) . "</p>\n";

    if (!empty($original_revisions)) {
        echo "<h4>Original Post Revisions:</h4>\n";
        foreach ($original_revisions as $revision) {
            $revision_author = get_user_by('id', $revision->post_author);
            echo "<p>- Revision ID {$revision->ID}: Author {$revision_author->display_name} (ID: {$revision->post_author}), Date: {$revision->post_date}</p>\n";
        }
    }

    // Now test the PolyTrans post creation
    echo "<h3>Testing PolyTrans Post Creation:</h3>\n";

    $translated_content = [
        'title' => 'Translated Post for Revision Test - ' . date('Y-m-d H:i:s'),
        'content' => 'This is the translated content.',
        'excerpt' => 'Translated excerpt'
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
            echo "<p><strong>Translated Post Status:</strong> {$translated_post->post_status}</p>\n";

            // Check author attribution
            if ($translated_post->post_author == $original_post->post_author) {
                echo "<p style='color: green;'>✓ Post author correctly preserved</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Post author NOT preserved</p>\n";
                echo "<p>Expected: {$original_post->post_author}, Got: {$translated_post->post_author}</p>\n";
            }

            // Check if revisions are enabled for this post type
            if (post_type_supports('post', 'revisions')) {
                echo "<p><strong>Post Type Supports Revisions:</strong> Yes</p>\n";

                // Get revisions for the translated post
                $translated_revisions = wp_get_post_revisions($translated_post_id, ['posts_per_page' => 10]);
                echo "<p><strong>Translated Post Revisions:</strong> " . count($translated_revisions) . "</p>\n";

                if (!empty($translated_revisions)) {
                    echo "<h4>Translated Post Revisions:</h4>\n";
                    foreach ($translated_revisions as $revision) {
                        $revision_author = get_user_by('id', $revision->post_author);
                        echo "<p>- Revision ID {$revision->ID}: Author {$revision_author->display_name} (ID: {$revision->post_author}), Date: {$revision->post_date}</p>\n";
                    }
                } else {
                    echo "<p style='color: orange;'>⚠ No revisions found for translated post</p>\n";
                    echo "<p>Note: WordPress may not create revisions for posts with 'pending' status</p>\n";
                }
            } else {
                echo "<p><strong>Post Type Supports Revisions:</strong> No</p>\n";
            }

            // Test what happens when we publish the post
            echo "<h4>Testing Post Status Change to Published:</h4>\n";
            $publish_result = wp_update_post([
                'ID' => $translated_post_id,
                'post_status' => 'publish'
            ]);

            if (is_wp_error($publish_result)) {
                echo "<p style='color: red;'>Failed to publish post: " . $publish_result->get_error_message() . "</p>\n";
            } else {
                echo "<p style='color: green;'>Post published successfully</p>\n";

                // Check revisions after publishing
                $published_revisions = wp_get_post_revisions($translated_post_id, ['posts_per_page' => 10]);
                echo "<p><strong>Revisions after publishing:</strong> " . count($published_revisions) . "</p>\n";

                if (!empty($published_revisions)) {
                    echo "<h5>Post Revisions After Publishing:</h5>\n";
                    foreach ($published_revisions as $revision) {
                        $revision_author = get_user_by('id', $revision->post_author);
                        echo "<p>- Revision ID {$revision->ID}: Author {$revision_author->display_name} (ID: {$revision->post_author}), Date: {$revision->post_date}</p>\n";
                    }
                }

                // Check the published post author
                $published_post = get_post($translated_post_id);
                $published_author = get_user_by('id', $published_post->post_author);
                echo "<p><strong>Published Post Author:</strong> {$published_author->display_name} (ID: {$published_post->post_author})</p>\n";

                if ($published_post->post_author == $original_post->post_author) {
                    echo "<p style='color: green;'>✓ Author preserved after publishing</p>\n";
                } else {
                    echo "<p style='color: red;'>✗ Author changed after publishing</p>\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>\n";
    }

    // Test direct wp_insert_post behavior
    echo "<h3>Testing Direct wp_insert_post Behavior:</h3>\n";

    $direct_post_data = [
        'post_title' => 'Direct Creation Test - ' . date('Y-m-d H:i:s'),
        'post_content' => 'Direct post content',
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => $original_post->post_author
    ];

    $direct_post_id = wp_insert_post($direct_post_data);

    if (is_wp_error($direct_post_id)) {
        echo "<p style='color: red;'>Direct post creation failed: " . $direct_post_id->get_error_message() . "</p>\n";
    } else {
        $direct_post = get_post($direct_post_id);
        $direct_author = get_user_by('id', $direct_post->post_author);

        echo "<p><strong>Direct Post ID:</strong> {$direct_post_id}</p>\n";
        echo "<p><strong>Direct Post Author:</strong> {$direct_author->display_name} (ID: {$direct_post->post_author})</p>\n";

        // Check revisions
        $direct_revisions = wp_get_post_revisions($direct_post_id, ['posts_per_page' => 10]);
        echo "<p><strong>Direct Post Revisions:</strong> " . count($direct_revisions) . "</p>\n";

        if (!empty($direct_revisions)) {
            echo "<h4>Direct Post Revisions:</h4>\n";
            foreach ($direct_revisions as $revision) {
                $revision_author = get_user_by('id', $revision->post_author);
                echo "<p>- Revision ID {$revision->ID}: Author {$revision_author->display_name} (ID: {$revision->post_author}), Date: {$revision->post_date}</p>\n";
            }
        }
    }

    // Cleanup
    echo "<h3>Cleanup:</h3>\n";
    if (isset($translated_post_id) && !is_wp_error($translated_post_id)) {
        wp_delete_post($translated_post_id, true);
        echo "<p>Deleted translated post {$translated_post_id}</p>\n";
    }
    if (isset($direct_post_id) && !is_wp_error($direct_post_id)) {
        wp_delete_post($direct_post_id, true);
        echo "<p>Deleted direct post {$direct_post_id}</p>\n";
    }
    wp_delete_post($original_post_id, true);
    echo "<p>Deleted original post {$original_post_id}</p>\n";
}

// Run tests if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'test-creation-revisions.php') {
    // Check if user is logged in and has proper permissions
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_die('You must be logged in with post editing capabilities to run this test.');
    }

    echo "<!DOCTYPE html><html><head><title>Post Creation Revisions Test</title></head><body>\n";
    echo "<h1>PolyTrans Post Creation and Revisions Test</h1>\n";

    test_post_creation_revisions();

    echo "</body></html>\n";
}
