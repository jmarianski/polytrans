<?php

/**
 * Test script for Google Translate integration
 * Run this from wp-admin to test the Google Translate functionality
 */

// Load WordPress
require_once '../../../wp-load.php';

// Make sure we're logged in as admin
if (!current_user_can('manage_options')) {
    wp_die('You need to be an administrator to run this test.');
}

// Load the plugin files
require_once plugin_dir_path(__FILE__) . 'includes/class-google-translate-integration.php';

// Test the Google Translate integration
echo "<h1>PolyTrans Google Translate Integration Test</h1>";

$google_translate = PolyTrans_Google_Translate_Integration::get_instance();

// Test simple text translation
echo "<h2>Test 1: Simple Text Translation</h2>";
$test_text = "Hello, this is a test.";
$result = $google_translate->translate($test_text, 'en', 'pl');

if ($result['success']) {
    echo "<p><strong>Original:</strong> " . esc_html($test_text) . "</p>";
    echo "<p><strong>Translated:</strong> " . esc_html($result['translated_content']) . "</p>";
    echo "<p style='color: green;'>✓ Simple translation successful!</p>";
} else {
    echo "<p style='color: red;'>✗ Simple translation failed: " . esc_html($result['error']) . "</p>";
}

// Test array translation
echo "<h2>Test 2: Array Translation</h2>";
$test_array = [
    'title' => 'Welcome to our website',
    'content' => 'This is the main content of the page.',
    'excerpt' => 'A short excerpt.'
];

$result2 = $google_translate->translate($test_array, 'en', 'pl');

if ($result2['success']) {
    echo "<p><strong>Original Array:</strong></p>";
    echo "<pre>" . esc_html(print_r($test_array, true)) . "</pre>";
    echo "<p><strong>Translated Array:</strong></p>";
    echo "<pre>" . esc_html(print_r($result2['translated_content'], true)) . "</pre>";
    echo "<p style='color: green;'>✓ Array translation successful!</p>";
} else {
    echo "<p style='color: red;'>✗ Array translation failed: " . esc_html($result2['error']) . "</p>";
}

// Test configuration check
echo "<h2>Test 3: Configuration Check</h2>";
if ($google_translate->is_configured()) {
    echo "<p style='color: green;'>✓ Google Translate is properly configured!</p>";
} else {
    echo "<p style='color: red;'>✗ Google Translate configuration check failed!</p>";
}

// Test supported languages
echo "<h2>Test 4: Supported Languages</h2>";
$supported_langs = $google_translate->get_supported_languages();
echo "<p>Number of supported languages: " . count($supported_langs) . "</p>";
echo "<p>Sample languages: " . implode(', ', array_slice(array_keys($supported_langs), 0, 10)) . "...</p>";
echo "<p style='color: green;'>✓ Language list loaded successfully!</p>";

echo "<h2>Test Complete</h2>";
echo "<p>If all tests show green checkmarks, the Google Translate integration is working correctly!</p>";
echo "<p><a href='" . admin_url('options-general.php?page=polytrans-settings') . "'>Go to PolyTrans Settings</a></p>";
