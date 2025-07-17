# PolyTrans Step Context Updating Fix

## Issue Summary

During workflow execution, subsequent steps were receiving stale context data instead of the updated values from previous steps. This caused workflows where Step 1 modifies post content and Step 2 processes that content to fail, as Step 2 would receive the original content instead of the modified content.

## Root Cause Analysis

### Problem Flow
1. **Step 1**: Removes links from post content → Updates database with new content
2. **Step 2**: Expects to process the updated content → Receives original content with links still present
3. **Result**: Step 2 processes outdated data, leading to incorrect workflow outcomes

### Technical Root Cause
The issue was in the `PolyTrans_Workflow_Executor` class, specifically in the output processing logic:

```php
// Original problematic code (lines 183-185)
if ($test_mode && isset($output_result['updated_context'])) {
    $execution_context = $output_result['updated_context'];
}
```

The execution context was **only** updated when in test mode, but **not** during actual workflow execution. This meant:
- **Test Mode**: Context properly updated between steps ✓
- **Production Mode**: Context never updated, steps received stale data ✗

## Solution Implemented

### 1. Fix Context Updating in Workflow Executor

**File**: `includes/postprocessing/class-workflow-executor.php`

**Change**: Update execution context in both test mode and production mode:

```php
// Fixed code
if ($output_result['success']) {
    // ... existing code ...
    
    // Update execution context with changes (both test mode and production mode)
    if (isset($output_result['updated_context'])) {
        $execution_context = $output_result['updated_context'];
    }
}
```

### 2. Add Database Context Refresh for Production Mode

**File**: `includes/postprocessing/class-workflow-output-processor.php`

**New Method**: Added `refresh_context_from_database()` to sync context with actual database state:

```php
private function refresh_context_from_database($context)
{
    // Get current post from database
    $current_post = get_post($translated_post_id);
    
    // Update context with current database values
    $context['title'] = $current_post->post_title;
    $context['content'] = $current_post->post_content;
    $context['excerpt'] = $current_post->post_excerpt;
    
    // Update translated_post structure
    $context['translated_post']['content'] = $current_post->post_content;
    // ... etc
}
```

**Integration**: Call refresh method after making changes in production mode:

```php
// In production mode, refresh context from database to get actual current values
if (!$test_mode && !empty($changes)) {
    $updated_context = $this->refresh_context_from_database($updated_context);
}
```

## How It Works Now

### Test Mode Flow
1. Step 1 executes → Creates change objects → Updates context via `apply_change_to_context()`
2. Step 2 receives updated context → Processes with correct data
3. No database changes made

### Production Mode Flow
1. Step 1 executes → Makes database changes → Refreshes context from database
2. Step 2 receives updated context → Processes with correct data
3. Database changes persist

## Benefits

### ✅ Correct Data Flow
- Subsequent steps now receive the actual updated data from previous steps
- Context accurately reflects the current state of the database
- Workflows behave consistently between test and production modes

### ✅ Improved Reliability
- No more stale data issues causing workflow failures
- Proper data dependency handling between steps
- Predictable workflow behavior

### ✅ Performance Optimized
- Context refresh only happens when changes are made
- Minimal database queries (only when necessary)
- Efficient context synchronization

## Testing

### Automated Test Suite

**File**: `test-context-updating.php`

The test suite validates:
1. **Context Updating**: Verifies subsequent steps receive updated data
2. **Database Consistency**: Ensures context matches actual database state
3. **Test vs Production**: Confirms consistent behavior in both modes
4. **Change Detection**: Validates that old data is properly replaced

### Test Scenario
1. Create post with content containing old links
2. Step 1: Remove all links from content
3. Step 2: Add new links to content  
4. Verify: Final content has new links but no old links

### Expected Results
- **Before Fix**: Step 2 would see original content with old links
- **After Fix**: Step 2 sees updated content without old links

## Compatibility

- **Backward Compatible**: Existing workflows continue to work unchanged
- **Test Mode**: Enhanced to match production behavior
- **Production Mode**: Fixed to properly update context
- **Performance**: Minimal impact, only refreshes when needed

## Edge Cases Handled

### Missing Post ID
- Graceful fallback if post ID not found in context
- Context returned unchanged if post cannot be retrieved
- No errors thrown for missing references

### Meta Data Synchronization
- Post meta is refreshed along with post content
- Custom fields stay in sync with database state
- Complex data structures properly updated

### Multiple Changes
- Context refreshed after all changes in a step are complete
- Batch operations properly synchronized
- No intermediate state inconsistencies

## Debugging Support

### Enhanced Logging
- Context updates are logged for audit purposes
- Before/after states tracked for debugging
- Step-by-step context changes recorded

### Test Tools
- Comprehensive test suite for validation
- Manual testing scenarios provided
- Log analysis tools for troubleshooting

## Related Features

This fix complements existing features:
- **Workflow Attribution**: User context still properly maintained
- **Output Processing**: All action types benefit from correct context
- **Step Dependencies**: Proper data flow between related steps
- **Error Handling**: Consistent behavior in error scenarios

## Configuration

No configuration changes required. The fix:
- Automatically applies to all workflow executions
- Works with all step types and output actions
- Maintains all existing functionality
- Requires no migration or updates
