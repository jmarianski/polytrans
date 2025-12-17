# Workflow Variables - Complete Reference

All available variables for use in workflow templates and prompts.

## Overview

Variables allow you to access post data, metadata, and context information in workflow templates. They use **Twig syntax** (`{{ variable }}`) for interpolation.

## Variable Syntax

**Twig Syntax (Recommended):**
```twig
{{ title }}
{{ original.content }}
{{ translated.meta.seo_title }}
```

**Legacy Syntax (Still Supported):**
```
{title}
{original.content}
{translated.meta.seo_title}
```

---

## Top-Level Aliases

These are convenience variables that reference the translated post (most commonly used):

| Variable | Description | Example |
|----------|-------------|---------|
| `title` | Translated post title | `{{ title }}` |
| `content` | Translated post content | `{{ content }}` |
| `excerpt` | Translated post excerpt | `{{ excerpt }}` |
| `author_name` | Post author name | `{{ author_name }}` |
| `date` | Post publication date | `{{ date }}` |

**Note:** These are aliases for `translated.title`, `translated.content`, etc.

---

## Original Post Variables

Access data from the original (source) post:

### Basic Fields

| Variable | Description | Example |
|----------|-------------|---------|
| `original.title` | Original post title | `{{ original.title }}` |
| `original.content` | Original post content | `{{ original.content }}` |
| `original.excerpt` | Original post excerpt | `{{ original.excerpt }}` |
| `original.slug` | Post slug | `{{ original.slug }}` |
| `original.status` | Post status | `{{ original.status }}` |
| `original.type` | Post type | `{{ original.type }}` |
| `original.author_name` | Author name | `{{ original.author_name }}` |
| `original.date` | Publication date | `{{ original.date }}` |

### Taxonomies

| Variable | Description | Example |
|----------|-------------|---------|
| `original.categories` | Category names (array) | `{{ original.categories|join(', ') }}` |
| `original.tags` | Tag names (array) | `{{ original.tags|join(', ') }}` |

### Meta Fields

| Variable | Description | Example |
|----------|-------------|---------|
| `original.meta` | All meta fields (object) | `{{ original.meta }}` |
| `original.meta.KEY` | Specific meta field | `{{ original.meta.seo_title }}` |

**Example:**
```twig
Original title: {{ original.title }}
SEO title: {{ original.meta.seo_title }}
Categories: {{ original.categories|join(', ') }}
```

---

## Translated Post Variables

Access data from the translated post:

### Basic Fields

| Variable | Description | Example |
|----------|-------------|---------|
| `translated.title` | Translated post title | `{{ translated.title }}` |
| `translated.content` | Translated post content | `{{ translated.content }}` |
| `translated.excerpt` | Translated post excerpt | `{{ translated.excerpt }}` |
| `translated.slug` | Post slug | `{{ translated.slug }}` |
| `translated.status` | Post status | `{{ translated.status }}` |
| `translated.type` | Post type | `{{ translated.type }}` |
| `translated.author_name` | Author name | `{{ translated.author_name }}` |
| `translated.date` | Publication date | `{{ translated.date }}` |

### Taxonomies

| Variable | Description | Example |
|----------|-------------|---------|
| `translated.categories` | Category names (array) | `{{ translated.categories|join(', ') }}` |
| `translated.tags` | Tag names (array) | `{{ translated.tags|join(', ') }}` |

### Meta Fields

| Variable | Description | Example |
|----------|-------------|---------|
| `translated.meta` | All meta fields (object) | `{{ translated.meta }}` |
| `translated.meta.KEY` | Specific meta field | `{{ translated.meta.seo_description }}` |

**Example:**
```twig
Translated title: {{ translated.title }}
Current content: {{ translated.content }}
SEO description: {{ translated.meta.seo_description }}
```

---

## Legacy Variables (Backward Compatibility)

These are still supported but `original.*` and `translated.*` are recommended:

| Variable | Description | Example |
|----------|-------------|---------|
| `original_post.title` | Original post title | `{{ original_post.title }}` |
| `original_post.content` | Original post content | `{{ original_post.content }}` |
| `original_post.excerpt` | Original post excerpt | `{{ original_post.excerpt }}` |
| `translated_post.title` | Translated post title | `{{ translated_post.title }}` |
| `translated_post.content` | Translated post content | `{{ translated_post.content }}` |
| `translated_post.excerpt` | Translated post excerpt | `{{ translated_post.excerpt }}` |

---

## Meta Data Variables

Access post metadata separately:

| Variable | Description | Example |
|----------|-------------|---------|
| `original_meta` | All original post meta (object) | `{{ original_meta }}` |
| `original_meta.KEY` | Specific original meta field | `{{ original_meta.custom_field }}` |
| `translated_meta` | All translated post meta (object) | `{{ translated_meta }}` |
| `translated_meta.KEY` | Specific translated meta field | `{{ translated_meta.custom_field }}` |

---

## Context Variables

Additional context information available in workflows:

| Variable | Description | Example |
|----------|-------------|---------|
| `target_language` | Target language code | `{{ target_language }}` |
| `source_language` | Source language code | `{{ source_language }}` |
| `translation_service` | Provider used | `{{ translation_service }}` |
| `quality_score` | Translation quality score (if available) | `{{ quality_score }}` |
| `word_count` | Post word count | `{{ word_count }}` |

---

## Twig Features

Workflow templates support full Twig syntax:

### Conditionals

```twig
{% if translated.status == 'publish' %}
This post is published.
{% else %}
This post is not published.
{% endif %}
```

### Loops

```twig
Categories:
{% for category in original.categories %}
- {{ category }}
{% endfor %}
```

### Filters

```twig
{{ content|length }} characters
{{ title|upper }}
{{ categories|join(', ') }}
```

### String Operations

```twig
{{ content|slice(0, 100) }}...  {# First 100 characters #}
{{ title|replace('Old', 'New') }}
```

---

## Variable Examples

### Compare Original and Translated

```twig
Original: {{ original.title }}
Translated: {{ translated.title }}

Original content length: {{ original.content|length }}
Translated content length: {{ translated.content|length }}
```

### Access Meta Fields

```twig
SEO Title: {{ original.meta.seo_title }}
SEO Description: {{ translated.meta.seo_description }}
Keywords: {{ original.meta.seo_keywords }}
```

### Use in AI Prompts

```twig
Translate this content from {{ source_language }} to {{ target_language }}:

Title: {{ original.title }}
Content: {{ original.content }}

Current translation:
{{ translated.content }}

Please improve the translation quality.
```

### Conditional Logic

```twig
{% if translated.meta.seo_title %}
SEO Title: {{ translated.meta.seo_title }}
{% else %}
No SEO title set.
{% endif %}
```

---

## Variable Availability

Variables are provided by different **Variable Providers**:

1. **PostDataProvider** - Post fields, taxonomies
2. **MetaDataProvider** - Meta fields
3. **ContextDataProvider** - Context variables

All providers are automatically loaded when executing workflows.

---

## Best Practices

1. **Use `original.*` and `translated.*`** instead of legacy `original_post.*`
2. **Use Twig syntax** (`{{ }}`) instead of legacy (`{}`)
3. **Check variable existence** with conditionals before using
4. **Use filters** for formatting (e.g., `|join(', ')` for arrays)
5. **Access nested values** with dot notation (e.g., `meta.seo_title`)

---

## Troubleshooting

**Variable not found:**
- Check variable name spelling
- Verify variable exists in context (use conditionals)
- Check if post has the data (e.g., meta field exists)

**Empty value:**
- Variable may not be set for this post
- Use `{% if variable %}` to check before using
- Check logs for variable provider errors

**Nested access fails:**
- Use dot notation: `meta.seo_title` not `meta['seo_title']`
- Verify nested structure exists
- Check Twig syntax is correct

---

## Variable Reference Quick Sheet

```
Top-Level:        title, content, excerpt, author_name, date
Original:         original.* (title, content, excerpt, slug, status, type, categories, tags, meta.*)
Translated:       translated.* (title, content, excerpt, slug, status, type, categories, tags, meta.*)
Meta:            original_meta.KEY, translated_meta.KEY
Context:         target_language, source_language, translation_service, quality_score, word_count
Legacy:          original_post.*, translated_post.*
```

---

**See Also:**
- [Workflow Management](WORKFLOW-TRIGGERING.md) - Creating workflows
- [Workflow Output Actions](WORKFLOW_OUTPUT_ACTIONS.md) - Using variables in actions
- [Workflow Logging](WORKFLOW-LOGGING.md) - Debugging variable issues

