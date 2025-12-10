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

-- Get the ID of the newly created assistant
SELECT LAST_INSERT_ID() as assistant_id, 
       'Translation EN→PL assistant created successfully!' as message;

