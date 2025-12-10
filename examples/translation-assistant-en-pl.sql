-- Translation Assistant: English → Polish
-- Simple, production-ready assistant for translating WordPress posts
-- Uses schema-based JSON parsing for robust output handling

INSERT INTO wp_polytrans_assistants (
    name,
    description,
    provider,
    status,
    system_prompt,
    user_message_template,
    api_parameters,
    expected_format,
    expected_output_schema,
    output_variables,
    created_at,
    updated_at,
    created_by
) VALUES (
    'Translation EN→PL',
    'Translates WordPress posts from English to Polish with SEO optimization',
    'openai',
    'active',
    -- System Instructions
    'You are a professional translator specializing in English to Polish translation for WordPress content.

Your task is to translate blog posts while:
- Maintaining natural, fluent Polish language
- Preserving all HTML tags, markdown formatting, and special characters
- Keeping the tone and style of the original
- Optimizing for Polish SEO best practices
- Preserving emojis, line breaks, and formatting

Always respond with valid JSON containing the translated content.',
    
    -- User Message Template (Twig)
    'Translate the following WordPress post from English to Polish:

{
  "title": "{{ translated.title }}",
  "content": "{{ translated.content }}",
  "excerpt": "{{ translated.excerpt }}",
  "meta": {
    {% for key, value in translated.meta %}
    "{{ key }}": "{{ value }}"{% if not loop.last %},{% endif %}
    {% endfor %}
  }
}

Return the translated content in the same JSON structure.',
    
    -- API Parameters (JSON string)
    '{"model":"gpt-4o","temperature":0.3,"max_tokens":4000}',
    
    -- Expected Format
    'json',
    
    -- Expected Output Schema (JSON string) - with auto-mapping targets for ALL SEO fields
    -- This schema automatically maps AI response fields to WordPress post fields
    -- No manual Output Actions needed! The system will:
    --   - Update post.title, post.content, post.excerpt automatically
    --   - Update ALL RankMath and Yoast SEO meta fields automatically
    -- Format: {"field": {"type": "string", "target": "post.field", "required": true}}
    '{"title":{"type":"string","target":"post.title","required":true},"content":{"type":"string","target":"post.content","required":true},"excerpt":{"type":"string","target":"post.excerpt"},"meta":{"rank_math_title":{"type":"string","target":"meta.rank_math_title"},"rank_math_description":{"type":"string","target":"meta.rank_math_description"},"rank_math_facebook_title":{"type":"string","target":"meta.rank_math_facebook_title"},"rank_math_facebook_description":{"type":"string","target":"meta.rank_math_facebook_description"},"rank_math_twitter_title":{"type":"string","target":"meta.rank_math_twitter_title"},"rank_math_twitter_description":{"type":"string","target":"meta.rank_math_twitter_description"},"rank_math_focus_keyword":{"type":"string","target":"meta.rank_math_focus_keyword"},"_yoast_wpseo_title":{"type":"string","target":"meta._yoast_wpseo_title"},"_yoast_wpseo_metadesc":{"type":"string","target":"meta._yoast_wpseo_metadesc"},"_yoast_wpseo_focuskw":{"type":"string","target":"meta._yoast_wpseo_focuskw"},"_yoast_wpseo_opengraph-title":{"type":"string","target":"meta._yoast_wpseo_opengraph-title"},"_yoast_wpseo_opengraph-description":{"type":"string","target":"meta._yoast_wpseo_opengraph-description"},"_yoast_wpseo_twitter-title":{"type":"string","target":"meta._yoast_wpseo_twitter-title"},"_yoast_wpseo_twitter-description":{"type":"string","target":"meta._yoast_wpseo_twitter-description"}}}',
    
    -- Output Variables (null for now)
    NULL,
    
    -- Timestamps
    NOW(),
    NOW(),
    
    -- Created by (1 = admin, adjust if needed)
    1
);

-- Note: The assistant ID will be shown by the installation script

