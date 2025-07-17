<?php

/**
 * Test script to verify that JSON structures are preserved during variable interpolation
 */

echo "Starting interpolation test...\n";

// Include WordPress functions (minimal simulation)
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content)
    {
        return $content;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($content)
    {
        return htmlspecialchars($content, ENT_QUOTES);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return $url;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($content)
    {
        return trim($content);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($content)
    {
        return trim($content);
    }
}

echo "WordPress functions simulated\n";

// Include the interface first
require_once __DIR__ . '/includes/postprocessing/interface-variable-provider.php';

echo "Interface included\n";

// Include the Variable Manager
require_once __DIR__ . '/includes/postprocessing/class-variable-manager.php';

echo "Variable Manager included\n";

// Test cases
$variable_manager = new PolyTrans_Variable_Manager();

echo "Variable Manager instantiated\n";

$context = [
    'original_post' => [
        'title' => 'Test Article',
        'content' => 'This is the original content of the article.'
    ]
];

echo "Context prepared\n";

// Test: System prompt with JSON structure
$system_prompt_with_json = 'Return in JSON format: {"post": "<enhanced content>"}. The content is: {original_post.content}';

echo "=== Testing Variable Interpolation Fix ===\n";
echo "ORIGINAL:\n" . $system_prompt_with_json . "\n\n";

$interpolated = $variable_manager->interpolate_template($system_prompt_with_json, $context);
echo "INTERPOLATED:\n" . $interpolated . "\n\n";

echo "=== Test Complete ===\n";
