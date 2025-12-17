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

### POST /translation/translate
Receive and process translation requests (for multi-server setups).

**Request:**
```json
{
  "post_id": 123,
  "target_language": "es",
  "source_language": "en",
  "provider": "openai",
  "scope": "regional"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Translation request received",
  "task_id": "task_abc123"
}
```

**Note**: This endpoint is primarily for multi-server setups where translation work is distributed. For single-server setups, translations are triggered via the WordPress admin interface.

### POST /translation/receive-post
Receive completed translations from external translation services.

**Request:**
```json
{
  "post_id": 123,
  "target_language": "es",
  "translated_content": {
    "post_title": "Título traducido",
    "post_content": "Contenido traducido...",
    "post_excerpt": "Resumen traducido..."
  },
  "metadata": {
    "translation_provider": "openai",
    "translation_time": "2025-12-16T10:00:00Z"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Translation received and processed",
  "translated_post_id": 456
}
```

**Note**: This endpoint is used in multi-server setups where a separate translation server sends completed translations back to the main server.

---

## ⚠️ Note on API Endpoints

The plugin currently focuses on WordPress admin interface for most operations. REST API endpoints are primarily designed for:
- **Multi-server setups** - Distributed translation workflows
- **External integrations** - Custom translation services

For single-server setups, use the WordPress admin interface:
- **Translation**: Edit post → Translation Scheduler meta box
- **Workflows**: PolyTrans → Post-Processing
- **Status**: PolyTrans → Translation Logs

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

### Request Translation (Multi-Server Setup)
```php
$response = wp_remote_post('https://translator-server.com/wp-json/polytrans/v1/translation/translate', [
    'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode([
        'post_id' => 123,
        'target_language' => 'es',
        'source_language' => 'en',
        'provider' => 'openai'
    ])
]);

$result = json_decode(wp_remote_retrieve_body($response), true);
```

### Receive Completed Translation
```php
$response = wp_remote_post('https://main-server.com/wp-json/polytrans/v1/translation/receive-post', [
    'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode([
        'post_id' => 123,
        'target_language' => 'es',
        'translated_content' => [
            'post_title' => 'Título traducido',
            'post_content' => 'Contenido traducido...'
        ]
    ])
]);

$result = json_decode(wp_remote_retrieve_body($response), true);
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
