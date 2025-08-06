# Plain Text Workflow Guide

## Overview

The PolyTrans postprocessing workflow system fully supports plain text AI responses with automatic response detection. This guide explains how to use it effectively.

## How It Works

### 1. AI Assistant Step Configuration

When you want to send whole post content to AI and receive a plain text response:

```php
[
    'type' => 'ai_assistant',
    'name' => 'Content Rewriter',
    'system_prompt' => 'You are a skilled content editor. Rewrite the provided content to make it more engaging while preserving the original meaning. Return only the rewritten content with no additional text or formatting.',
    'user_message' => 'Rewrite this content:\n\n{content}',
    'expected_format' => 'text', // KEY: Set to 'text' for plain text responses
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7
]
```

### 2. Output Actions Configuration

For plain text responses, you can leave the source variable empty:

```php
'output_actions' => [
    [
        'type' => 'update_post_content',
        'source_variable' => '', // KEY: Leave empty for auto-detection
        'target' => ''
    ],
    [
        'type' => 'update_post_meta',
        'source_variable' => '', // KEY: Leave empty for auto-detection 
        'target' => 'ai_rewritten_content'
    ]
]
```

## Auto-Detection Logic

When `source_variable` is empty, the system automatically detects the main response using this priority order:

1. **`ai_response`** - Primary variable for plain text format
2. **`content`** - Alternative content variable
3. **`assistant_response`** - For predefined assistant responses
4. **Single variable** - If only one variable exists, use it
5. **First available** - Use the first available value as fallback

## UI Usage

### In the Workflow Editor:

1. **Expected Response Format**: Choose "Plain Text"
   - Helper text: "For complete content (like rewritten posts) - leave Source Variable empty in output actions"

2. **Source Variable** (in output actions): Leave empty
   - Helper text: "Leave empty to automatically use the main AI response (recommended for plain text responses)"

3. **Pro Tip**: The UI shows: "For plain text responses (like rewritten content), leave the 'Source Variable' field empty and the system will automatically use the AI's complete response."

## Example Workflow

Here's a complete example of a content rewriting workflow:

```json
{
    "id": "content_rewriter",
    "name": "AI Content Rewriter",
    "description": "Rewrites post content using AI to make it more engaging",
    "language": "en",
    "enabled": true,
    "triggers": {
        "on_translation_complete": false,
        "manual_only": true
    },
    "steps": [
        {
            "id": "rewrite_step",
            "name": "Content Rewriter",
            "type": "ai_assistant",
            "enabled": true,
            "system_prompt": "You are a skilled content editor. Rewrite the provided content to make it more engaging while preserving the original meaning. Return only the rewritten content with no additional text or formatting.",
            "user_message": "Rewrite this content:\n\n{content}",
            "expected_format": "text",
            "model": "gpt-4o-mini",
            "temperature": 0.7,
            "output_actions": [
                {
                    "type": "update_post_content",
                    "source_variable": "",
                    "target": ""
                },
                {
                    "type": "update_post_meta",
                    "source_variable": "",
                    "target": "ai_rewritten_content"
                }
            ]
        }
    ]
}
```

## vs JSON Format

### Plain Text Format (`expected_format: 'text'`):
- AI returns complete content as a single response
- Leave `source_variable` empty in output actions
- System auto-detects and uses the full response
- Perfect for content rewriting, summarization, etc.

### JSON Format (`expected_format: 'json'`):
- AI returns structured data: `{"title": "...", "content": "...", "score": 5}`
- Specify exact variables in output actions: `source_variable: "title"`
- Use for extracting specific fields from structured responses
- Perfect for analysis, scoring, field extraction, etc.

## Available Output Action Types

All action types work with auto-detection:

- `update_post_title` - Update the post title
- `update_post_content` - Update the post content  
- `update_post_excerpt` - Update the post excerpt
- `update_post_meta` - Save to a custom field (requires `target`)
- `append_to_post_content` - Add to end of post content
- `prepend_to_post_content` - Add to beginning of post content
- `save_to_option` - Save to WordPress option (requires `target`)

## Testing

Use the workflow tester in the admin interface:
1. Go to PolyTrans → Post-Processing
2. Edit or create a workflow
3. Use the test interface to validate your configuration
4. Check the output processing results to ensure auto-detection works

## Best Practices

1. **Always set expected_format**: Use `'text'` for plain responses, `'json'` for structured
2. **Use descriptive system prompts**: Be clear about what format you expect
3. **Leave source_variable empty for plain text**: Let the system auto-detect
4. **Test your workflows**: Use the built-in tester before deploying
5. **Monitor logs**: Check PolyTrans logs for any issues

## Troubleshooting

### If auto-detection fails:
1. Check that `expected_format` is set to `'text'`
2. Verify the AI is actually returning content
3. Look at the workflow execution logs
4. Test with a simple rewrite prompt first

### Common issues:
- **JSON in text format**: If AI returns JSON despite text format, check your system prompt
- **Empty responses**: Verify your user message template has proper variable substitution
- **Variable not found**: The system will show available variables in error messages

## Summary

The plain text workflow feature provides a simplified way to:
- ✅ Send complete post content to AI
- ✅ Receive plain text responses
- ✅ Automatically save responses without specifying variables
- ✅ Support multiple output actions per step
- ✅ Work with all available AI models and settings

This makes content rewriting, summarization, and similar tasks much easier to configure and use.
