# PolyTrans Post-Processing Workflow Logging

## Overview
Comprehensive logging has been added to the PolyTrans post-processing workflow system to track workflow execution, step results, and troubleshoot issues.

## Log Messages Structure
All log messages use the prefix `PolyTrans Workflow` for easy filtering and follow this pattern:
```
PolyTrans Workflow: [Component]: [Message]
PolyTrans Workflow ERROR: [Component]: [Error message]  
PolyTrans Workflow EXCEPTION: [Component]: [Exception details]
```

## Workflow Manager Logging

### Translation Trigger Events
- **Translation completion detected**: When a translation finishes
- **Workflow discovery**: How many workflows found for target language
- **Workflow conditions**: Which workflows pass/fail conditions and why
- **Execution scheduling**: When workflows are scheduled to run

Example log messages:
```
PolyTrans Workflow Manager: Translation completed - Original: 123, Translated: 456, Language: pl
PolyTrans Workflow Manager: Found 2 workflow(s) for language 'pl'
PolyTrans Workflow Manager: Workflow 'SEO Internal Linking' passed all conditions
PolyTrans Workflow Manager: Skipping workflow 'Content Review' (conditions not met)
PolyTrans Workflow Manager: Scheduling immediate execution of workflow 'SEO Internal Linking'
```

### Workflow Execution
- **Execution start/end**: When workflows begin and complete
- **Variable context building**: How many data providers and variables
- **Success/failure status**: Overall workflow results

Example log messages:
```
PolyTrans Workflow Manager: Starting execution of workflow 'SEO Internal Linking' (ID: workflow_123)
PolyTrans Workflow Manager: Building variable context with 4 data providers
PolyTrans Workflow Manager: Variable context built with 23 variables
PolyTrans Workflow Manager: Workflow 'SEO Internal Linking' execution completed successfully
```

## Workflow Executor Logging

### Workflow Level
- **Execution start**: Workflow name, ID, test mode status
- **Context analysis**: Number of variables available
- **Validation results**: Whether workflow structure is valid
- **Overall completion**: Success/failure with execution time and step count

Example log messages:
```
PolyTrans Workflow: Starting execution of 'SEO Internal Linking' (ID: workflow_123)
PolyTrans Workflow: Context includes 23 variables: title, content, original_post.title, ...
PolyTrans Workflow: Validation passed. Found 2 steps to execute
PolyTrans Workflow: 'SEO Internal Linking' completed successfully in 3.542s. Executed 2 steps.
```

### Step Level
- **Step execution**: Step name, ID, type, and execution order
- **Step timing**: Individual step execution time
- **Step results**: Success/failure with output variables
- **Step skipping**: When steps are disabled or conditions not met

Example log messages:
```
PolyTrans Workflow: Executing step #0: 'Content Analysis' (ID: analyze_content, Type: ai_assistant)
PolyTrans Workflow: Step 'Content Analysis' completed successfully in 2.156s
PolyTrans Workflow: Step output variables: analysis_result, quality_score, suggestions
PolyTrans Workflow: Skipping disabled step 'Optional Enhancement' (ID: enhance_content)
```

### Output Processing
- **Output actions**: Number of actions being processed
- **Action results**: Success/failure of individual output actions
- **Context updates**: When execution context is updated

Example log messages:
```
PolyTrans Workflow: Processing 3 output actions for step 'Content Analysis'
PolyTrans Workflow: Successfully processed 3 output actions for step 'Content Analysis'
PolyTrans Workflow: Merged step output into execution context for next steps
```

### Error Handling
- **Step failures**: Detailed error messages for failed steps
- **Continue on error**: When execution continues despite failures
- **Exceptions**: Full exception details with stack traces

Example log messages:
```
PolyTrans Workflow ERROR: Step 'Content Analysis' failed: API timeout after 30 seconds
PolyTrans Workflow: Continuing despite step failure (continue_on_error = true)
PolyTrans Workflow EXCEPTION: 'SEO Internal Linking' failed with exception after 1.234s: Connection refused
```

## Condition Checking Logging

The system logs detailed information about why workflows are or aren't executed:

```
PolyTrans Workflow Manager: Workflow 'Manual Review Only' is set to manual execution only
PolyTrans Workflow Manager: Workflow 'Disabled Workflow' is disabled  
PolyTrans Workflow Manager: Workflow 'Translation Only' not configured for translation completion trigger
PolyTrans Workflow Manager: Workflow 'Post Type Restricted' conditions not met
```

## How to Enable Logging

### WordPress Configuration
Add these lines to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false); // Don't show errors on frontend
```

### Log File Locations
- **WordPress debug log**: `wp-content/debug.log`
- **Server error log**: Usually `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- **PHP error log**: Check `ini_get('error_log')` in PHP

## Filtering Log Messages

To see only PolyTrans workflow logs:
```bash
# From WordPress debug.log
grep "PolyTrans Workflow" wp-content/debug.log

# Live monitoring
tail -f wp-content/debug.log | grep "PolyTrans Workflow"

# Only errors
grep "PolyTrans Workflow ERROR\|PolyTrans Workflow EXCEPTION" wp-content/debug.log
```

## Common Log Patterns

### Successful Workflow Execution
```
PolyTrans Workflow Manager: Translation completed - Original: 123, Translated: 456, Language: pl
PolyTrans Workflow Manager: Found 1 workflow(s) for language 'pl'  
PolyTrans Workflow Manager: Workflow 'SEO Internal Linking' passed all conditions
PolyTrans Workflow Manager: Scheduling immediate execution of workflow 'SEO Internal Linking'
PolyTrans Workflow: Starting execution of 'SEO Internal Linking' (ID: workflow_abc123)
PolyTrans Workflow: Validation passed. Found 2 steps to execute
PolyTrans Workflow: Executing step #0: 'Content Analysis' (ID: step1, Type: ai_assistant)
PolyTrans Workflow: Step 'Content Analysis' completed successfully in 2.156s
PolyTrans Workflow: Step output variables: enhanced_content, links_added
PolyTrans Workflow: Processing 1 output actions for step 'Content Analysis'
PolyTrans Workflow: Successfully processed 1 output actions for step 'Content Analysis'
PolyTrans Workflow: 'SEO Internal Linking' completed successfully in 2.234s. Executed 1 steps.
```

### No Workflows Available
```
PolyTrans Workflow Manager: Translation completed - Original: 123, Translated: 456, Language: de
PolyTrans Workflow Manager: No workflows found for language 'de'
```

### Workflow Skipped Due to Conditions
```
PolyTrans Workflow Manager: Translation completed - Original: 123, Translated: 456, Language: pl
PolyTrans Workflow Manager: Found 2 workflow(s) for language 'pl'
PolyTrans Workflow Manager: Workflow 'Manual Only Workflow' is set to manual execution only
PolyTrans Workflow Manager: Workflow 'Auto Content Enhancement' passed all conditions
PolyTrans Workflow Manager: Scheduled 1 workflow(s) for execution
```

## Troubleshooting

### No Log Messages Appearing
1. Check `WP_DEBUG` and `WP_DEBUG_LOG` are enabled
2. Verify log file exists and is writable
3. Ensure workflows are enabled and configured for translation trigger
4. Check if manual_only flag is preventing automatic execution

### Workflow Not Executing
Look for these messages to diagnose:
- "No workflows found for language 'X'" - No workflows configured for that language
- "Workflow 'X' is disabled" - Workflow needs to be enabled
- "Workflow 'X' is set to manual execution only" - Check manual_only setting
- "Workflow 'X' not configured for translation completion trigger" - Check trigger settings

### Step Failures
- Look for "Step 'X' failed:" messages
- Check if API keys are configured
- Verify step configuration is valid
- Check if required variables are available in context

## Performance Monitoring

The logs include timing information for performance analysis:
- Overall workflow execution time
- Individual step execution time
- Variable context building time

This helps identify slow steps or overall performance issues.
