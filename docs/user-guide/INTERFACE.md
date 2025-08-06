# PolyTrans User Interface Guide

This guide explains how to use the PolyTrans plugin interface for content translation and management.

## Overview

PolyTrans adds several new interfaces to your WordPress admin:

1. **Translation Meta Boxes** (on post/page edit screens)
2. **Translation Settings** (Settings menu)
3. **Translation Logs** (PolyTrans menu)
4. **Workflow Management** (PolyTrans menu)
5. **Tag Translations** (Posts menu)

## Translation Meta Boxes

When editing posts or pages, you'll see two new meta boxes:

### Translation Meta Box

Located on the post edit screen, this meta box lets you:

#### Mark Translation Type
- **Human Translated**: Mark content as professionally translated
- **Machine Translated**: Mark content as automatically translated
- **Original**: Mark content as original (not translated)

**How to use:**
1. Edit any post or page
2. Scroll to the "Translation" meta box
3. Select the appropriate translation type
4. Update the post

### Translation Scheduler Meta Box

This is the main interface for initiating translations:

#### Translation Scope Options
- **Local**: Translate within the same WordPress installation
- **Regional**: Translate to specific languages you choose
- **Global**: Translate to all configured languages

#### Regional Translation Setup
When selecting "Regional" scope:

1. **Choose Target Languages**: Select which languages to translate to
   - Checkboxes appear for each configured language
   - You can select multiple languages
   - Only available target languages are shown

2. **Enable Review** (Optional): 
   - Check "Enable Review" to require human review
   - Translated content will need approval before publishing
   - Reviewers will receive email notifications

3. **Start Translation**:
   - Click "Translate" to begin the process
   - Progress will be shown in real-time
   - Check Translation Logs for detailed status

#### Example Workflow
```
1. Edit your English post
2. Scroll to "Translation Scheduler"
3. Select "Regional" scope
4. Check "Spanish" and "French" languages
5. Enable "Review" if needed
6. Click "Translate"
7. Monitor progress in Translation Logs
```

## Translation Settings

Access via **Settings → Translation Settings**

### Translation Provider Configuration

#### Google Translate Provider
- **No setup required**
- Select "Google Translate" and save
- Ready to use immediately

#### OpenAI Provider
More advanced setup required:

1. **API Key**: Enter your OpenAI API key
2. **Primary Language**: Set your main language
3. **Assistant Mapping**: Map language pairs to specific AI assistants
   - Format: `source_to_target` (e.g., "en_to_pl")
   - Value: Assistant ID (e.g., "asst_123abc")

### Language Configuration

#### Allowed Languages
Configure which languages are available:

- **Source Languages**: Languages you translate FROM
- **Target Languages**: Languages you translate TO
- **Language Codes**: Use standard codes (en, es, fr, de, pl, etc.)

#### Post Status Settings
For each target language, configure:

- **Post Status After Translation**: 
  - `publish`: Automatically publish translated posts
  - `draft`: Save as draft for review
  - `pending`: Set to pending review
  - `same_as_source`: Match the source post status

### Email Configuration

#### Notification Settings
- **Enable Notifications**: Turn email notifications on/off
- **Review Notifications**: Notify reviewers when translations are ready
- **Completion Notifications**: Notify authors when translations are published

#### Email Templates
Customize notification emails:
- **Subject Lines**: Customize email subjects
- **Message Content**: Personalize email content
- **Recipient Settings**: Configure who receives what notifications

## Translation Logs

Access via **PolyTrans → Translation Logs**

### Log Viewing Interface

#### Log Filters
- **Date Range**: Filter by time period
- **Log Level**: Filter by severity (Info, Warning, Error)
- **Component**: Filter by system component
- **Action**: Filter by specific actions

#### Log Details
Each log entry shows:
- **Timestamp**: When the action occurred
- **Level**: Severity (Info/Warning/Error)
- **Component**: Which part of the system
- **Message**: Detailed information
- **Context**: Additional data (post ID, user, etc.)

#### Example Log Entry
```
2024-12-09 10:30:15 | INFO | Translation Scheduler
Message: Regional translation initiated for post ID 123
Context: {
  "post_id": 123,
  "target_languages": ["es", "fr"],
  "scope": "regional",
  "user_id": 1
}
```

### Common Log Messages

#### Successful Operations
- `Translation request sent` - Translation initiated
- `Translation received` - Translation completed
- `Post created successfully` - New translated post created

#### Warnings
- `Review required` - Translation needs human review
- `Missing language configuration` - Language not properly set up
- `API rate limit approaching` - Slow down requests

#### Errors
- `Translation failed` - Something went wrong
- `API authentication failed` - Check API keys
- `Invalid post data` - Data validation failed

## Workflow Management

Access via **PolyTrans → Workflows**

### Workflow List

View all configured workflows:
- **Name**: Workflow identifier
- **Status**: Active/Inactive
- **Steps**: Number of processing steps
- **Last Run**: When last executed
- **Actions**: Edit, Test, Delete

### Creating/Editing Workflows

#### Basic Information
- **Name**: Descriptive workflow name
- **Description**: What the workflow does
- **Status**: Active or inactive

#### Workflow Steps
Add processing steps:

1. **AI Assistant Steps**: Use OpenAI for content processing
   - Select assistant
   - Configure prompts
   - Set parameters

2. **Predefined Steps**: Use built-in processing
   - Choose from available templates
   - Customize as needed

#### Output Actions
Define what should be updated:
- **Post Title**: Update the post title
- **Post Content**: Update the post content
- **Meta Fields**: Update custom fields
- **SEO Data**: Update Yoast/RankMath fields

#### Testing Workflows
Before enabling:
1. Click "Test" next to any workflow
2. Select a test post
3. Review the preview of changes
4. Enable workflow if results look good

### Workflow Execution Logs
Monitor workflow activity:
- **Execution History**: See when workflows ran
- **Success/Failure Rates**: Monitor reliability
- **Processing Times**: Track performance
- **Error Details**: Debug issues

## Tag Translations

Access via **Posts → Tag Translations**

### Managing Multilingual Tags

#### Adding Tags for Translation
1. **Tag List**: View all tags that need translation
2. **Add New**: Add tags to the translation list
3. **Language Mapping**: Map translations for each language

#### Translation Interface
For each tag:
- **Original Tag**: The source tag name
- **Language Columns**: Translation for each target language
- **Status**: Translation status (Complete/Pending/Missing)

#### Bulk Operations
- **CSV Export**: Export tag translations for offline work
- **CSV Import**: Import bulk tag translations
- **Bulk Translate**: Automatically translate multiple tags

### Example Tag Translation Workflow
```
1. Go to Posts → Tag Translations
2. Add "Technology" tag to translation list
3. Fill in translations:
   - Spanish: "Tecnología"
   - French: "Technologie"
   - German: "Technologie"
4. Save changes
5. Tags will be automatically applied to translated posts
```

## Status Indicators

Throughout the interface, look for visual indicators:

### Translation Status Icons
- ✅ **Complete**: Translation finished successfully
- ⏳ **In Progress**: Translation currently processing
- ⚠️ **Review Required**: Needs human review
- ❌ **Failed**: Translation encountered an error
- 📝 **Draft**: Saved as draft

### Workflow Status Badges
- 🟢 **Active**: Workflow is enabled and running
- 🔴 **Inactive**: Workflow is disabled
- 🟡 **Testing**: Workflow in test mode
- ⚫ **Error**: Workflow has configuration issues

## Keyboard Shortcuts

When using the interface:

- **Ctrl/Cmd + S**: Save current form
- **Tab**: Navigate between form fields
- **Enter**: Submit active form (in most cases)
- **Esc**: Cancel current action

## Mobile Interface

PolyTrans works on mobile devices:

- **Responsive Design**: Interface adapts to screen size
- **Touch-Friendly**: Large buttons and touch targets
- **Core Features**: Translation scheduling and monitoring work on mobile
- **Limitations**: Complex workflow editing better on desktop

## Accessibility

The plugin interface includes:

- **Screen Reader Support**: All elements properly labeled
- **Keyboard Navigation**: Full functionality without mouse
- **High Contrast**: Compatible with accessibility themes
- **Font Scaling**: Respects browser font size settings

## Tips for Efficient Use

### Best Practices
1. **Review Settings First**: Configure languages and providers before translating
2. **Test Small**: Start with a single post to test your setup
3. **Monitor Logs**: Check logs regularly for issues
4. **Use Workflows**: Automate repetitive post-processing tasks
5. **Plan Reviews**: Set up reviewer assignments for quality control

### Time-Saving Tips
1. **Bulk Operations**: Use regional scope to translate to multiple languages at once
2. **Saved Configurations**: Create workflow templates for common tasks
3. **Tag Management**: Set up tag translations before bulk content translation
4. **Email Notifications**: Configure notifications to stay informed without constantly checking

### Troubleshooting Interface Issues

#### Interface Not Loading
- Check browser console for JavaScript errors
- Disable other plugins temporarily
- Clear browser cache
- Check WordPress debug logs

#### Missing Elements
- Verify user permissions
- Check plugin activation status
- Ensure required dependencies are installed

#### Performance Issues
- Reduce number of languages if interface is slow
- Check server resources during peak translation times
- Consider enabling WordPress object caching

## Next Steps

After learning the interface:

1. **[Configuration Guide](../admin/CONFIGURATION.md)**: Advanced settings
2. **[FAQ & Troubleshooting](FAQ.md)**: Common questions
3. **[Examples](../../examples/)**: Real-world usage examples

---

*Need help with a specific interface element? Check our [FAQ](FAQ.md) or contact support.*
