# User Interface Guide

## Post Editor

### Translation Scheduler Meta Box

On any post/page edit screen:

1. **Select scope:**
   - Regional: Translate to configured languages
   - Global: Custom language selection

2. **Select languages** (checkboxes)

3. **Enable Review** (optional)
   - Assigns reviewer
   - Post saved as draft

4. **Click "Translate"**

### Translation Status

Shows existing translations:
- ⏳ In progress
- ✅ Completed  
- ❌ Failed

**Add More Languages:** Button to translate to additional languages

## Admin Menus

### PolyTrans → Translation Logs

View all translation activity:
- Translation requests
- Success/failure status
- Error messages
- Timestamps

**Filters:**
- By status
- By language
- By date
- By post

### PolyTrans → Post-Processing

Manage workflows:
- Create workflows
- Edit steps
- Test workflows
- Execute manually
- View execution logs

### Settings → Translation Settings

Configure:
- Translation provider
- API keys
- Languages
- Default reviewers
- Email notifications

### Posts → Tag Translations

Manage tag translations:
- View tags by language
- Create missing translations
- Edit existing translations

## Workflow Management

### Create Workflow

1. **PolyTrans → Post-Processing**
2. Click "Add New Workflow"
3. Configure:
   - Name
   - Target language
   - Trigger (auto/manual)
4. Add steps:
   - AI Assistant (OpenAI)
   - Find/Replace
   - Regex Replace
5. Configure output actions
6. Save

### Execute Workflow Manually

**Method 1:** From post editor
- Scroll to PolyTrans meta box
- Click "Execute" next to workflow

**Method 2:** From workflow list
- PolyTrans → Post-Processing
- Click "Execute" next to workflow
- Select post

**Method 3:** From Execute menu
- PolyTrans → Execute Workflow
- Select workflow
- Select post
- Execute

### View Logs

**PolyTrans → Post-Processing → Logs tab**

Shows:
- Execution history
- Step results
- Errors
- Execution time

## Notifications

Configure in **Settings → Email Settings**:

- Translation complete
- Review requested
- Translation failed

Customize email templates with variables:
- `{post_title}`
- `{source_language}`
- `{target_language}`
- `{post_link}`

## Tips

- Use **Regional scope** for consistent languages
- Enable **Review** for quality control
- Check **Translation Logs** regularly
- Test workflows before enabling auto-trigger
- Use OpenAI for better quality
