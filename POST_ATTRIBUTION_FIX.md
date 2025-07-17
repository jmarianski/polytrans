# PolyTrans Post Creation Author Attribution Fix

## Issue Summary

The PolyTrans plugin was not properly preserving the original post author when creating translated posts. Instead of attributing translated posts to the original author, they were being attributed to the user executing the translation process (often a system user or the currently logged-in user).

## Root Cause

The `PolyTrans_Translation_Post_Creator::create_post()` method was not including the `post_author` field in the `$postarr` array passed to `wp_insert_post()`, causing WordPress to default to the current user.

## Solution Implemented

### 1. Primary Fix: Post Creator Enhancement

**File**: `includes/receiver/managers/class-translation-post-creator.php`

**Changes**:
- Retrieve the original post object to get the author information
- Include `'post_author' => $original_post->post_author` in the post creation array
- Add comprehensive logging for audit purposes
- Validate that the original post exists before proceeding

**Benefits**:
- Sets the correct author from the moment the post is created
- Avoids unnecessary subsequent updates
- More efficient than the previous approach
- Provides clear audit trail

### 2. Secondary Enhancement: Metadata Manager Improvement

**File**: `includes/receiver/managers/class-translation-metadata-manager.php`

**Changes**:
- Replaced `error_log()` calls with proper `PolyTrans_Logs_Manager::log()` calls
- Added check to avoid unnecessary updates if author is already correct
- Enhanced logging with structured context data
- Improved error handling and validation

**Benefits**:
- Consistent logging throughout the plugin
- Avoids redundant database operations
- Better debugging and audit capabilities
- More robust error handling

## Implementation Details

### Post Creation Flow

1. **Original Flow** (with issue):
   ```
   wp_insert_post($postarr) // No post_author specified → defaults to current user
   → setup_metadata() → set_author() → wp_update_post() // Fix author after creation
   ```

2. **New Flow** (fixed):
   ```
   wp_insert_post($postarr + ['post_author' => $original_author]) // Correct author from start
   → setup_metadata() → set_author() // Skips if already correct
   ```

### Logging Enhancement

All author attribution activities are now logged with:
- **Source**: `translation_post_creator` or `translation_metadata_manager`
- **Context**: Original post ID, translated post ID, author details
- **Levels**: Info (success), Warning (issues), Debug (skipped operations)

### Error Handling

- Validates original post exists before creating translation
- Handles missing or invalid author information gracefully
- Provides meaningful error messages for debugging
- Continues operation even if author attribution fails

## Testing

### Automated Test Suite

**File**: `test-post-attribution.php`

The test suite validates:
1. Post Creator preserves author correctly during creation
2. Metadata Manager fixes author if needed
3. Complete translation flow maintains author attribution
4. Logging system captures all attribution activities

### Manual Testing

1. Create a post as User A
2. Execute translation while logged in as User B
3. Verify translated post is attributed to User A (not User B)
4. Check logs for proper attribution tracking

## Compatibility

- **Backward Compatible**: Existing workflows continue to work
- **WordPress Standard**: Uses standard WordPress post creation patterns
- **Plugin Integration**: Works with existing PolyTrans logging and error handling
- **Performance**: More efficient due to reduced database operations

## Security Considerations

- Preserves original authorship for proper content attribution
- Maintains audit trail of all author changes
- Validates user permissions through WordPress standard functions
- No privilege escalation or unauthorized author assignment

## Configuration

No configuration changes required. The fix:
- Automatically applies to all new translations
- Works with all post types supported by PolyTrans
- Respects existing WordPress user permissions
- Integrates with existing PolyTrans settings

## Related Features

This fix complements the **Workflow Attribution User** feature by ensuring:
- **Post Creation**: Uses original post author (this fix)
- **Workflow Changes**: Can use specified attribution user (workflow feature)
- Clear distinction between content authorship and change attribution
- Complete audit trail for both creation and modification activities
