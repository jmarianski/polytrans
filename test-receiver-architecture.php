<?php

/**
 * Advanced test script for PolyTrans receiver architecture
 * Tests the complete translation pipeline including Google Translate and the receiver system
 */

// Load WordPress
require_once '../../../wp-load.php';

// Make sure we're logged in as admin
if (!current_user_can('manage_options')) {
    wp_die('You need to be an administrator to run this test.');
}

echo "<h1>PolyTrans Advanced Receiver Architecture Test</h1>";

// Test 1: Receiver extension initialization
echo "<h2>Test 1: Receiver Extension Initialization</h2>";
try {
    $receiver = new PolyTrans_Translation_Receiver_Extension();
    echo "<p style='color: green;'>✓ Receiver extension created successfully!</p>";

    $coordinator = $receiver->get_coordinator();
    echo "<p style='color: green;'>✓ Translation coordinator accessible!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Receiver extension failed: " . esc_html($e->getMessage()) . "</p>";
}

// Test 2: Manager class availability
echo "<h2>Test 2: Manager Classes</h2>";
$managers = [
    'PolyTrans_Translation_Request_Validator',
    'PolyTrans_Translation_Post_Creator',
    'PolyTrans_Translation_Metadata_Manager',
    'PolyTrans_Translation_Taxonomy_Manager',
    'PolyTrans_Translation_Language_Manager',
    'PolyTrans_Translation_Notification_Manager',
    'PolyTrans_Translation_Status_Manager',
    'PolyTrans_Translation_Security_Manager'
];

foreach ($managers as $manager_class) {
    if (class_exists($manager_class)) {
        echo "<p style='color: green;'>✓ $manager_class loaded</p>";
    } else {
        echo "<p style='color: red;'>✗ $manager_class not found</p>";
    }
}

// Test 3: Create a test post for translation
echo "<h2>Test 3: End-to-End Translation Test</h2>";

// Create a test post
$test_post_id = wp_insert_post([
    'post_title' => 'Test Post for Translation',
    'post_content' => 'This is a test post that will be translated from English to Polish.',
    'post_status' => 'publish',
    'post_type' => 'post'
]);

if ($test_post_id) {
    echo "<p style='color: green;'>✓ Created test post ID: $test_post_id</p>";

    // Test the translation coordinator directly
    try {
        $coordinator = new PolyTrans_Translation_Coordinator();

        // Simulate translated content (normally this would come from Google Translate)
        $translated_content = [
            'title' => 'Post testowy do tłumaczenia',
            'content' => 'To jest post testowy, który zostanie przetłumaczony z angielskiego na polski.',
            'excerpt' => ''
        ];

        $translation_params = [
            'source_language' => 'en',
            'target_language' => 'pl',
            'original_post_id' => $test_post_id,
            'translated' => $translated_content
        ];

        $result = $coordinator->process_translation($translation_params);

        if ($result['success']) {
            echo "<p style='color: green;'>✓ Translation processed successfully!</p>";
            echo "<p><strong>Created post ID:</strong> {$result['created_post_id']}</p>";
            echo "<p><strong>Status:</strong> {$result['status']}</p>";

            // Check the created post
            $translated_post = get_post($result['created_post_id']);
            if ($translated_post) {
                echo "<p><strong>Translated title:</strong> " . esc_html($translated_post->post_title) . "</p>";
                echo "<p style='color: green;'>✓ Translated post created successfully!</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Translation failed: " . esc_html($result['error']) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Translation test failed: " . esc_html($e->getMessage()) . "</p>";
    }

    // Clean up test post
    wp_delete_post($test_post_id, true);
    if (isset($result['created_post_id'])) {
        wp_delete_post($result['created_post_id'], true);
    }
    echo "<p style='color: blue;'>ℹ Test posts cleaned up</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to create test post</p>";
}

// Test 4: Google Translate Integration with Receiver
echo "<h2>Test 4: Google Translate + Receiver Integration</h2>";

try {
    $google_translate = PolyTrans_Google_Translate_Integration::get_instance();

    // Test a simple translation
    $test_text = "Hello world, this is a test.";
    $translation_result = $google_translate->translate($test_text, 'en', 'pl');

    if ($translation_result['success']) {
        echo "<p style='color: green;'>✓ Google Translate working!</p>";
        echo "<p><strong>Original:</strong> " . esc_html($test_text) . "</p>";
        echo "<p><strong>Translated:</strong> " . esc_html($translation_result['translated_content']) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Google Translate failed: " . esc_html($translation_result['error']) . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Google Translate test failed: " . esc_html($e->getMessage()) . "</p>";
}

// Test 5: REST API endpoint availability
echo "<h2>Test 5: REST API Endpoints</h2>";

$endpoints_to_check = [
    '/polytrans/v1/translation/receive-post'
];

foreach ($endpoints_to_check as $endpoint) {
    $server = rest_get_server();
    $routes = $server->get_routes();

    if (isset($routes[$endpoint])) {
        echo "<p style='color: green;'>✓ REST endpoint registered: $endpoint</p>";
    } else {
        echo "<p style='color: red;'>✗ REST endpoint missing: $endpoint</p>";
    }
}

echo "<h2>Test Complete</h2>";
echo "<p>The PolyTrans plugin now includes a sophisticated receiver architecture migrated from transinfo-wr-docker!</p>";
echo "<p><strong>Key Features:</strong></p>";
echo "<ul>";
echo "<li>Modular manager classes for different aspects of translation processing</li>";
echo "<li>Comprehensive request validation and error handling</li>";
echo "<li>Robust post creation with metadata and taxonomy management</li>";
echo "<li>Advanced security with IP restrictions and authentication</li>";
echo "<li>Polylang integration for proper language relationships</li>";
echo "<li>Email notification system for review workflow</li>";
echo "<li>Detailed status tracking and logging</li>";
echo "</ul>";

echo "<p><a href='" . admin_url('options-general.php?page=polytrans-settings') . "'>Go to PolyTrans Settings</a></p>";
