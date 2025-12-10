# OpenAI Error Logging Improvements

**Version**: 1.4.0 (Phase 1)
**Date**: 2025-12-10

## Overview

Improved error logging for OpenAI translation failures to include detailed error codes and messages, making debugging much easier.

## Problem

Previously, translation errors showed minimal information:
```
OpenAI: step 0 failed (de -> en): Run ended with status: failed
```

This made it impossible to diagnose the actual issue (rate limiting, insufficient funds, timeout, server error, etc.).

## Solution

Enhanced error extraction from OpenAI API responses to include:
- **Error Code** - Specific error type from OpenAI
- **Error Message** - Human-readable description
- **Additional Context** - Thread ID, run ID, language pair, etc.

## Error Codes

Common OpenAI error codes you'll see in logs:

| Error Code | Meaning | Solution |
|------------|---------|----------|
| `rate_limit_exceeded` | Too many requests | Wait and retry, or upgrade plan |
| `insufficient_quota` | Account out of credits | Add credits to OpenAI account |
| `server_error` | OpenAI API issue | Retry later, OpenAI is having issues |
| `invalid_request_error` | Bad request format | Check assistant configuration |
| `authentication_error` | Invalid API key | Verify API key in settings |
| `timeout` | Request took too long | Reduce content size or adjust timeout |
| `incomplete_*` | Run didn't finish | Check run details in OpenAI dashboard |

## New Log Format

### Translation Step Failure

**Before:**
```
OpenAI: step 0 failed (de -> en): Run ended with status: failed
```

**After:**
```
OpenAI: step 0 failed (de -> en) [code: rate_limit_exceeded]: Run ended with status: failed - Rate limit reached for requests
```

With additional structured data:
```json
{
  "step": 0,
  "source_lang": "de",
  "target_lang": "en",
  "error_code": "rate_limit_exceeded",
  "error_details": {
    "code": "rate_limit_exceeded",
    "message": "Rate limit reached for requests",
    "raw": {
      "code": "rate_limit_exceeded",
      "message": "Rate limit reached for requests"
    }
  }
}
```

### Run Status Failure

**Before:**
```
OpenAI run status: failed (en -> pl)
```

**After:**
```
OpenAI run status: failed [code: insufficient_quota] (en -> pl): Run ended with status: failed - You exceeded your current quota
```

With additional structured data:
```json
{
  "source_lang": "en",
  "target_lang": "pl",
  "status": "failed",
  "error_code": "insufficient_quota",
  "error_details": {
    "code": "insufficient_quota",
    "message": "You exceeded your current quota",
    "raw": {...}
  },
  "thread_id": "thread_abc123",
  "run_id": "run_xyz789"
}
```

## Implementation Details

### Files Modified

1. **`includes/providers/openai/class-openai-client.php`**
   - Added `extract_run_error_details()` method
   - Enhanced `wait_for_run_completion()` to extract error details
   - Returns `error_code` and `error_details` in response

2. **`includes/providers/openai/class-openai-provider.php`**
   - Updated step failure logging (line ~159)
   - Updated run status logging (line ~280)
   - Passes error_code through return values

3. **`includes/postprocessing/steps/class-predefined-assistant-step.php`**
   - Updated to pass error_code from completion_response

### Error Extraction Logic

The `extract_run_error_details()` method checks multiple places for error information:

1. **`last_error`** - Primary OpenAI error field (for failed/cancelled runs)
   ```json
   {
     "last_error": {
       "code": "rate_limit_exceeded",
       "message": "Rate limit reached"
     }
   }
   ```

2. **`incomplete_details`** - For runs that didn't complete
   ```json
   {
     "incomplete_details": {
       "reason": "max_completion_tokens"
     }
   }
   ```

3. **`error`** - General error field (fallback)
   ```json
   {
     "error": {
       "code": "invalid_request",
       "message": "Invalid parameter"
     }
   }
   ```

## Usage Examples

### Viewing Detailed Errors in Logs

**In Database** (`wp_polytrans_logs` table):
```sql
SELECT message, context
FROM wp_polytrans_logs
WHERE level = 'error'
AND message LIKE '%OpenAI%failed%'
ORDER BY created_at DESC
LIMIT 10;
```

**In PHP Debug Log** (`wp-content/debug.log`):
```
[10-Dec-2025 12:00:00 UTC] OpenAI: step 0 failed (en -> pl) [code: insufficient_quota]: Run ended with status: failed - You exceeded your current quota
```

### Debugging Specific Error Codes

#### Rate Limiting
```
Error Code: rate_limit_exceeded
Solution: Wait a few minutes and retry, or upgrade your OpenAI plan
```

#### Insufficient Quota
```
Error Code: insufficient_quota
Solution: Add credits to your OpenAI account at https://platform.openai.com/account/billing
```

#### Server Errors
```
Error Code: server_error
Solution: OpenAI API is having issues. Check https://status.openai.com/ and retry later
```

## Benefits

1. **Faster Debugging** - Immediately see what went wrong
2. **Better Monitoring** - Track error patterns (rate limits, quota issues)
3. **Actionable Information** - Know exactly what to fix
4. **Reduced Support Time** - Users can self-diagnose issues
5. **Historical Analysis** - Query logs for error trends

## Testing

To test error logging improvements:

1. **Trigger Rate Limit** - Send many requests rapidly
2. **Trigger Insufficient Quota** - Use an account with no credits
3. **Trigger Invalid Request** - Use non-existent assistant ID
4. **Check Logs** - Verify detailed error codes appear

## Future Improvements

- [ ] Add retry logic for specific error codes (rate_limit, server_error)
- [ ] Dashboard widget showing error frequency by code
- [ ] Email alerts for critical errors (insufficient_quota)
- [ ] Automatic API key validation on settings save

## References

- OpenAI API Errors: https://platform.openai.com/docs/guides/error-codes
- OpenAI Run Object: https://platform.openai.com/docs/api-reference/runs/object
- PolyTrans Logs: `wp_polytrans_logs` table

---

*Last updated: 2025-12-10*
