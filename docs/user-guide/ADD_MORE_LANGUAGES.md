# Add More Languages Feature

## Overview

The **Add More Languages** feature allows you to easily add additional language translations to a post that already has some translations scheduled or completed. This is useful when you want to expand your content's reach to more languages without going through the full translation scheduler workflow again.

## How to Use

### Step 1: Check Existing Translations

When a post already has translations (scheduled, in progress, completed, or failed), you'll see them listed in the Translation Scheduler meta box with status indicators:

- ⏳ **Spinning icon** - Translation in progress
- ✅ **Green checkmark** - Translation completed
- ❌ **Red X** - Translation failed

### Step 2: Click "Add More Languages"

Below the list of existing translations, you'll see a button:

```
[➕ Add More Languages]
```

Click this button to expand the language selection panel.

### Step 3: Select Additional Languages

A dropdown will appear showing **only available languages** (languages that haven't been translated yet):

- Languages already translated or scheduled are hidden
- Hold **Ctrl** (Windows/Linux) or **Cmd** (Mac) to select multiple languages
- Check "Needs review" if you want these translations to require review before publishing

### Step 4: Start Translation

Click **"Start Translation"** to begin translating to the selected languages.

- The panel will close automatically
- New language translations will appear in the status list
- Translation will start immediately
- Status updates every 5 seconds

### Canceling

Click **"Cancel"** to close the panel without adding languages.

## Features

### Smart Language Filtering

The "Add More Languages" dropdown automatically:

- ✅ **Shows only available languages** - Languages not yet translated
- ❌ **Hides scheduled languages** - Languages already in the queue
- ❌ **Hides completed languages** - Languages already translated
- ❌ **Hides failed languages** - Languages that failed (use retry instead)

### Automatic Visibility

The "Add More Languages" button appears only when:

1. **There are existing translations** - At least one language is scheduled/completed/failed
2. **There are available languages** - At least one language hasn't been translated yet

If all available languages are already translated, the button won't appear.

### Independent Settings

Each "Add More" translation can have its own settings:

- **Needs Review** - Toggle independently from original translations
- Translations use the current provider settings
- Follows the same workflow as regular translations

## Use Cases

### Expanding Content Reach

**Scenario**: You initially translated to Spanish and French, now want to add German and Italian.

```
Initial: EN → ES, FR ✅
Add More: DE, IT
Result: EN → ES, FR, DE, IT ✅
```

### Testing Markets

**Scenario**: Test with one language first, then expand to more markets.

```
Phase 1: EN → ES (test market)
Phase 2: Add FR, DE, IT (expand)
Phase 3: Add PT, PL (further expand)
```

### Gradual Rollout

**Scenario**: Translate to languages gradually as resources allow.

```
Week 1: Major markets (ES, FR, DE)
Week 2: Add more (IT, PT)
Week 3: Add additional (PL, NL)
```

### After Failed Translations

**Scenario**: Some translations succeeded, some failed. Add new languages while you fix failed ones.

```
Status: ES ✅, FR ✅, DE ❌
Add More: IT, PT
Result: ES ✅, FR ✅, DE ❌, IT ⏳, PT ⏳
```

## Workflow Comparison

### Traditional Method

Without "Add More Languages":

1. Go to Translation Scheduler
2. Select scope: Regional
3. Select ALL target languages (including already translated)
4. System may skip already translated languages
5. Confusion about which are new vs existing

### With Add More Languages

Streamlined workflow:

1. Click "Add More Languages"
2. See only available languages
3. Select new languages
4. Click "Start Translation"
5. Clear which languages are being added

## Important Notes

### Does Not Re-translate

Adding more languages does **NOT** re-translate existing languages:

- If Spanish is already translated, it won't appear in the dropdown
- To re-translate, use the **Retry** button (🔄) instead
- "Add More" is strictly for new languages

### Respects Settings

New translations respect current settings:

- **Translation Provider** - Uses currently selected provider (Google/OpenAI)
- **Post Status** - Uses language-specific post status settings
- **Reviewer** - Uses language-specific reviewer settings
- **Email Notifications** - Sent according to settings

### No Batch Limits

You can add as many languages as you want:

- Select multiple languages at once
- Add languages multiple times
- No limit on total languages

## Technical Details

### Button Visibility Logic

```javascript
Show "Add More" button when:
- hasExistingTranslations = true (any status: started/completed/failed)
- hasAvailableLanguages = true (at least one untranslated language)

Hide button when:
- No translations exist yet (use main scheduler)
- All available languages already translated
```

### Language Filtering

```javascript
Available languages = All allowed target languages
                    - Currently scheduled languages
                    - Source language
```

### AJAX Request

Same endpoint as regular translation scheduling:

```
Action: polytrans_schedule_translation
Scope: regional
Targets: [selected languages array]
Needs Review: 0 or 1
```

### Status Updates

After adding languages:

1. Panel closes automatically
2. Status list refreshes immediately
3. Polling starts (updates every 5 seconds)
4. New languages appear with status indicators

## UI Elements

### Add More Languages Button

```html
[➕ Add More Languages]
```

- **Location**: Below translation status list
- **Style**: Secondary button (gray)
- **Width**: Full width of container
- **Icon**: Plus icon (dashicons-plus-alt)

### Add More Panel

```
┌─────────────────────────────────────┐
│ Add More Languages                   │
├─────────────────────────────────────┤
│ [  ] German                          │
│ [  ] Italian                         │
│ [  ] Portuguese                      │
│                                      │
│ Hold Ctrl/Cmd to select multiple    │
│                                      │
│ ☐ Needs review                      │
│                                      │
│ [Start Translation] [Cancel]        │
└─────────────────────────────────────┘
```

## Troubleshooting

### Button Not Visible

**Possible Reasons**:
- No existing translations yet → Use main scheduler
- All languages already translated → Nothing to add
- Post not saved → Save post first

### Can't Find a Language

**Check**:
- Is it already translated? → Shows in status list
- Is it allowed in settings? → Check Translation Settings
- Is it the source language? → Can't translate to source

### Translation Not Starting

**Check**:
- Did you select at least one language?
- Is the post saved?
- Are provider settings configured?
- Check browser console for errors

### Added Wrong Languages

**Solution**:
- Wait for translation to complete
- Use **Clear** button (✖️) to remove unwanted translations
- Or use **Retry** button (🔄) if you want to keep but re-translate

## Best Practices

### Plan Your Rollout

1. **Start with major markets** - Translate to your primary languages first
2. **Add gradually** - Don't overwhelm translators with too many at once
3. **Monitor results** - Check quality before adding more
4. **Use needs review** - Enable review for new languages until confident

### Language Grouping

Consider adding languages in logical groups:

- **European languages**: ES, FR, DE, IT
- **Nordic languages**: SV, NO, DA, FI
- **Slavic languages**: PL, CZ, SK, RU
- **Asian languages**: JA, KO, ZH

### Quality Control

For new languages:

1. Add one test language first
2. Verify translation quality
3. Adjust provider settings if needed
4. Then add remaining languages in group

### Resource Management

Be mindful of:

- **API costs** - More languages = more API calls
- **Review capacity** - Don't add more than reviewers can handle
- **Server load** - Batch additions during off-peak hours

## Related Features

- [Translation Scheduler](INSTALLATION.md#translation-scheduler) - Main translation interface
- [Retry Translation](RETRY_TRANSLATION.md) - Re-translate existing languages
- [Clear Translation](FAQ.md#clear-translation) - Remove translation status
- [Translation Settings](INSTALLATION.md#translation-settings) - Configure languages

## Examples

### Example 1: Global Content Expansion

```
Original post: English blog article
Initial translation: Spanish, French
After 1 month: Add German, Italian (good engagement)
After 3 months: Add Portuguese, Polish (expand reach)
After 6 months: Add Dutch, Swedish (complete Europe)
```

### Example 2: Market Testing

```
Product launch post: English
Test market: Add Spanish only
If successful: Add French, German
If very successful: Add 5 more European languages
```

### Example 3: Seasonal Content

```
Holiday campaign: English
Week 1: Add major markets (ES, FR, DE)
Week 2: Add secondary (IT, PT, NL)
Week 3: Add remaining (PL, SV, DA)
```

## Support

If you have issues with the "Add More Languages" feature:

1. Check this documentation first
2. Review the **PolyTrans → Logs** page
3. Verify **Translation Settings** are correct
4. Test with a single language first
5. Report issues with specific details

---

**Last Updated**: October 2025  
**Feature Version**: 1.2.0+
