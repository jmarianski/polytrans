# PolyTrans API Documentation

## Overview

The PolyTrans plugin provides REST API endpoints for translation processing and workflow management. This document describes all available endpoints, request/response formats, and authentication methods.

**Base URL**: `{site_url}/wp-json/polytrans/v1/`

## Authentication

All API endpoints support multiple authentication methods, it is set by a user which type will be used:

### 1. Bearer Token (Recommended)
```bash
Authorization: Bearer your-secret-token
```

### 2. Custom Header
```bash
X-PolyTrans-Secret: your-secret-token
# or custom header name as configured
```

### 3. POST Parameter
```json
{
  "secret": "your-secret-token",
  // ... other parameters
}
```

### 4. GET Parameter
```
/wp-json/polytrans/v1/endpoint?secret=your-secret-token
```

## API Endpoints

### 1. Translation Processing

#### POST `/translation/translate`
Processes translation requests from external services.

**Request Body:**
```json
{
  "source_language": "en",
  "target_language": "es", 
  "original_post_id": 123,
  "target_endpoint": "https://receiver.example.com/wp-json/polytrans/v1/translation/receive-post",
  "toTranslate": {
    "title": "Article Title",
    "content": "Article content to translate...",
    "excerpt": "Article excerpt",
    "meta": {
      "rank_math_title": "SEO Title",
      "rank_math_description": "SEO Description"
      (...)
    }
  }
}
```

**Response (Success):**
```json
{
  "success": true,
  "translated": {
    "title": "Título del Artículo",
    "content": "Contenido del artículo traducido...",
    "excerpt": "Extracto del artículo",
    "meta": {
      "rank_math_title": "Título SEO",
      "rank_math_description": "Descripción SEO"
      (...)
    }
  },
  "source_language": "en",
  "target_language": "es"
}
```

**Response (Error):**
```json
{
  "error": "Error description",
  "code": "error_code"
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (missing parameters, invalid data)
- `401` - Unauthorized (invalid authentication)
- `403` - Forbidden (insufficient permissions)
- `500` - Internal Server Error

---

#### POST `/translation/receive-post`
Receives completed translations from external translation services.

**Request Body:**
```json
{
  "source_language": "en",
  "target_language": "es",
  "original_post_id": 123,
  "translated": {
    "title": "Título Traducido",
    "content": "Contenido traducido completo...",
    "excerpt": "Extracto traducido",
    "meta": {
      "rank_math_title": "Título SEO Traducido",
      "rank_math_description": "Descripción SEO Traducida"
      (...)
    }
  },
  "context_articles": [
    {
      "id": 456,
      "title": "Artículo de Contexto",
      "content": "Contenido del artículo de contexto...",
      "excerpt": "Extracto del contexto",
      "date": "2025-07-18 10:30:00",
      "url": "https://example.com/articulo-contexto",
      "categories": [
        {
          "id": 1,
          "name": "Tecnología",
          "slug": "tecnologia"
        }
      ],
      "tags": [
        {
          "id": 10,
          "name": "IA",
          "slug": "ia"
        }
      ]
    }
  ]
}
```

**Response (Success):**
```json
{
  "success": true,
  "post_id": 456,
  "post_url": "https://example.com/es/titulo-traducido",
  "message": "Translation received and post created successfully"
}
```

**Response (Error):**
```json
{
  "error": "Error description",
  "details": "Detailed error information"
}
```

---

### 2. Workflow Management (AJAX Endpoints)

#### POST `/wp-admin/admin-ajax.php?action=polytrans_test_workflow`
Tests workflow execution without making permanent changes.

**Request Body (Form Data):**
```
action=polytrans_test_workflow
workflow_id=123
post_id=456
nonce=abc123def456
```

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_results": [
      {
        "step_name": "AI Assistant Step",
        "step_type": "ai_assistant",
        "status": "success",
        "input": "Original content...",
        "output": "Processed content...",
        "tokens_used": 150,
        "processing_time": 2.3
      }
    ],
    "context": {
      "post_title": "Updated Title",
      "post_content": "Updated Content",
      "meta_description": "Updated Meta Description"
    },
    "actions_processed": 3,
    "total_processing_time": 5.2
  }
}
```

---

#### POST `/wp-admin/admin-ajax.php?action=polytrans_save_workflow`
Saves or updates a workflow configuration.

**Request Body (Form Data):**
```
action=polytrans_save_workflow
workflow_id=123 (optional, for updates)
workflow_name=My Workflow
workflow_description=Description of workflow
steps=[...] (JSON encoded array of steps)
output_actions=[...] (JSON encoded array of actions)
attribution_user=user_id (optional)
nonce=abc123def456
```

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_id": 123,
    "message": "Workflow saved successfully"
  }
}
```

---

### 3. Translation Scheduling (AJAX Endpoints)

#### POST `/wp-admin/admin-ajax.php?action=polytrans_schedule_translation`
Schedules translation for a post.

**Request Body (Form Data):**
```
action=polytrans_schedule_translation
post_id=123
scope=regional
targets[]=es
targets[]=fr
needs_review=1
nonce=abc123def456
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Translation scheduled (regional).",
    "targets": ["es", "fr"],
    "scheduled_langs": ["es", "fr"]
  }
}
```

---

## Data Structures

### Translation Payload Structure

When sending translation requests to external endpoints, the payload includes:

```json
{
  "source_language": "en",
  "target_language": "es",
  "original_post_id": 123,
  "target_endpoint": "https://receiver.example.com/wp-json/polytrans/v1/translation/receive-post",
  "toTranslate": {
    "title": "Source article title",
    "content": "Source article content...",
    "excerpt": "Source article excerpt",
    "meta": {
      "rank_math_title": "SEO Title",
      "rank_math_description": "SEO Description",
      "rank_math_facebook_title": "Facebook Title",
      "rank_math_facebook_description": "Facebook Description",
      "rank_math_twitter_title": "Twitter Title",
      "rank_math_twitter_description": "Twitter Description",
      "rank_math_focus_keyword": "focus keyword"
      (...)
    }
  },
  "context_articles": [
    {
      "id": 456,
      "title": "Recent Article Title in Target Language",
      "content": "Full article content...",
      "excerpt": "Article excerpt",
      "date": "2025-07-18 10:30:00",
      "url": "https://example.com/recent-article",
      "categories": [
        {
          "id": 1,
          "name": "Category Name",
          "slug": "category-slug"
        }
      ],
      "tags": [
        {
          "id": 10,
          "name": "Tag Name", 
          "slug": "tag-slug"
        }
      ]
    }
  ]
}
```

### Workflow Step Structure

```json
{
  "step_id": "unique_step_id",
  "step_name": "Human Readable Step Name",
  "step_type": "ai_assistant",
  "enabled": true,
  "config": {
    "model": "gpt-4",
    "system_prompt": "You are a helpful assistant...",
    "user_prompt": "Process this content: {post_content}",
    "temperature": 0.7,
    "max_tokens": 2000
  }
}
```

### Workflow Output Action Structure

```json
{
  "action_type": "update_post_field",
  "target_field": "post_content",
  "source_step": "step_id",
  "enabled": true
}
```

## Error Handling

### Common Error Responses

**400 Bad Request:**
```json
{
  "error": "Missing required parameter: target_endpoint",
  "code": "missing_parameter"
}
```

**401 Unauthorized:**
```json
{
  "error": "Invalid authentication credentials",
  "code": "invalid_auth"
}
```

**403 Forbidden:**
```json
{
  "error": "Insufficient permissions",
  "code": "insufficient_permissions"
}
```

**500 Internal Server Error:**
```json
{
  "error": "Translation provider not configured",
  "code": "provider_error",
  "details": "OpenAI API key not set"
}
```

## Rate Limiting

The API does not implement built-in rate limiting, but endpoints may be subject to:
- WordPress's built-in request limits
- Server-level rate limiting
- Translation provider API limits (OpenAI, Google Translate)

## Content Types

All REST API endpoints:
- **Accept**: `application/json`
- **Content-Type**: `application/json`

AJAX endpoints:
- **Accept**: `application/json`
- **Content-Type**: `application/x-www-form-urlencoded`

## Security Considerations

1. **IP Restrictions**: Can be configured to restrict access by IP address
2. **Secret Validation**: All endpoints validate authentication secrets
3. **Nonce Validation**: AJAX endpoints require valid WordPress nonces
4. **Input Sanitization**: All input is sanitized and validated
5. **Permission Checks**: User capabilities are verified for administrative functions

## Testing

### Test Endpoint Availability
```bash
curl -X GET https://yoursite.com/wp-json/polytrans/v1/
```

### Test Translation Endpoint
```bash
curl -X POST https://yoursite.com/wp-json/polytrans/v1/translation/translate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-secret-token" \
  -d '{
    "source_language": "en",
    "target_language": "es",
    "original_post_id": 123,
    "target_endpoint": "https://receiver.example.com/wp-json/polytrans/v1/translation/receive-post",
    "toTranslate": {
      "title": "Test Title",
      "content": "Test content to translate"
    }
  }'
```

### Test Receive Endpoint
```bash
curl -X POST https://yoursite.com/wp-json/polytrans/v1/translation/receive-post \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-secret-token" \
  -d '{
    "source_language": "en",
    "target_language": "es",
    "original_post_id": 123,
    "translated": {
      "title": "Título de Prueba",
      "content": "Contenido de prueba traducido"
    }
  }'
```
