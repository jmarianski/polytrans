# Workflow Management

## What are Workflows?

Post-processing automation that runs after translation. Use cases:
- SEO optimization
- Content formatting
- Custom transformations
- QA checks

## Creating Workflows

**PolyTrans → Post-Processing → Add New Workflow**

### Basic Settings
- **Name:** Descriptive name
- **Target Language:** Which language this applies to
- **Enabled:** Auto-run on translations

### Workflow Steps

**AI Assistant:**
- Uses OpenAI for processing
- System prompt: Instructions for AI
- User message: Template with variables (`{content}`, `{title}`)
- Expected format: `text` or `json`

**Find/Replace:**
- Simple text replacement
- Multiple find/replace pairs

**Regex Replace:**
- Pattern-based replacement
- Supports capture groups

### Output Actions

What to do with step results:
- `update_post_content` - Replace post content
- `update_post_meta` - Set custom field
- `update_post_excerpt` - Update excerpt

### Variables

Available in templates:
- `{content}` - Post content
- `{title}` - Post title
- `{excerpt}` - Post excerpt
- `{custom_field_name}` - Any custom field

## Triggering Workflows

### Automatic
- Enable workflow
- Runs after translation completes
- All matching translations processed

### Manual
**Three ways:**

1. **From post editor:**
   - PolyTrans meta box → Click "Execute"

2. **From workflow list:**
   - PolyTrans → Post-Processing → Click "Execute"

3. **From Execute menu:**
   - PolyTrans → Execute Workflow

## Testing Workflows

**Edit workflow → Click "Test Workflow"**

- Enter sample content
- See results before saving
- Check for errors

## Examples

### SEO Optimization
```
Step: AI Assistant
System Prompt: "Optimize for SEO"
User Message: "{content}"
Output: update_post_content
```

### Add Copyright Notice
```
Step: Find/Replace
Find: (empty)
Replace: "© 2025 Company Name"
Position: append
Output: update_post_content
```

### Extract Keywords
```
Step: AI Assistant
System Prompt: "Extract 5 keywords as JSON"
User Message: "{content}"
Expected Format: json
Output: update_post_meta → seo_keywords
```

## Workflow Execution Order

1. Translation completes
2. Check for enabled workflows matching language
3. Execute steps sequentially
4. Apply output actions
5. Log results
6. Update post

## Logs

**PolyTrans → Post-Processing → Logs**

View:
- Execution history
- Step results
- Errors
- Execution time
- Post affected

Filter by:
- Workflow
- Status (success/failed)
- Date range
- Post ID

## Best Practices

- Test workflows before enabling
- Use descriptive names
- Keep steps simple
- One workflow per purpose
- Monitor logs regularly
- Handle errors gracefully

## Troubleshooting

**Workflow not running:**
- Check it's enabled
- Verify target language matches
- Check logs for errors

**AI step fails:**
- Verify OpenAI assistant exists
- Check API key valid
- Review prompt template

**Output not applied:**
- Check output action configuration
- Verify source variable exists
- Review execution logs

See [WORKFLOW-LOGGING.md](WORKFLOW-LOGGING.md) for log details.
