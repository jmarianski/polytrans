# Workflow Execution Logs

## Accessing Logs

**PolyTrans → Post-Processing → Logs tab**

## Log Information

Each execution log shows:
- **Workflow Name** - Which workflow ran
- **Post ID** - Affected post
- **Status** - Success/failed
- **Duration** - Execution time
- **Timestamp** - When it ran
- **Steps** - Individual step results
- **Errors** - Error messages if failed

## Log Details

Click a log entry to see:

### Overall Status
- Success/failed/partial
- Total execution time
- Post before/after

### Step Results
For each step:
- Step type and configuration
- Input received
- Output generated
- Execution time
- Success/failure status
- Error messages

### Output Actions
- Actions performed
- Fields updated
- Before/after values

## Filtering Logs

**Filters available:**
- Workflow: Specific workflow
- Status: Success/failed/all
- Date range: Last 7/30/90 days or custom
- Post ID: Specific post

## Log Retention

- Logs kept for 90 days by default
- Configure in Settings → Translation Settings
- Old logs auto-deleted

## Understanding Errors

### Common Error Types

**OpenAI Errors:**
```
Assistant not found: asst_abc123
```
→ Assistant doesn't exist or was deleted

**Validation Errors:**
```
Missing required variable: {content}
```
→ Template uses variable not available in context

**Timeout Errors:**
```
Execution timeout after 300s
```
→ Step took too long, increase timeout or simplify

**JSON Parse Errors:**
```
Invalid JSON in AI response
```
→ AI didn't return valid JSON, adjust prompt or use text format

### Resolution Steps

1. Check error message for specific issue
2. Review workflow configuration
3. Test workflow with sample content
4. Verify API keys and assistants
5. Check post has required fields

## Monitoring

### Healthy Workflow Indicators
- ✅ High success rate (>95%)
- ✅ Consistent execution times
- ✅ No timeout errors
- ✅ All steps completing

### Warning Signs
- ⚠️ Frequent failures
- ⚠️ Increasing execution times
- ⚠️ Timeout errors
- ⚠️ Skipped steps

## Debugging

**To debug a failing workflow:**

1. Find failed execution in logs
2. Review step-by-step results
3. Check error messages
4. Test workflow with same post
5. Simplify if needed
6. Check documentation

**Enable debug mode:**
```php
// wp-config.php
define('POLYTRANS_DEBUG', true);
```

This adds detailed logging to **PolyTrans → Translation Logs**.

## Export Logs

**Export for analysis:**
- Click "Export" button
- Select date range
- Download as CSV

Includes all log data for external analysis.

## Best Practices

- Review logs weekly
- Set up alerts for high failure rates
- Archive old logs before deletion
- Document workflow changes in logs
- Test after configuration changes

## Log Data Structure

```json
{
  "workflow_id": 1,
  "post_id": 123,
  "status": "success",
  "duration": 4.2,
  "timestamp": "2025-10-07 12:00:00",
  "steps": [
    {
      "type": "ai_assistant",
      "status": "success",
      "duration": 3.8,
      "input": "...",
      "output": "...",
      "error": null
    }
  ],
  "output_actions": [
    {
      "type": "update_post_content",
      "status": "success",
      "before": "...",
      "after": "..."
    }
  ]
}
```

See [WORKFLOW-TRIGGERING.md](WORKFLOW-TRIGGERING.md) for workflow management.
