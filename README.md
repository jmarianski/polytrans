# PolyTrans Plugin

A comprehensive WordPress plugin for managing multilingual content translation workflows with AI-powered translation support.

PS. The description below may be bullshit as AI wrote it, let me check it later.

## Features

### Core Translation Management
- **Translation Meta Box**: Mark posts as human or machine translated
- **Translation Scheduler**: Schedule automatic translations to multiple languages
- **Translation Status Tracking**: Monitor translation progress and logs
- **Review Workflow**: Assign reviewers for translation quality control

### Translation Provider System
The plugin uses a hot-pluggable provider architecture that supports:

- **Google Translate**: Simple, fast machine translation using public API (no API key required)
- **OpenAI Integration**: AI-powered translation with custom assistants
  - API key configuration required
  - Custom assistant mapping per language pair
  - Multi-step translation support for complex language workflows

### Advanced Translation Architecture
- **Modular Receiver System**: Specialized manager classes for different aspects of translation processing
- **Translation Coordinator**: Orchestrates the entire translation process
- **Request Validation**: Comprehensive validation of incoming translation requests
- **Post Creation**: Robust post creation with proper sanitization
- **Metadata Management**: Intelligent copying and translation of post metadata
- **Taxonomy Management**: Automatic category and tag translation mapping
- **Language Management**: Polylang integration with translation relationships
- **Security**: IP restrictions and authentication validation

### Communication Features
- **REST API Endpoints**: Receive translated content from external services
- **Email Notifications**: Notify reviewers and authors throughout the workflow
- **User Assignment**: Autocomplete user search for reviewer assignment

### Additional Features
- **Tag Translation Management**: Manage multilingual taxonomy translations
- **Polylang Integration**: Full compatibility with Polylang plugin
- **Flexible Language Configuration**: Configure allowed source and target languages
- **Status Management**: Set post status after translation (publish, draft, pending, same as source)

## Architecture

### Multi-Server Support
The plugin supports both single-server and multi-server translation workflows:

1. **Single Server (Local)**: All translation processing happens on the same WordPress installation
2. **Multi-Server**: Translation work can be distributed across multiple WordPress installations
   - **Scheduler Server**: Manages translation requests
   - **Translator Server**: Performs actual translation work
   - **Receiver Server**: Processes completed translations

### Provider System
The plugin implements a hot-pluggable provider architecture:
- **Translation Provider Interface**: Defines core translation functionality
- **Settings Provider Interface**: Handles provider-specific configuration UI
- **Provider Registry**: Automatic discovery and registration of translation providers
- **Dynamic Settings UI**: Providers automatically appear in settings with their own configuration tabs

## Installation

1. Upload the plugin files to `/wp-content/plugins/polytrans/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under **Settings > Translation Settings**

## Configuration

### Basic Setup
1. Go to **Settings > Translation Settings**
2. Choose your translation provider (Google Translate or OpenAI)
3. Configure allowed source and target languages
4. Set up post status and reviewer settings for each language

### Google Translate Configuration
- **No setup required** - Google Translate works out of the box using the public API
- Simply select "Google Translate" as your provider

### OpenAI Configuration
1. Select "OpenAI" as translation provider
2. Enter your OpenAI API key
3. Choose your primary OpenAI language
4. Map assistants to language pairs (e.g., "en_to_pl" → "asst_123")
5. Test your configuration using the built-in testing interface

### Multi-Server Setup
1. Configure translation endpoint URLs for external translation services
2. Set up receiver endpoint for incoming translated content
3. Configure authentication secrets and methods
4. Customize email templates for notifications

## Usage

### Scheduling Translations
1. Edit any post or page
2. Use the "Translation" meta box to mark translation type
3. Use the "Translation Scheduler" meta box to:
   - Select translation scope (Local, Regional, Global)
   - Choose target languages for Regional scope
   - Enable review if needed
   - Click "Translate"

### Managing Tag Translations
1. Go to **Posts > Tag Translations**
2. Add tags to translate in the tag list
3. Map translations for each language
4. Export/import CSV for bulk management

### Reviewing Translations
1. Reviewers receive email notifications when translations are ready
2. Edit the translated post to review content
3. Publish or update status as needed
4. Original author receives notification when published

## REST API Endpoints

### Translation Endpoints
- **POST** `/wp-json/polytrans/v1/translation/translate` - Receive and process translation requests
- **POST** `/wp-json/polytrans/v1/translation/receive-post` - Receive completed translations
- **GET** `/wp-json/polytrans/v1/translation/status/{post_id}` - Check translation status

### Authentication
All endpoints support configurable authentication:
- Bearer token in Authorization header
- Custom header (x-polytrans-secret)
- POST parameter

## Hooks and Filters

### Actions
- `polytrans_translation_before_create` - Before creating translated post
- `polytrans_translation_after_create` - After creating translated post
- `polytrans_translation_status_updated` - When translation status changes

### Filters
- `polytrans_register_providers` - Register custom translation providers
- `polytrans_translation_allowed_meta_keys` - Modify allowed meta keys for translation
- `polytrans_translation_post_data` - Modify post data before creating translation
- `polytrans_translation_email_content` - Customize notification email content

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Polylang plugin (recommended for full multilingual support)
- OpenAI API key (only if using OpenAI provider)

## License

This plugin is proprietary software developed for the PolyTrans platform.
