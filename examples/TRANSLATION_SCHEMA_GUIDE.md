# Complete Translation Schema Guide

This guide shows how to create a full-featured translation assistant with auto-mapping for all WordPress SEO fields.

## Files in This Directory

1. **`translation-schema-full.json`** - Complete schema with all SEO fields (formatted)
2. **`translation-user-message-full.twig`** - User message template that sends all fields

## Supported SEO Fields

### RankMath SEO
- `rank_math_title`
- `rank_math_description`
- `rank_math_facebook_title`
- `rank_math_facebook_description`
- `rank_math_twitter_title`
- `rank_math_twitter_description`
- `rank_math_focus_keyword`

### Yoast SEO
- `_yoast_wpseo_title`
- `_yoast_wpseo_metadesc`
- `_yoast_wpseo_focuskw`
- `_yoast_wpseo_opengraph-title`
- `_yoast_wpseo_opengraph-description`
- `_yoast_wpseo_twitter-title`
- `_yoast_wpseo_twitter-description`

## How to Use

### Step 1: Copy the Schema

Open `translation-schema-full.json` and copy the entire content.

Or use this minified version for direct paste:

```json
{"title":{"type":"string","target":"post.title","required":true},"content":{"type":"string","target":"post.content","required":true},"excerpt":{"type":"string","target":"post.excerpt"},"meta":{"rank_math_title":{"type":"string","target":"meta.rank_math_title"},"rank_math_description":{"type":"string","target":"meta.rank_math_description"},"rank_math_facebook_title":{"type":"string","target":"meta.rank_math_facebook_title"},"rank_math_facebook_description":{"type":"string","target":"meta.rank_math_facebook_description"},"rank_math_twitter_title":{"type":"string","target":"meta.rank_math_twitter_title"},"rank_math_twitter_description":{"type":"string","target":"meta.rank_math_twitter_description"},"rank_math_focus_keyword":{"type":"string","target":"meta.rank_math_focus_keyword"},"_yoast_wpseo_title":{"type":"string","target":"meta._yoast_wpseo_title"},"_yoast_wpseo_metadesc":{"type":"string","target":"meta._yoast_wpseo_metadesc"},"_yoast_wpseo_focuskw":{"type":"string","target":"meta._yoast_wpseo_focuskw"},"_yoast_wpseo_opengraph-title":{"type":"string","target":"meta._yoast_wpseo_opengraph-title"},"_yoast_wpseo_opengraph-description":{"type":"string","target":"meta._yoast_wpseo_opengraph-description"},"_yoast_wpseo_twitter-title":{"type":"string","target":"meta._yoast_wpseo_twitter-title"},"_yoast_wpseo_twitter-description":{"type":"string","target":"meta._yoast_wpseo_twitter-description"}}}
```

### Step 2: Create or Edit Assistant

1. Go to **PolyTrans → AI Assistants**
2. Create new or edit existing assistant
3. Set **Response Format** to **JSON**
4. Paste the schema into **Expected Output Schema** field
5. Copy **User Message Template** from `translation-user-message-full.twig`
6. Save

### Step 3: Use in Workflow

1. Go to **PolyTrans → Workflows**
2. Add **Managed Assistant** step
3. Select your translation assistant
4. **No Output Actions needed!** All fields are auto-mapped

## What Happens Automatically

With this schema, the system will automatically:

✅ Update `post.title` with translated title
✅ Update `post.content` with translated content
✅ Update `post.excerpt` with translated excerpt
✅ Update all RankMath SEO fields
✅ Update all Yoast SEO fields
✅ Skip empty/missing fields gracefully
✅ Log warnings for missing required fields

## Customization

### Remove Fields You Don't Use

If you only use RankMath, remove Yoast fields from schema:

```json
{
  "title": {"type": "string", "target": "post.title", "required": true},
  "content": {"type": "string", "target": "post.content", "required": true},
  "meta": {
    "rank_math_title": {"type": "string", "target": "meta.rank_math_title"},
    "rank_math_description": {"type": "string", "target": "meta.rank_math_description"}
  }
}
```

### Add Custom Fields

Add your own meta fields:

```json
{
  "meta": {
    "my_custom_field": {
      "type": "string",
      "target": "meta.my_custom_field"
    }
  }
}
```

### Make Fields Required

Add `"required": true` to enforce presence:

```json
{
  "meta": {
    "rank_math_title": {
      "type": "string",
      "target": "meta.rank_math_title",
      "required": true
    }
  }
}
```

## Troubleshooting

### AI doesn't return all fields

**Normal!** AI will only return fields that exist in the original post. Empty fields are set to `null` automatically.

### Some fields not updating

Check logs (**PolyTrans → Logs**) for:
- Parsing warnings
- Missing field warnings
- Auto-action execution logs

### Want to see what's happening

Enable debug logging in workflow test mode to see:
- Interpolated prompts
- AI raw response
- Parsed data
- Auto-actions generated

## Performance Tips

### For Large Posts

Increase `max_tokens` in assistant configuration:
- Small posts: 2000 tokens
- Medium posts: 4000 tokens
- Large posts: 8000 tokens

### For Faster Translation

Use `gpt-4o-mini` instead of `gpt-4o`:
- 60% cheaper
- 2x faster
- Still excellent quality for most content

## Example: Minimal Schema

If you only need basic translation:

```json
{
  "title": {
    "type": "string",
    "target": "post.title",
    "required": true
  },
  "content": {
    "type": "string",
    "target": "post.content",
    "required": true
  }
}
```

User Message:
```twig
Translate from {{ source_language }} to {{ target_language }}:

Title: {{ translated.title }}
Content: {{ translated.content }}

Return JSON: {"title": "...", "content": "..."}
```

Done! 🎉

## Flynt / ACF Flexible Content

ACF Flexible Content can store data in two ways:

### Option A: Flat Meta Keys (Trans.info style)

WordPress stores each component field as a separate meta key:
- `postComponents_0_contentHtml`
- `postComponents_1_contentHtml`
- `postComponents_2_contentHtml`
- etc.

**Use Twig in your schema** to dynamically include all matching keys:

```json
{
  "title": {"type": "string", "target": "post.title", "required": true},
  "content": {"type": "string", "target": "post.content", "required": true},
  "meta": {
    "_yoast_wpseo_metadesc": {"type": "string", "target": "meta._yoast_wpseo_metadesc"}
    {% for key, value in original.meta %}
      {% if key matches '/^postComponents_\\d+_contentHtml$/' %}
        ,"{{ key }}": {"type": "string", "target": "meta.{{ key }}"}
      {% endif %}
    {% endfor %}
  }
}
```

**User Message Template:**

```twig
Translate from {{ source_language }} to {{ target_language }}:

{
  "title": "{{ original.title|escape('js') }}",
  "content": "{{ original.content|escape('js') }}",
  "meta": {
    "_yoast_wpseo_metadesc": "{{ original.meta._yoast_wpseo_metadesc|default('')|escape('js') }}"
    {% for key, value in original.meta %}
      {% if key matches '/^postComponents_\\d+_contentHtml$/' %}
        ,"{{ key }}": "{{ value|escape('js') }}"
      {% endif %}
    {% endfor %}
  }
}

Return translated JSON with same structure.
```

> **Note:** PolyTrans 1.8.0+ automatically includes `postComponents_*_contentHtml` and `pageComponents_*_contentHtml` in the translation context.

### Option B: Serialized Array (Classic ACF)

Some setups store all components in a single `pageComponents` meta field as a serialized array:

```json
[
  {
    "acf_fc_layout": "blockWysiwyg",
    "contentHtml": "<p>Text to translate</p>",
    "options": {"theme": "light"}
  },
  {
    "acf_fc_layout": "blockImageText",
    "title": "Headline",
    "text": "<p>Description</p>",
    "image": 123
  }
]
```

For this approach, use:
- **`translation-schema-flynt.json`** - Schema with pageComponents as array
- **`translation-user-message-flynt.twig`** - User message template for Flynt

The schema defines `pageComponents` as `"type": "array"`, which passes the entire structure to AI for translation.

### Important

The AI prompt should instruct to:
- Translate ONLY text fields (contentHtml, title, text, caption, etc.)
- Preserve exact JSON structure
- Keep layout names, IDs, and options unchanged

### Translatable Fields in Flynt

Common text fields across Flynt components:
- `contentHtml` - WYSIWYG content
- `title`, `headline`, `subline` - Headings
- `text`, `intro`, `body` - Text blocks
- `caption`, `description` - Image/media descriptions

