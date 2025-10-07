# PolyTrans API

## Base URL
`{site_url}/wp-json/polytrans/v1/`

## Authentication

**Bearer Token (Recommended):**
```bash
Authorization: Bearer your-secret-token
```

**Custom Header:**
```bash
X-PolyTrans-Secret: your-secret-token
```

## Endpoints

### POST /translate
Translate a post

**Request:**
```json
{
  "post_id": 123,
  "target_language": "es",
  "scope": "regional"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Translation started",
  "task_id": "task_abc123"
}
```

### GET /status/{task_id}
Check translation status

**Response:**
```json
{
  "status": "completed|processing|failed",
  "post_id": 456,
  "progress": 100
}
```

### GET /translations/{post_id}
Get all translations for a post

**Response:**
```json
{
  "translations": {
    "es": 456,
    "fr": 789
  }
}
```

### POST /workflow/execute
Execute a workflow on a post

**Request:**
```json
{
  "workflow_id": 1,
  "post_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "execution_id": "exec_abc123",
  "status": "running"
}
```

### GET /workflows
List available workflows

**Response:**
```json
{
  "workflows": [
    {
      "id": 1,
      "name": "SEO Optimization",
      "target_language": "es"
    }
  ]
}
```

## Error Responses

```json
{
  "success": false,
  "code": "invalid_post_id",
  "message": "Post not found"
}
```

**Common Error Codes:**
- `invalid_post_id` - Post doesn't exist
- `unsupported_language` - Language not configured
- `translation_failed` - Translation provider error
- `workflow_not_found` - Workflow doesn't exist
- `unauthorized` - Invalid authentication

## Webhooks

Register webhook URLs to receive notifications:

**Settings → API Settings → Webhooks**

**Events:**
- `translation.completed` - Translation finished
- `translation.failed` - Translation error
- `workflow.completed` - Workflow finished

**Payload Example:**
```json
{
  "event": "translation.completed",
  "post_id": 123,
  "translation_id": 456,
  "language": "es",
  "timestamp": "2025-10-07T12:00:00Z"
}
```

## Rate Limits

- Default: 60 requests/minute
- Configurable per API key
- Headers returned:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset`

## PHP Examples

### Translate a Post
```php
$response = wp_remote_post('https://example.com/wp-json/polytrans/v1/translate', [
    'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode([
        'post_id' => 123,
        'target_language' => 'es'
    ])
]);

$result = json_decode(wp_remote_retrieve_body($response), true);
```

### Check Status
```php
$response = wp_remote_get(
    'https://example.com/wp-json/polytrans/v1/status/' . $task_id,
    ['headers' => ['Authorization' => 'Bearer ' . $api_key]]
);

$status = json_decode(wp_remote_retrieve_body($response), true);
```

## Hooks for Developers

```php
// Before translation
add_filter('polytrans_pre_translate', function($content, $lang) {
    return $content;
}, 10, 2);

// After translation
add_action('polytrans_translation_complete', function($post_id, $lang) {
    // Custom logic
}, 10, 2);

// Modify workflow context
add_filter('polytrans_workflow_context', function($context, $workflow) {
    return $context;
}, 10, 2);
```

See source code for full hook reference.
