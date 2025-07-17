# PolyTrans Workflow Attribution User Feature

## Overview

The Attribution User feature allows administrators to specify which user should be credited for changes made by workflow executions. This is particularly useful for maintaining clean revision logs and proper change attribution in WordPress.

## How It Works

### 1. Setting Attribution User

When creating or editing a workflow:

1. Navigate to **PolyTrans > Workflows** in the WordPress admin
2. Create a new workflow or edit an existing one
3. In the **Workflow Settings** section, find the **Change Attribution User** field
4. Start typing a username, display name, or email to search for users
5. Select the desired user from the autocomplete dropdown
6. Save the workflow

### 2. Workflow Execution

When a workflow executes:

1. The system captures the current user context
2. If an attribution user is specified, it temporarily switches to that user
3. All workflow changes (post updates, meta updates, etc.) are attributed to the specified user
4. After execution completes, the system restores the original user context
5. All user context switches are logged for audit purposes

### 3. User Context Switching

The implementation safely handles user context by:

- Storing the original user ID before any changes
- Validating that the attribution user exists and is valid
- Switching to the attribution user only for actual changes (not test mode)
- Restoring the original user context after all changes complete
- Logging all context switches with relevant details

## Benefits

### Clean Revision Logs
- All workflow changes appear as if made by the specified user
- Revision history shows consistent authorship
- Easy to identify automated vs. manual changes

### Audit Trail
- Complete logging of when user context switching occurs
- Tracks original user, attribution user, and workflow details
- Warning logs for invalid or missing attribution users

### Flexible Attribution
- Can attribute to any valid WordPress user
- Different workflows can use different attribution users
- Optional feature - workflows without attribution users work normally

## Technical Implementation

### Frontend (JavaScript)
- User autocomplete field with search functionality
- Uses custom AJAX endpoint for user search
- Fallback to WP REST API if custom endpoint unavailable
- Clear button to remove selected user

### Backend (PHP)
- Workflow data includes `attribution_user` (user ID) and `attribution_user_label` (display name)
- User context switching in `PolyTrans_Workflow_Output_Processor`
- Validation and error handling for invalid users
- Comprehensive logging through `PolyTrans_Logs_Manager`

### Database Storage
- Attribution user ID stored as string in workflow configuration
- Attribution user label stored for display purposes
- Both values included in workflow data sanitization and normalization

## Error Handling

### Invalid Users
- If attribution user ID doesn't correspond to valid user, workflow continues with original user
- Warning logged about invalid attribution user
- No workflow execution failure due to attribution issues

### Missing Permissions
- User autocomplete requires `manage_options` capability
- User search endpoint validates user permissions
- Attribution only applies to users that exist in the system

## Security Considerations

### User Search
- AJAX endpoint validates user permissions
- Search limited to existing WordPress users
- Nonce verification for all AJAX requests

### Context Switching
- Only switches user context for actual workflow execution (not test mode)
- Always restores original user context, even if errors occur
- Comprehensive logging of all context changes

## Testing

A comprehensive test suite is available in `test-attribution-user.php` which:

- Tests workflow execution with attribution users
- Validates user context switching
- Tests error handling with invalid users
- Shows recent attribution logs
- Demonstrates both test mode and actual execution

## Configuration Options

### Workflow Settings
- **Change Attribution User**: Optional user selector field
- **Placeholder**: "Type to search user..."
- **Help Text**: Explains when attribution is applied vs. current user

### JavaScript Configuration
- User autocomplete with 2-character minimum search
- Custom AJAX endpoint with fallback to REST API
- Proper nonce handling for security

## Logging

All attribution-related activities are logged with:

- Source: `workflow_output_processor`
- Context: Original user, attribution user, workflow name
- Levels: Info (successful switches), Warning (invalid users)
- Timestamps and detailed context for audit trails

## Compatibility

- Compatible with all existing workflows
- Backward compatible (workflows without attribution users work unchanged)
- Works with all WordPress user roles and capabilities
- Integrates with existing PolyTrans logging and error handling systems
