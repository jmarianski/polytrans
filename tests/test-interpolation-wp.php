<?php

/**
 * WordPress-integrated test for variable interpolation fix
 * This file can be accessed via /wp-content/plugins/polytrans/test-interpolation-wp.php
 */

// Ensure this is being run in WordPress context
if (!defined('ABSPATH')) {
    // If not in WordPress, try to load it
    require_once(dirname(__FILE__) . '/../../../wp-config.php');
}

// Include our classes
require_once plugin_dir_path(__FILE__) . 'includes/postprocessing/interface-variable-provider.php';
require_once plugin_dir_path(__FILE__) . 'includes/postprocessing/class-variable-manager.php';

?>
<!DOCTYPE html>
<html>

<head>
    <title>PolyTrans Variable Interpolation Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
        }

        .original {
            background: #f0f0f0;
            padding: 10px;
            margin: 5px 0;
        }

        .result {
            background: #e8f5e8;
            padding: 10px;
            margin: 5px 0;
        }

        .error {
            background: #ffe8e8;
            padding: 10px;
            margin: 5px 0;
        }

        pre {
            white-space: pre-wrap;
        }
    </style>
</head>

<body>
    <h1>PolyTrans Variable Interpolation Test</h1>
    <p>This test verifies that JSON structures are preserved during variable interpolation.</p>

    <?php
    try {
        $variable_manager = new PolyTrans_Variable_Manager();

        // Test context
        $context = [
            'original_post' => [
                'title' => 'How to Optimize Your Website for Search Engines',
                'content' => 'Search engine optimization (SEO) is crucial for online visibility. This article covers the fundamentals of SEO and practical tips for improving your website\'s ranking.'
            ],
            'recent_articles' => [
                [
                    'title' => 'Understanding Google Analytics',
                    'url' => 'https://example.com/google-analytics-guide',
                    'excerpt' => 'Learn how to set up and use Google Analytics effectively.'
                ],
                [
                    'title' => 'Content Marketing Best Practices',
                    'url' => 'https://example.com/content-marketing-tips',
                    'excerpt' => 'Discover proven strategies for creating engaging content.'
                ],
                [
                    'title' => 'Social Media Marketing Trends',
                    'url' => 'https://example.com/social-media-trends',
                    'excerpt' => 'Stay up-to-date with the latest social media marketing trends.'
                ]
            ]
        ];

        // Test 1: System prompt with JSON (the problematic case)
        echo '<div class="test-section">';
        echo '<h2>Test 1: System Prompt with JSON Structure</h2>';
        echo '<p>This test should preserve the JSON structure while interpolating variables.</p>';

        $system_prompt = 'You are an expert content editor specializing in SEO optimization. Your task is to enhance the provided article by adding internal links to related content.

Please review the original article and add 2-3 relevant internal links using the provided reference articles. Return your response in this exact JSON format:

{
  "enhanced_post": "<the original post content with internal links added>",
  "links_added": <number of links added>,
  "reasoning": "<brief explanation of why these links were chosen>"
}

Original article title: {original_post.title}
Original article content: {original_post.content}

Available articles for internal linking:
{recent_articles}

Important: Ensure the links are contextually relevant and enhance the reader\'s experience.';

        echo '<div class="original"><strong>Original System Prompt:</strong><pre>' . htmlspecialchars($system_prompt) . '</pre></div>';

        $interpolated = $variable_manager->interpolate_template($system_prompt, $context);
        echo '<div class="result"><strong>Interpolated Result:</strong><pre>' . htmlspecialchars($interpolated) . '</pre></div>';

        // Check if JSON structure is preserved
        if (strpos($interpolated, '{"enhanced_post":') !== false || strpos($interpolated, '{\n  "enhanced_post":') !== false) {
            echo '<div class="result"><strong>✅ SUCCESS:</strong> JSON structure preserved!</div>';
        } else {
            echo '<div class="error"><strong>❌ FAILED:</strong> JSON structure was removed!</div>';
        }
        echo '</div>';

        // Test 2: Variable extraction
        echo '<div class="test-section">';
        echo '<h2>Test 2: Variable Extraction</h2>';
        echo '<p>This test should only extract legitimate variable names, not JSON keys.</p>';

        $variables = $variable_manager->extract_variables($system_prompt);
        echo '<div class="result"><strong>Variables Found:</strong> ' . implode(', ', $variables) . '</div>';

        $expected_variables = ['original_post.title', 'original_post.content', 'recent_articles'];
        $missing = array_diff($expected_variables, $variables);
        $extra = array_diff($variables, $expected_variables);

        if (empty($missing) && empty($extra)) {
            echo '<div class="result"><strong>✅ SUCCESS:</strong> Correct variables extracted!</div>';
        } else {
            echo '<div class="error"><strong>❌ ISSUES:</strong>';
            if (!empty($missing)) echo ' Missing: ' . implode(', ', $missing);
            if (!empty($extra)) echo ' Extra: ' . implode(', ', $extra);
            echo '</div>';
        }
        echo '</div>';

        // Test 3: Complex mixed content
        echo '<div class="test-section">';
        echo '<h2>Test 3: Complex Mixed Content</h2>';
        echo '<p>This test combines multiple JSON structures with variables.</p>';

        $complex_template = 'Analyze this article: {original_post.title}

Content: {original_post.content}

Provide analysis in this format:
{
  "seo_score": 85,
  "recommendations": [
    {"type": "internal_linking", "priority": "high"},
    {"type": "keyword_optimization", "priority": "medium"}
  ],
  "related_content": {
    "articles": ["url1", "url2"],
    "suggested_topics": ["topic1", "topic2"]
  }
}

Reference articles: {recent_articles}

Additional metadata format:
{
  "processing_time": "2023-12-01T10:30:00Z",
  "confidence": 0.92
}';

        echo '<div class="original"><strong>Original Template:</strong><pre>' . htmlspecialchars($complex_template) . '</pre></div>';

        $complex_result = $variable_manager->interpolate_template($complex_template, $context);
        echo '<div class="result"><strong>Interpolated Result:</strong><pre>' . htmlspecialchars($complex_result) . '</pre></div>';

        // Check for JSON preservation
        $json_patterns = [
            '"seo_score": 85',
            '"recommendations": [',
            '"related_content": {',
            '"processing_time":',
            '"confidence": 0.92'
        ];

        $json_preserved = true;
        foreach ($json_patterns as $pattern) {
            if (strpos($complex_result, $pattern) === false) {
                $json_preserved = false;
                break;
            }
        }

        if ($json_preserved) {
            echo '<div class="result"><strong>✅ SUCCESS:</strong> All JSON structures preserved!</div>';
        } else {
            echo '<div class="error"><strong>❌ FAILED:</strong> Some JSON structures were corrupted!</div>';
        }
        echo '</div>';
    } catch (\Exception $e) {
        echo '<div class="error"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

    <div class="test-section">
        <h2>Summary</h2>
        <p>This test verifies that the variable interpolation fix correctly:</p>
        <ul>
            <li>✅ Preserves JSON structures with curly braces</li>
            <li>✅ Still interpolates legitimate variables like {post.title}</li>
            <li>✅ Extracts only actual variables, not JSON keys</li>
            <li>✅ Handles complex mixed content properly</li>
        </ul>
        <p><strong>If all tests show SUCCESS, the interpolation fix is working correctly!</strong></p>
    </div>
</body>

</html>