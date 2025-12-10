-- Translation Assistant Template
-- Replace {SOURCE_LANG}, {TARGET_LANG}, {SOURCE_NAME}, {TARGET_NAME} with your language pair
-- Examples: EN→PL, EN→FR, PL→EN, etc.

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
    'Translation {SOURCE_LANG}→{TARGET_LANG}',
    'Translates WordPress posts from {SOURCE_NAME} to {TARGET_NAME} with SEO optimization',
    'openai',
    'active',
    -- System Instructions
    'You are a professional translator specializing in {SOURCE_NAME} to {TARGET_NAME} translation for WordPress content.

Your task is to translate blog posts while:
- Maintaining natural, fluent {TARGET_NAME} language
- Preserving all HTML tags, markdown formatting, and special characters
- Keeping the tone and style of the original
- Optimizing for {TARGET_NAME} SEO best practices
- Preserving emojis, line breaks, and formatting
- Adapting cultural references when necessary

Always respond with valid JSON containing the translated content.',
    
    -- User Message Template (Twig)
    'Translate the following WordPress post from {SOURCE_NAME} to {TARGET_NAME}:

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
    -- gpt-4o for best quality, temperature 0.3 for consistency, 10000 tokens for long posts
    '{"model":"gpt-4o","temperature":0.3,"max_tokens":10000}',
    
    -- Expected Format
    'json',
    
    -- Expected Output Schema (JSON string)
    '{"title":"string","content":"string","excerpt":"string","meta":"object"}',
    
    -- Output Variables (null for now)
    NULL,
    
    -- Timestamps
    NOW(),
    NOW(),
    
    -- Created by (1 = admin, adjust if needed)
    1
);

-- Examples of language pairs:
-- EN→PL: English → Polish
-- EN→FR: English → French  
-- EN→DE: English → German
-- EN→ES: English → Spanish
-- PL→EN: Polish → English
-- FR→EN: French → English

-- To create multiple assistants, run this query multiple times with different values.
-- Note: The assistant ID will be shown by the installation script

