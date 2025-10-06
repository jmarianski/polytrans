# Retry Translation Feature

## Overview

The Retry Translation feature allows you to re-translate content even when a translation already exists. This is particularly useful when:

- A translation failed due to an error
- You want to improve an existing translation with updated content
- You've changed your translation provider settings and want to re-run
- The original translation had issues that need fixing

## How to Use

### From the Post Editor

1. **Open your post** in the WordPress editor
2. **Locate the Translation Scheduler** meta box on the right side
3. **Find the language** you want to retry
4. **Click the retry button** (🔄 icon) next to the language
5. **Confirm** the retry action when prompted
6. The translation will restart automatically

### Button Icons

The translation status area shows different buttons for each language:

- 👁️ **View/Edit** - View the translated post (shown when translation is complete)
- 🔄 **Retry** - Restart the translation process (shown for all statuses)
- ✖️ **Clear** - Remove the translation status completely

## What Happens During Retry

When you click the retry button:

1. **Clears existing status** - All previous translation metadata is removed:
   - Translation status
   - Translation logs
   - Error messages
   - Completion timestamps

2. **Schedules new translation** - A fresh translation is initiated:
   - Status set to "started"
   - New log entry created
   - Translation request sent to provider

3. **Ignores existing translations** - The system will:
   - Not check if translation already exists
   - Create/update the translated post as needed
   - Force re-translation even if post exists in target language

## Use Cases

### Failed Translation

If a translation failed due to an error:

```
Status: ❌ Failed
Action: Click 🔄 Retry
Result: Translation restarts from scratch
```

### Update After Content Changes

If you've edited the original post and want to update translation:

```
Status: ✅ Completed
Action: Click 🔄 Retry
Result: Translation re-runs with new content
```

### Provider Settings Changed

If you've updated your translation provider configuration:

```
Status: ✅ Completed
Action: Click 🔄 Retry
Result: Translation re-runs with new provider/settings
```

### Quality Improvement

If you're unsatisfied with translation quality:

```
Status: ✅ Completed  
Action: Click 🔄 Retry
Result: Translation re-runs (may produce different results)
```

## Important Notes

### Confirmation Required

- You must confirm the retry action
- Warning: "This will restart the translation process. Continue?"
- This prevents accidental re-translations

### Existing Translations

- Retry does **not** delete the existing translated post
- The translation process will update the existing post
- If you want to completely start over, use Clear first, then translate again

### Multiple Retries

- You can retry as many times as needed
- Each retry clears previous status and starts fresh
- Previous translation history is lost on retry

### Translation Queue

- Retried translations are processed immediately
- They follow the same queue as new translations
- May take time depending on provider load

## Technical Details

### Status Flow

```
Before Retry:
- Status: any (failed, completed, processing, etc.)
- Metadata: exists
- Log: historical entries

After Retry Click:
- Status: started
- Metadata: cleared
- Log: new entry "Translation retry initiated by user"

During Processing:
- Status: started → processing → completed/failed
- Metadata: updated during process
- Log: new entries added
```

### Cleared Metadata

On retry, these post meta fields are removed:

- `_polytrans_translation_status_{lang}`
- `_polytrans_translation_log_{lang}`
- `_polytrans_translation_error_{lang}`
- `_polytrans_translation_completed_{lang}`

### API Endpoint

The retry feature uses a dedicated AJAX endpoint:

**Action**: `polytrans_retry_translation`

**Parameters**:
- `post_id` - ID of the original post
- `lang` - Target language code
- `_ajax_nonce` - Security nonce

**Response**:
```json
{
  "success": true,
  "data": {
    "message": "Translation retry started for ES",
    "scheduled_langs": ["es", "fr", "de"],
    "lang": "es"
  }
}
```

## Troubleshooting

### Retry Button Not Visible

**Check**:
- Is the translation status visible in the scheduler?
- Is the post saved? (Unsaved posts can't be retried)
- Do you have permission to edit the post?

### Retry Does Nothing

**Check**:
- Browser console for JavaScript errors
- WordPress debug log for PHP errors
- Network tab for failed AJAX requests

### Translation Still Fails After Retry

**Check**:
- Translation provider configuration
- API key validity
- Network connectivity
- Source content (may contain untranslatable elements)
- Check PolyTrans logs for detailed error messages

### Multiple Retries Keep Failing

**Actions**:
1. Check the translation logs for specific errors
2. Verify provider API credentials
3. Test with a simpler post first
4. Contact support with log details

## Comparison: Retry vs Clear

| Feature | Retry | Clear |
|---------|-------|-------|
| **Clears status** | ✅ Yes | ✅ Yes |
| **Starts translation** | ✅ Yes | ❌ No |
| **Requires confirmation** | ✅ Yes | ✅ Yes |
| **Deletes translated post** | ❌ No | ❌ No |
| **Creates log entry** | ✅ Yes | ✅ Yes |
| **Use case** | Re-translate | Remove status |

## Best Practices

### Before Retrying

1. **Check the logs** - Understand why you need to retry
2. **Save your post** - Ensure all changes are saved
3. **Verify settings** - Confirm provider configuration is correct
4. **Review content** - Make sure source content is ready

### After Retrying

1. **Monitor progress** - Status updates every 5 seconds
2. **Check logs** - Review translation logs for issues
3. **Verify result** - Check the translated post when complete
4. **Test links** - Ensure internal links still work

### When to Use Clear Instead

Use **Clear** when you want to:
- Remove translation status without re-translating
- Manually translate instead of using automation
- Remove a language from scheduled languages
- Start completely fresh (clear first, then translate)

## Related Features

- [Translation Scheduler](INSTALLATION.md#translation-scheduler) - How to schedule translations
- [Translation Logs](FAQ.md#translation-logs) - Understanding translation logs
- [Failed Translations](FAQ.md#failed-translations) - Troubleshooting failures

## Support

If you encounter issues with the retry feature:

1. Check the **PolyTrans → Logs** page
2. Review the **translation logs** for the specific post
3. Verify **provider settings** are correct
4. Test with a **simple post** first
5. Report issues with log details and error messages

---

**Last Updated**: October 2025  
**Feature Version**: 1.2.0+
