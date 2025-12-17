# Workflow Output Actions - Complete Reference

All available output actions for workflow steps.

## Overview

Output actions define what happens with the results from a workflow step. Each step can have multiple output actions, and they are executed in order.

## Available Actions

### 1. `update_post_title`

Updates the post title with the step result.

**Parameters:**
- `source_variable` (optional) - Variable path to use (e.g., `title`, `ai_response.content`). If empty, auto-detects from response.
- `target` - Not used (post title is fixed)

**Example:**
```json
{
  "action": "update_post_title",
  "source_variable": "title",
  "target": ""
}
```

**Use Case:** Extract title from AI response and update post title.

---

### 2. `update_post_content`

Replaces the entire post content with the step result.

**Parameters:**
- `source_variable` (optional) - Variable path to use. If empty, auto-detects from response.
- `target` - Not used (post content is fixed)

**Example:**
```json
{
  "action": "update_post_content",
  "source_variable": "content",
  "target": ""
}
```

**Use Case:** Rewrite or optimize post content with AI.

---

### 3. `update_post_excerpt`

Updates the post excerpt with the step result.

**Parameters:**
- `source_variable` (optional) - Variable path to use. If empty, auto-detects from response.
- `target` - Not used (post excerpt is fixed)

**Example:**
```json
{
  "action": "update_post_excerpt",
  "source_variable": "excerpt",
  "target": ""
}
```

**Use Case:** Generate or update post excerpt automatically.

---

### 4. `update_post_meta`

Updates a custom post meta field.

**Parameters:**
- `source_variable` (optional) - Variable path to use. If empty, auto-detects from response.
- `target` (required) - Meta key name (e.g., `seo_keywords`, `custom_field`)

**Example:**
```json
{
  "action": "update_post_meta",
  "source_variable": "keywords",
  "target": "seo_keywords"
}
```

**Use Case:** Save extracted keywords, SEO data, or custom metadata.

---

### 5. `update_post_status`

Updates the post status (publish, draft, pending, etc.).

**Parameters:**
- `source_variable` (optional) - Variable path containing status value. If empty, auto-detects from response.
- `target` - Not used

**Valid Statuses:**
- `publish` - Published
- `draft` - Draft
- `pending` - Pending review
- `private` - Private
- `future` - Scheduled
- `trash` - Trashed

**Status Parsing:**
The system intelligently parses AI responses and handles common variations:
- `published` → `publish`
- `pending review` → `pending`
- `scheduled` → `future`

**Example:**
```json
{
  "action": "update_post_status",
  "source_variable": "status",
  "target": ""
}
```

**Use Case:** Quality gates - AI determines if content meets standards before publishing.

---

### 6. `update_post_date`

Updates the post publication date (for scheduling).

**Parameters:**
- `source_variable` (optional) - Variable path containing date value. If empty, auto-detects from response.
- `target` - Not used

**Supported Date Formats:**
- MySQL: `Y-m-d H:i:s` (e.g., `2025-12-16 14:30:00`)
- ISO 8601: `Y-m-d\TH:i:sP` (e.g., `2025-12-16T14:30:00+00:00`)
- Date only: `Y-m-d` (e.g., `2025-12-16`)
- US format: `m/d/Y` (e.g., `12/16/2025`)
- European format: `d/m/Y` (e.g., `16/12/2025`)
- Natural language: `tomorrow`, `next week`, `in 3 days`

**Example:**
```json
{
  "action": "update_post_date",
  "source_variable": "publish_date",
  "target": ""
}
```

**Use Case:** AI-driven optimal timing - schedule posts based on content analysis.

---

### 7. `append_to_post_content`

Appends content to the end of the post.

**Parameters:**
- `source_variable` (optional) - Variable path to use. If empty, auto-detects from response.
- `target` - Not used

**Example:**
```json
{
  "action": "append_to_post_content",
  "source_variable": "footer",
  "target": ""
}
```

**Use Case:** Add copyright notices, disclaimers, or additional content at the end.

---

### 8. `prepend_to_post_content`

Prepends content to the beginning of the post.

**Parameters:**
- `source_variable` (optional) - Variable path to use. If empty, auto-detects from response.
- `target` - Not used

**Example:**
```json
{
  "action": "prepend_to_post_content",
  "source_variable": "intro",
  "target": ""
}
```

**Use Case:** Add introductions, warnings, or promotional content at the start.

---

### 9. `save_to_option`

Saves a value to a WordPress option (site-wide setting).

**Parameters:**
- `source_variable` (optional) - Variable path to use. If empty, auto-detects from response.
- `target` (required) - Option name (e.g., `my_custom_option`)

**Example:**
```json
{
  "action": "save_to_option",
  "source_variable": "last_processed_date",
  "target": "polytrans_last_workflow_date"
}
```

**Use Case:** Store workflow statistics, last run dates, or site-wide data.

---

## Auto-Detection

If `source_variable` is empty, the system automatically detects the best value from the step response:

**Priority Order:**
1. `ai_response` (plain text format)
2. `processed_content` (predefined assistant plain text)
3. `content` (generic content variable)
4. `assistant_response` (assistant response)
5. Single variable (if only one exists)
6. First available value (fallback)

**Example:**
```json
{
  "action": "update_post_content",
  "source_variable": "",
  "target": ""
}
```

The system will automatically use the AI's response without specifying a variable.

---

## Variable Paths

You can use dot notation to access nested values:

**Examples:**
- `title` - Top-level variable
- `ai_response` - Nested variable
- `meta.seo_title` - Nested meta field
- `original.title` - Original post title
- `translated.content` - Translated post content

**JSON Response Example:**
```json
{
  "title": "New Title",
  "content": "New Content",
  "meta": {
    "keywords": "seo, optimization"
  }
}
```

**Variable Paths:**
- `title` → "New Title"
- `content` → "New Content"
- `meta.keywords` → "seo, optimization"

---

## Multiple Actions

You can configure multiple output actions for a single step:

**Example Workflow:**
```json
{
  "steps": [
    {
      "type": "managed_assistant",
      "config": {
        "assistant_id": 1,
        "user_message": "{{ content }}"
      },
      "output_actions": [
        {
          "action": "update_post_content",
          "source_variable": "content",
          "target": ""
        },
        {
          "action": "update_post_meta",
          "source_variable": "keywords",
          "target": "seo_keywords"
        },
        {
          "action": "update_post_status",
          "source_variable": "status",
          "target": ""
        }
      ]
    }
  ]
}
```

Actions are executed in order, and each action can use variables updated by previous actions.

---

## Best Practices

1. **Always specify `source_variable`** for clarity and reliability
2. **Use `target` parameter** for `update_post_meta` and `save_to_option`
3. **Test workflows** before enabling auto-trigger
4. **Check logs** if actions don't work as expected
5. **Use auto-detection** only for simple, single-value responses

---

## Troubleshooting

**Action not executing:**
- Check `source_variable` exists in step response
- Verify `target` is set for `update_post_meta` and `save_to_option`
- Review workflow execution logs

**Wrong value being used:**
- Specify `source_variable` explicitly instead of relying on auto-detection
- Check variable path syntax (use dot notation for nested values)

**Status/Date parsing fails:**
- Ensure value matches supported formats
- Check logs for parsing error messages
- Use exact status names: `publish`, `draft`, `pending`

---

**See Also:**
- [Workflow Management](WORKFLOW-TRIGGERING.md) - Creating workflows
- [Workflow Variables](WORKFLOW_VARIABLES.md) - Available variables
- [Workflow Logging](WORKFLOW-LOGGING.md) - Monitoring execution

