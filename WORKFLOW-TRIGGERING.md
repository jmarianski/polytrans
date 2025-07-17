# PolyTrans Post-Processing Workflow Triggering Guide

This document explains the exact conditions required for post-processing workflows to trigger after translation completion.

## When Are Workflows Triggered?

Post-processing workflows are automatically triggered when the `polytrans_translation_completed` WordPress action is fired. This happens in three scenarios:

### 1. Background Translation Completion
- **File**: `includes/core/class-background-processor.php`
- **When**: After a scheduled background translation completes successfully
- **Action**: `do_action('polytrans_translation_completed', $original_post_id, $translated_post_id, $target_language)`

### 2. External Translation Completion
- **File**: `includes/core/class-translation-extension.php`
- **When**: When an external translation service reports completion via webhook/API
- **Action**: `do_action('polytrans_translation_completed', $original_post_id, $translated_post_id, $target_language)`

### 3. Manual Translation Status Update
- **File**: `includes/receiver/managers/class-translation-status-manager.php`
- **When**: When translation status is manually updated to completed
- **Action**: `do_action('polytrans_translation_completed', $original_post_id, $translated_post_id, $target_language)`

## Workflow Execution Conditions

For a workflow to execute automatically after translation completion, ALL of the following conditions must be met:

### 1. Workflow Must Be Enabled
```php
$workflow['enabled'] === true
```

### 2. Target Language Must Match
The workflow's target languages must either be empty (applies to all languages) or include the translation's target language:
```php
empty($workflow['target_languages']) || in_array($target_language, $workflow['target_languages'])
```

### 3. Translation Completion Trigger Must Be Enabled
```php
$workflow['triggers']['on_translation_complete'] === true
```

### 4. Manual-Only Mode Must Be Disabled
```php
$workflow['triggers']['manual_only'] !== true
```

### 5. Additional Conditions Must Pass (If Any)
If the workflow has additional conditions (post type, categories, etc.), they must all evaluate to true:
```php
$this->evaluate_workflow_conditions($workflow['triggers']['conditions'], $context)
```

## Common Issues & Solutions

### Issue: Workflows Not Triggering

#### Possible Causes:
1. **Workflow is disabled**
   - **Solution**: Enable the workflow in PolyTrans → Post-Processing

2. **Translation completion trigger is disabled**
   - **Solution**: Edit the workflow and ensure "Trigger on translation completion" is checked

3. **Manual-only mode is enabled**
   - **Solution**: Edit the workflow and uncheck "Manual execution only"

4. **Target language mismatch**
   - **Solution**: Ensure the workflow's target languages include the language you're translating to

5. **Translation didn't complete properly**
   - **Solution**: Check post meta for completion status and timestamp

6. **WordPress action not fired**
   - **Solution**: Verify that `polytrans_translation_completed` action is being fired (check logs)

### Issue: Translation Completed But No Workflows Execute

#### Debug Steps:
1. **Check workflow configuration**:
   ```php
   $workflows = get_option('polytrans_workflows', []);
   var_dump($workflows);
   ```

2. **Verify hook registration**:
   ```php
   global $wp_filter;
   var_dump($wp_filter['polytrans_translation_completed']);
   ```

3. **Check post meta**:
   ```php
   $status = get_post_meta($post_id, '_polytrans_translation_status_' . $language, true);
   $completed = get_post_meta($post_id, '_polytrans_translation_completed_' . $language, true);
   ```

4. **Use the debug tool**:
   - Go to PolyTrans → Workflow Debug
   - Enter post IDs and language
   - Run debug analysis

## Testing Workflow Triggering

### Manual Testing
You can manually trigger the translation completed action for testing:

```php
// Simulate translation completion
do_action('polytrans_translation_completed', $original_post_id, $translated_post_id, $target_language);
```

### Using the Debug Tool
1. Go to **PolyTrans → Workflow Debug** in WordPress admin
2. Enter the original post ID, translated post ID, and target language
3. Click "Debug Workflows" to analyze conditions
4. Click "Simulate Trigger" to manually fire the action

## Workflow Context Variables

When workflows are triggered, they receive a context with these variables:

```php
$context = [
    'original_post_id' => $original_post_id,
    'translated_post_id' => $translated_post_id,
    'target_language' => $target_language,
    'trigger' => 'translation_completed'
];
```

Additional variables are provided by data providers:
- **Post Data Provider**: `post_title`, `post_content`, `post_excerpt`, etc.
- **Meta Data Provider**: Custom field values
- **Context Data Provider**: WordPress context (user, site info, etc.)
- **Articles Data Provider**: Recent articles for SEO linking

## Logging

All workflow execution is logged using `PolyTrans_Logs_Manager::log()`. Check the logs at:
- **Database**: PolyTrans → Logs (if database logging is enabled)
- **Error Log**: WordPress error log file
- **Post Meta**: `_polytrans_workflow_execution_log` (for individual posts)

## Example Workflow Configuration

Here's an example of a properly configured workflow that will trigger automatically:

```php
$workflow = [
    'id' => 'seo-linking-workflow',
    'name' => 'SEO Internal Linking',
    'enabled' => true,
    'target_languages' => ['en', 'fr'], // or [] for all languages
    'triggers' => [
        'on_translation_complete' => true,
        'manual_only' => false,
        'conditions' => [] // or specific conditions
    ],
    'steps' => [
        // ... workflow steps
    ]
];
```

## Troubleshooting Checklist

When workflows aren't triggering, check:

- [ ] Workflow is enabled
- [ ] Target language is configured correctly
- [ ] "Trigger on translation completion" is checked
- [ ] "Manual execution only" is unchecked
- [ ] Translation actually completed successfully
- [ ] WordPress action `polytrans_translation_completed` is fired
- [ ] Workflow Manager is loaded and listening for the action
- [ ] No PHP errors in the logs
- [ ] Additional workflow conditions (if any) are met

## Getting Help

If workflows still aren't triggering after checking all conditions:

1. Use the **Workflow Debug** tool to get a detailed analysis
2. Check the **PolyTrans Logs** for error messages
3. Enable WordPress debug logging to see detailed error information
4. Verify that all PolyTrans components are loaded properly
