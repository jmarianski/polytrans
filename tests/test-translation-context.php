<?php
/**
 * Test script for translation handler with context articles
 * 
 * This script tests the new context articles feature in the external translation process.
 * It simulates sending a translation request with context articles from recent posts.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug: Test translation payload with context articles
function test_translation_payload_with_context() {
    echo "<h2>Testing Translation Payload with Context Articles</h2>";
    
    // Test if Articles Data Provider is available
    if (!class_exists('PolyTrans_Articles_Data_Provider')) {
        echo "<p style='color: red;'>❌ PolyTrans_Articles_Data_Provider class not found</p>";
        return;
    }
    
    echo "<p style='color: green;'>✅ PolyTrans_Articles_Data_Provider class found</p>";
    
    // Test getting recent articles for different languages
    $test_languages = ['en', 'es', 'fr', 'de'];
    
    foreach ($test_languages as $lang) {
        echo "<h3>Testing context articles for language: $lang</h3>";
        
        $articles_provider = new PolyTrans_Articles_Data_Provider();
        
        // Prepare context
        $context = [
            'articles_count' => 5, // Use smaller count for testing
            'post_id' => null, // No exclusion for test
            'article_post_types' => ['post'],
            'language' => $lang
        ];
        
        $variables = $articles_provider->get_variables($context);
        $recent_articles = $variables['recent_articles'] ?? [];
        
        echo "<p><strong>Found " . count($recent_articles) . " articles in $lang</strong></p>";
        
        if (!empty($recent_articles)) {
            echo "<ul>";
            foreach (array_slice($recent_articles, 0, 3) as $article) { // Show first 3 only
                echo "<li>";
                echo "<strong>ID:</strong> " . esc_html($article['id']) . " - ";
                echo "<strong>Title:</strong> " . esc_html($article['title']) . " - ";
                echo "<strong>Date:</strong> " . esc_html($article['date']) . " - ";
                echo "<strong>URL:</strong> " . esc_html($article['url']);
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p><em>No articles found in $lang</em></p>";
        }
        
        echo "<hr>";
    }
    
    // Test payload structure
    echo "<h3>Testing Payload Structure</h3>";
    
    $mock_post = (object) [
        'ID' => 123,
        'post_title' => 'Test Article Title',
        'post_content' => 'This is test content for the article.',
        'post_excerpt' => 'This is a test excerpt.'
    ];
    
    $mock_meta = ['test_meta' => 'test_value'];
    
    // Simulate context articles
    $context_articles = [];
    if (count($recent_articles) > 0) {
        foreach (array_slice($recent_articles, 0, 3) as $article) {
            $context_articles[] = [
                'id' => $article['id'],
                'title' => $article['title'],
                'content' => substr($article['content'], 0, 200) . '...', // Truncate for display
                'excerpt' => $article['excerpt'],
                'date' => $article['date'],
                'url' => $article['url'],
                'categories' => $article['categories'],
                'tags' => $article['tags']
            ];
        }
    }
    
    // Mock payload structure
    $payload = [
        'source_language' => 'en',
        'target_language' => 'es',
        'original_post_id' => 123,
        'target_endpoint' => 'https://example.com/translation/receive',
        'toTranslate' => [
            'title' => $mock_post->post_title,
            'content' => $mock_post->post_content,
            'excerpt' => $mock_post->post_excerpt,
            'meta' => $mock_meta
        ],
        'context_articles' => $context_articles
    ];
    
    echo "<h4>Mock Payload Structure:</h4>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
    echo esc_html(json_encode($payload, JSON_PRETTY_PRINT));
    echo "</pre>";
    
    echo "<p><strong>Payload includes:</strong></p>";
    echo "<ul>";
    echo "<li>Source article data (title, content, excerpt, meta)</li>";
    echo "<li>" . count($context_articles) . " context articles in target language</li>";
    echo "<li>Translation endpoints and language codes</li>";
    echo "</ul>";
}

// Run the test
test_translation_payload_with_context();
?>
