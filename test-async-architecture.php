<?php
/**
 * Test script to demonstrate async translation workflow
 * 
 * This file shows how the new async architecture works.
 * You can run this in WordPress admin or via WP-CLI to test.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

/**
 * Test function to demonstrate async translation
 */
function polytrans_test_async_translation() {
    // Example: Test local async translation
    echo "<h2>Testing PolyTrans Async Translation</h2>\n";
    
    // Simulate settings for local async translation
    $settings = [
        'translation_provider' => 'google',
        'translation_receiver_secret' => 'test-secret-123',
        'translation_receiver_secret_method' => 'header_bearer',
        // No translation_endpoint - will use local endpoints
    ];
    
    echo "<h3>Settings:</h3>\n";
    echo "<pre>" . print_r($settings, true) . "</pre>\n";
    
    // Test the endpoint URLs that would be generated
    $local_translate_endpoint = home_url('/wp-json/polytrans/v1/translation/translate');
    $local_receiver_endpoint = home_url('/wp-json/polytrans/v1/translation/receive-post');
    
    echo "<h3>Generated Endpoints:</h3>\n";
    echo "<strong>Translate Endpoint:</strong> " . esc_html($local_translate_endpoint) . "<br>\n";
    echo "<strong>Receiver Endpoint:</strong> " . esc_html($local_receiver_endpoint) . "<br>\n";
    
    // Test payload that would be sent
    $test_payload = [
        'source_language' => 'pl',
        'target_language' => 'en',
        'original_post_id' => 123,
        'target_endpoint' => $local_receiver_endpoint,
        'toTranslate' => [
            'title' => 'Przykładowy tytuł',
            'content' => 'To jest przykładowa treść artykułu do przetłumaczenia.',
            'excerpt' => 'Krótki opis artykułu',
            'meta' => [
                'rank_math_title' => 'SEO tytuł artykułu'
            ]
        ]
    ];
    
    echo "<h3>Test Payload:</h3>\n";
    echo "<pre>" . print_r($test_payload, true) . "</pre>\n";
    
    echo "<h3>Workflow:</h3>\n";
    echo "<ol>\n";
    echo "<li>User clicks 'Translate' button</li>\n";
    echo "<li>AJAX request → Translation Handler</li>\n";
    echo "<li>Handler sends async HTTP POST to: <code>" . esc_html($local_translate_endpoint) . "</code></li>\n";
    echo "<li>Translation Extension receives request and processes with Google/OpenAI</li>\n";
    echo "<li>Extension sends result to: <code>" . esc_html($local_receiver_endpoint) . "</code></li>\n";
    echo "<li>Receiver creates translated post and sends notifications</li>\n";
    echo "</ol>\n";
    
    echo "<h3>Benefits:</h3>\n";
    echo "<ul>\n";
    echo "<li>✅ Non-blocking UI (async processing)</li>\n";
    echo "<li>✅ Same workflow for local and remote servers</li>\n";
    echo "<li>✅ Better error handling and logging</li>\n";
    echo "<li>✅ Scalable architecture</li>\n";
    echo "</ul>\n";
}

// Only run if accessed via admin or WP-CLI
if (is_admin() && isset($_GET['polytrans_test'])) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-info">';
        polytrans_test_async_translation();
        echo '</div>';
    });
}
