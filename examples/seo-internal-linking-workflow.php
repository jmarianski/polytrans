<?php
/**
 * Example SEO Internal Linking Workflow Template
 * 
 * This file demonstrates how to create a workflow for automatic
 * SEO internal linking using the Articles Data Provider.
 */

// Example workflow configuration
$seo_internal_linking_workflow = [
    'id' => 'seo_internal_linking',
    'name' => 'SEO Internal Linking Assistant',
    'description' => 'Automatically suggests relevant internal links for new content based on existing published articles',
    'language' => 'en',
    'enabled' => true,
    'triggers' => [
        'on_translation_complete' => true,
        'manual_only' => false,
        'conditions' => []
    ],
    'steps' => [
        [
            'id' => 'step_1',
            'name' => 'SEO Internal Link Analysis',
            'type' => 'ai_assistant',
            'enabled' => true,
            'system_prompt' => 'You are an SEO expert specializing in internal linking strategies. Your task is to analyze content and recommend relevant internal links from existing articles.

You always respond in JSON format with this exact structure:
{
  "internal_links": [
    {
      "anchor_text": "suggested anchor text",
      "url": "article_url", 
      "reason": "why this link is relevant",
      "placement_suggestion": "where in the content to place this link"
    }
  ],
  "seo_analysis": {
    "content_topics": ["topic1", "topic2"],
    "linking_opportunities": 5,
    "strategic_notes": "additional SEO observations"
  }
}

Guidelines:
- Recommend 2-5 most relevant internal links
- Focus on semantic relevance, not just keyword matching
- Consider user journey and content depth
- Suggest natural anchor text that flows with the content
- Avoid over-optimization',
            'user_message' => 'Current Article Title: {title}
Current Article Content: {content}

Available Articles for Internal Linking:
{recent_articles_summary}

Please analyze the current article and recommend the most relevant internal links from the available articles. Focus on:

1. Semantic relevance between topics
2. Natural linking opportunities within the content  
3. Strategic SEO value for user navigation
4. Contextual fit for the anchor text

Consider the article categories, tags, and content themes when making recommendations.',
            'expected_format' => 'json',
            'temperature' => 0.3,
            'max_tokens' => 1000,
            'output_actions' => [
                [
                    'type' => 'update_post_meta',
                    'source_variable' => 'data',
                    'target' => 'seo_internal_links_suggestions'
                ]
            ]
        ]
    ]
];

/**
 * Usage Instructions:
 * 
 * 1. Create this workflow in the PolyTrans admin
 * 2. Configure the number of articles to analyze (default: 20)
 * 3. The workflow will automatically run after translations complete
 * 4. Results are saved to post meta 'seo_internal_links_suggestions'
 * 5. You can then use this data to manually or automatically insert links
 * 
 * Variables Available:
 * - {title} - Current post title
 * - {content} - Current post content  
 * - {recent_articles_summary} - Formatted list of recent articles
 * - {recent_articles_count} - Number of articles retrieved
 * - {recent_articles.0.title} - Title of first recent article
 * - {recent_articles.0.url} - URL of first recent article
 * 
 * Configuration Options:
 * - articles_count: Number of recent articles to include (5-50)
 * - article_post_types: Array of post types to include ['post', 'page']
 * - language: Language code for multilingual sites
 */

/**
 * Example Output Format:
 * 
 * The AI will return JSON like this:
 * {
 *   "internal_links": [
 *     {
 *       "anchor_text": "artificial intelligence in healthcare",
 *       "url": "https://example.com/ai-healthcare-revolution",
 *       "reason": "Highly relevant to the main topic, provides deeper context on AI applications",
 *       "placement_suggestion": "In the second paragraph where AI applications are first mentioned"
 *     },
 *     {
 *       "anchor_text": "patient data privacy concerns", 
 *       "url": "https://example.com/healthcare-data-privacy",
 *       "reason": "Addresses potential concerns readers might have about the topic",
 *       "placement_suggestion": "In the section discussing data usage and analysis"
 *     }
 *   ],
 *   "seo_analysis": {
 *     "content_topics": ["artificial intelligence", "healthcare technology", "medical diagnosis"],
 *     "linking_opportunities": 4,
 *     "strategic_notes": "Strong potential for hub page strategy around healthcare AI topic cluster"
 *   }
 * }
 */

/**
 * Advanced Usage - Custom Article Filtering:
 * 
 * You can customize the article selection by modifying the test context:
 * 
 * testContext.articles_count = 30;  // Get more articles
 * testContext.article_post_types = ['post', 'case-study'];  // Include custom post types
 * testContext.language = 'en';  // Specify language for multilingual sites
 */

/**
 * Integration with Output Actions:
 * 
 * The workflow saves suggestions to post meta, which you can then use in:
 * 
 * 1. Admin dashboard - Display suggestions in meta box
 * 2. Frontend - Automatically insert links during content display
 * 3. Editorial workflow - Present suggestions to editors for review
 * 4. Analytics - Track which suggestions are most effective
 */
?>
