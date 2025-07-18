<?php

/**
 * Test Articles Data Provider
 * 
 * Simple test script to verify the Articles Data Provider is working correctly.
 * Run this from the WordPress admin or via WP-CLI to test article fetching.
 */

// Test function to verify articles provider
function test_articles_data_provider()
{
    // Create provider instance
    $provider = new PolyTrans_Articles_Data_Provider();

    // Test context with different configurations
    $test_contexts = [
        [
            'name' => 'Default (20 articles)',
            'context' => ['target_language' => 'en']
        ],
        [
            'name' => 'Custom count (10 articles)',
            'context' => ['articles_count' => 10, 'target_language' => 'en']
        ],
        [
            'name' => 'Exclude specific post',
            'context' => ['articles_count' => 15, 'post_id' => 1, 'target_language' => 'en']
        ]
    ];

    echo "<h2>Articles Data Provider Test Results</h2>\n";

    foreach ($test_contexts as $test) {
        echo "<h3>Test: {$test['name']}</h3>\n";

        $variables = $provider->get_variables($test['context']);

        echo "<p><strong>Articles found:</strong> " . $variables['recent_articles_count'] . "</p>\n";

        if (!empty($variables['recent_articles'])) {
            echo "<h4>Recent Articles:</h4>\n<ul>\n";
            foreach (array_slice($variables['recent_articles'], 0, 5) as $article) {
                echo "<li><strong>{$article['title']}</strong> - {$article['url']}<br>";
                echo "<em>Categories:</em> " . implode(', ', array_column($article['categories'], 'name')) . "<br>";
                echo "<em>Word Count:</em> {$article['meta']['word_count']}</li>\n";
            }
            echo "</ul>\n";
        }

        echo "<h4>Formatted Summary (first 500 chars):</h4>\n";
        echo "<pre>" . substr($variables['recent_articles_summary'], 0, 500) . "...</pre>\n";

        echo "<hr>\n";
    }

    // Test variable documentation
    echo "<h3>Available Variables:</h3>\n<ul>\n";
    foreach ($provider->get_available_variables() as $var) {
        echo "<li><code>{$var}</code></li>\n";
    }
    echo "</ul>\n";

    // Test variable documentation
    echo "<h3>Variable Documentation:</h3>\n<dl>\n";
    foreach ($provider->get_variable_documentation() as $var => $doc) {
        echo "<dt><code>{$var}</code></dt>\n";
        echo "<dd>{$doc['description']}<br><em>Example:</em> <code>{$doc['example']}</code></dd>\n";
    }
    echo "</dl>\n";
}

// Only run if called directly or via WordPress admin
if (defined('ABSPATH')) {
    // Check if we're in WordPress admin and user has permission
    if (is_admin() && current_user_can('manage_options') && isset($_GET['test_articles_provider'])) {
        echo '<div class="wrap">';
        test_articles_data_provider();
        echo '</div>';
        return;
    }
}

// Show how to run the test
echo "<!-- To test the Articles Data Provider, add ?test_articles_provider=1 to any admin URL -->\n";
