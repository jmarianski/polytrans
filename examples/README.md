# Translation Assistants - Ready-to-Use Examples

This directory contains production-ready SQL scripts for creating translation assistants with schema-based JSON parsing.

## Available Assistants

### 1. `translation-assistant-en-pl.sql`
**English → Polish** translator for general WordPress content.

**Features:**
- Natural, fluent Polish translation
- SEO optimization
- Preserves HTML, markdown, emojis
- Schema: `{title, content, excerpt, meta}`

**Use case:** Blog posts, articles, general content

---

### 2. `translation-assistant-en-fr-logistics.sql`
**English → French** specialized translator for logistics & transport industry.

**Features:**
- Industry-specific glossary (Trans.eu terminology)
- KEY-based translation system
- Preserves markdown formatting (**bold**, *italics*)
- Schema: `{KEY, text}`

**Use case:** Trans.eu platform, logistics content, batch translations

---

### 3. `translation-assistant-template.sql`
**Universal template** for any language pair.

**Replace these placeholders:**
- `{SOURCE_LANG}` → e.g., "EN", "PL", "FR"
- `{TARGET_LANG}` → e.g., "PL", "EN", "FR"
- `{SOURCE_NAME}` → e.g., "English", "Polish", "French"
- `{TARGET_NAME}` → e.g., "Polish", "English", "French"

**Use case:** Creating custom language pairs

---

## Installation

### Option 1: Via MySQL/phpMyAdmin
1. Open phpMyAdmin or MySQL client
2. Select your WordPress database
3. Run the SQL script for your desired assistant
4. Note the `assistant_id` from the result

### Option 2: Via WP-CLI
```bash
wp db query < translation-assistant-en-pl.sql
```

### Option 3: Via PHP (in WordPress)
```php
global $wpdb;
$sql = file_get_contents(__DIR__ . '/examples/translation-assistant-en-pl.sql');
$wpdb->query($sql);
```

---

## Usage in Workflows

After creating an assistant, use it in your workflows:

### Step 1: Add Managed Assistant Step
1. Go to **PolyTrans → Workflows**
2. Create or edit a workflow
3. Add step: **Managed Assistant**
4. Select your translation assistant
5. Configure trigger (e.g., "When post is published")

### Step 2: Configure Output Actions
The assistant returns structured data based on its schema:

**For EN→PL assistant (full post structure):**
```
Output Actions:
- update_post_field: title = {{ step_1_output.title }}
- update_post_field: content = {{ step_1_output.content }}
- update_post_field: excerpt = {{ step_1_output.excerpt }}
- update_post_meta: seo_title = {{ step_1_output.meta.seo_title }}
```

**For EN→FR Logistics assistant (KEY-based):**
```
Output Actions:
- update_post_meta: translated_text = {{ step_1_output.text }}
- update_post_meta: translation_key = {{ step_1_output.KEY }}
```

---

## Customization

### Adjusting the Model
Change `"model":"gpt-4o"` in `api_parameters` to:
- `"gpt-4o-mini"` - Faster, cheaper, good quality
- `"gpt-4o"` - Best quality, slower, more expensive
- `"gpt-4-turbo"` - Balanced option

### Adjusting Temperature
Change `"temperature":0.3` to:
- `0.0-0.3` - More consistent, literal translations
- `0.4-0.7` - Balanced creativity and consistency
- `0.8-1.0` - More creative, varied translations

### Adding Custom Glossary
Edit the `system_prompt` field and add your terminology:

```sql
## Custom Glossary
- your term → twój termin
- another term → inny termin
```

---

## Schema-Based Parsing

All assistants use **Expected Output Schema** for robust JSON parsing:

### Benefits:
✓ **Handles AI quirks** - Extracts JSON from commentary, code blocks, etc.
✓ **Type coercion** - Converts `"8"` → `8`, `"yes"` → `true`
✓ **Missing fields** - Sets to `null` with warnings
✓ **Validation** - Ensures correct structure
✓ **Structured data** - Direct access via `{{ step_output.field }}`

### Example:
AI responds:
```
Sure! Here's the translation:
```json
{"title": "Translated Title", "content": "Translated Content"}
```
Hope this helps!
```

Parser extracts:
```json
{"title": "Translated Title", "content": "Translated Content"}
```

Workflow context gets:
- `{{ step_1_output.title }}` = "Translated Title"
- `{{ step_1_output.content }}` = "Translated Content"

---

## Troubleshooting

### Assistant not appearing in dropdown
1. Check if assistant was created: `SELECT * FROM wp_polytrans_assistants;`
2. Verify `status` = 'active'
3. Clear WordPress cache

### Translation not working
1. Check workflow logs: **PolyTrans → Logs**
2. Look for parsing warnings
3. Verify OpenAI API key is configured
4. Check model availability (gpt-4o requires API access)

### Schema validation errors
1. Check assistant's `expected_output_schema` is valid JSON
2. Verify AI response matches schema structure
3. Review parse warnings in logs

---

## Support

For issues or questions:
1. Check logs: **PolyTrans → Logs**
2. Test assistant manually: Edit assistant → Test button
3. Review schema validation warnings

---

## License

These examples are part of the PolyTrans plugin and follow the same license.

