# PolyTrans Translation Architecture

## Overview

The PolyTrans plugin now supports a complete 3-server translation architecture:

1. **Scheduler Server** - Schedules translations and manages the workflow
2. **Translator Server** - Performs the actual translation work
3. **Receiver Server** - Receives and processes translated content

These can all be the same machine, or separate machines for scalability.

## Components

### 1. Translation Scheduler (`class-translation-handler.php`)
- Schedules translation requests
- Can handle translations **locally** (direct integration) or **remotely** (via API)
- Supports both Google Translate and OpenAI providers
- Manages translation status and logging

### 2. Translation Extension (`class-translation-extension.php`) - **NEW**
- **THIS WAS THE MISSING PIECE**
- Provides REST API endpoint: `POST /wp-json/polytrans/v1/translation/translate`
- Receives translation requests from other servers
- Performs translation using configured provider (Google/OpenAI)
- Sends results back to the requesting server

### 3. Translation Receiver (`class-translation-receiver-extension.php`)
- Receives translated content from translator servers
- Creates new posts in the target language
- Handles metadata, taxonomies, and notifications

### 4. Translation Providers
- **Google Translate Integration** - Uses public Google Translate API
- **OpenAI Integration** - Uses OpenAI API with assistants (enhanced with translation interface)

## Workflows

### Local Translation (Single Server) - Async
```
User clicks "Translate" 
→ Scheduler sends HTTP request to own /translation/translate endpoint (async)
→ Translation Extension receives request and translates using Google/OpenAI
→ Translation Extension sends result to own /translation/receive-post endpoint
→ Receiver creates post and sends notifications
```

### Remote Translation (Multi-Server) - Async
```
Server A (Scheduler):
User clicks "Translate" 
→ Scheduler sends async HTTP request to Server B's /translation/translate endpoint

Server B (Translator):
→ Translation Extension receives request
→ Calls configured provider (Google/OpenAI)
→ Sends translated content to Server A's /translation/receive-post endpoint

Server A (Receiver):
→ Translation Receiver processes translated content
→ Creates new post and sends notifications
```

## Key Benefits of Async Architecture

- **Non-blocking UI**: User interface doesn't freeze during translation
- **Scalable**: Can handle multiple translation requests simultaneously
- **Consistent**: Same HTTP-based workflow for local and remote translations
- **Reliable**: Failed requests can be retried and logged properly
- **Fire-and-forget**: Scheduler doesn't wait for translation completion

## Configuration

### Scheduler Settings (polytrans_settings)
- `translation_provider`: 'google' or 'openai'
- `translation_endpoint`: URL of remote translator server (optional)
- `translation_receiver_endpoint`: URL where results should be sent back
- `translation_receiver_secret`: Security token
- `translation_receiver_secret_method`: Authentication method

### Example Multi-Server Setup

**Server A (Scheduler + Receiver):**
```php
$settings = [
    'translation_provider' => 'google',
    'translation_endpoint' => 'https://serverb.com/wp-json/polytrans/v1/translation/translate',
    'translation_receiver_endpoint' => 'https://servera.com/wp-json/polytrans/v1/translation/receive-post',
    'translation_receiver_secret' => 'your-secret-key',
    'translation_receiver_secret_method' => 'header_bearer'
];
```

**Server B (Translator):**
```php
$settings = [
    'translation_provider' => 'google', // or 'openai'
    'translation_receiver_secret' => 'your-secret-key',
    'translation_receiver_secret_method' => 'header_bearer'
];
```

### Single Server Setup (Local Async)

**Server (All-in-one):**
```php
$settings = [
    'translation_provider' => 'google', // or 'openai'
    'translation_receiver_secret' => 'your-secret-key',
    'translation_receiver_secret_method' => 'header_bearer'
    // No translation_endpoint needed - will auto-use local endpoints
];
```

When `translation_endpoint` is empty or invalid, the system automatically uses local endpoints for async processing.

## REST API Endpoints

### `/wp-json/polytrans/v1/translation/translate` (NEW)
**Purpose:** Receive and process translation requests from other servers

**Method:** POST

**Request Body:**
```json
{
    "source_language": "pl",
    "target_language": "en",
    "original_post_id": 123,
    "target_endpoint": "https://servera.com/wp-json/polytrans/v1/translation/receive-post",
    "toTranslate": {
        "title": "Post title",
        "content": "Post content",
        "excerpt": "Post excerpt",
        "meta": {
            "rank_math_title": "SEO title"
        }
    }
}
```

**Authentication:** Uses configured secret method (Bearer token, custom header, etc.)

### `/wp-json/polytrans/v1/translation/receive-post` (Existing)
**Purpose:** Receive translated content and create posts

**Method:** POST

**Request Body:**
```json
{
    "source_language": "pl",
    "target_language": "en", 
    "original_post_id": 123,
    "translated": {
        "title": "Translated title",
        "content": "Translated content",
        "excerpt": "Translated excerpt",
        "meta": {
            "rank_math_title": "Translated SEO title"
        }
    }
}
```

## Security

- All endpoints are protected with configurable secrets
- Multiple authentication methods supported:
  - Bearer token in Authorization header
  - Custom header (x-polytrans-secret)
  - GET/POST parameter
- SSL verification enforced in production environments

## Translation Providers

### Google Translate
- Uses public Google Translate API
- Always available (no API key required)
- Supports 100+ languages
- Handles JSON structures and individual strings

### OpenAI
- Requires API key and configured assistants
- Supports intermediate translation via Google for unsupported language pairs
- More contextual and accurate translations
- Currently falls back to Google Translate (can be enhanced with full OpenAI implementation)

## Migration from TransInfo

The key missing piece was the **Translation Extension** that provides the `/translation/translate` endpoint. This allows one server to send translation requests to another server, which was the core functionality needed to complete the 3-server architecture.

The polytrans plugin now has feature parity with the transinfo plugin for translation workflows.
