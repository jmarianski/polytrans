<?php

/**
 * Direct test of interpolation regex pattern
 */

echo "=== Testing Interpolation Regex Pattern ===\n\n";

// Test the new pattern
$pattern = '/\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}/';

$test_strings = [
    // Should match (valid variables)
    '{original_post.content}',
    '{recent_articles}',
    '{meta.custom_field}',
    '{some_variable}',
    '{post.title}',

    // Should NOT match (JSON structures)
    '{"post": "content"}',
    '{"processed_content": "<enhanced>"}',
    '{"key": "value", "number": 123}',
    '{"array": ["item1", "item2"]}',
];

echo "1. Testing what patterns match:\n";
foreach ($test_strings as $test) {
    $matches = preg_match($pattern, $test);
    echo "  '$test' -> " . ($matches ? "MATCHES" : "no match") . "\n";
}

echo "\n2. Testing full interpolation logic:\n";

$template = 'Process this: {original_post.content}

Return in JSON format:
{
  "post": "<enhanced content>",
  "links_added": 3
}

Context: {recent_articles}';

echo "TEMPLATE:\n$template\n\n";

// Simulate the context
$context = [
    'original_post' => [
        'content' => 'This is the test content.'
    ],
    'recent_articles' => '[{"title": "Article 1"}, {"title": "Article 2"}]'
];

// Simulate interpolation
$result = preg_replace_callback($pattern, function ($matches) use ($context) {
    $variable_path = $matches[1];
    echo "  Found variable: '$variable_path'\n";

    // Simple lookup for testing
    if ($variable_path === 'original_post.content') {
        return $context['original_post']['content'];
    } elseif ($variable_path === 'recent_articles') {
        return $context['recent_articles'];
    }
    return '';
}, $template);

echo "\nINTERPOLATED RESULT:\n$result\n";

echo "\n=== Test Complete ===\n";
