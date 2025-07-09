# PolyTrans Plugin

A comprehensive WordPress plugin for managing multilingual content translation workflows with AI-powered translation support.

PS. The description below may be bullshit as AI wrote it, let me check it later.

## Features

### Core Translation Management
- **Translation Meta Box**: Mark posts as human or machine translated
- **Translation Scheduler**: Schedule automatic translations to multiple languages
- **Translation Status Tracking**: Monitor translation progress and logs
- **Review Workflow**: Assign reviewers for translation quality control

### Advanced Translation Receiver Architecture
- **Modular Design**: Separated concerns with specialized manager classes
- **Translation Coordinator**: Orchestrates the entire translation process
- **Request Validation**: Comprehensive validation of incoming translation requests
- **Post Creation**: Robust post creation with proper sanitization
- **Metadata Management**: Intelligent copying and translation of post metadata
- **Taxonomy Management**: Automatic category and tag translation mapping
- **Language Management**: Polylang integration with translation relationships
- **Notification System**: Email notifications for reviewers and authors
- **Status Tracking**: Detailed status management and logging
- **Security Management**: IP restrictions and authentication validation

### Translation Providers
- **Google Translate**: Simple, fast machine translation
- **OpenAI Integration**: AI-powered translation with custom assistants
  - API key validation
  - Custom assistant mapping per language pair
  - Intermediate translation support

### Advanced Features
- **REST API Endpoints**: Receive translated content from external services
- **Email Notifications**: Notify reviewers and authors
- **Tag Translation Management**: Manage multilingual taxonomy translations
- **User Assignment**: Autocomplete user search for reviewer assignment
- **Security**: Configurable secret authentication for translation endpoints

### Language Support
- **Polylang Integration**: Full compatibility with Polylang plugin
- **Flexible Language Configuration**: Configure allowed source and target languages
- **Status Management**: Set post status after translation (publish, draft, pending, same as source)

## Installation

1. Upload the plugin files to `/wp-content/plugins/polytrans-translation/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under **Settings > Translation Settings**

## Configuration

### Basic Setup
1. Go to **Settings > Translation Settings**
2. Choose your translation provider (Google Translate or OpenAI)
3. Configure allowed source and target languages
4. Set up post status and reviewer settings for each language

### OpenAI Configuration
1. Select "OpenAI" as translation provider
2. Enter your OpenAI API key
3. Choose your primary OpenAI language
4. Map assistants to language pairs
5. Configure translation workflow

### Advanced Settings
1. Set up translation endpoint URLs
2. Configure receiver endpoint for external translation services
3. Set up authentication secrets
4. Customize email templates

## Usage

### Scheduling Translations
1. Edit any post or page
2. Use the "Auto Translation Scheduler" meta box
3. Select translation scope (Local, Regional, Global)
4. Choose target languages for Regional scope
5. Enable review if needed
6. Click "Translate"

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

### Receive Translation
- **Endpoint**: `/wp-json/polytrans/v1/translation/receive-post`
- **Method**: POST
- **Authentication**: Configurable secret
- **Purpose**: Receive completed translations from external services

### Get Translation Status
- **Endpoint**: `/wp-json/polytrans/v1/translation/status/{post_id}`
- **Method**: GET
- **Authentication**: User login required
- **Purpose**: Check translation status for a specific post

## Hooks and Filters

The plugin provides various WordPress hooks for customization:

### Actions
- `polytrans_translation_before_create`: Before creating translated post
- `polytrans_translation_after_create`: After creating translated post
- `polytrans_translation_status_updated`: When translation status changes

### Filters
- `polytrans_translation_allowed_meta_keys`: Modify allowed meta keys for translation
- `polytrans_translation_post_data`: Modify post data before creating translation
- `polytrans_translation_email_content`: Customize notification email content

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Polylang plugin (recommended for full multilingual support)

## Changelog

### Version 1.0.0
- Initial release
- Google Translate integration
- OpenAI integration with custom assistants
- Translation scheduling and status tracking
- Review workflow with email notifications
- Tag translation management
- REST API endpoints
- Comprehensive admin interface

## Support

For support and feature requests, please contact the Polytrans development team.

## License

This plugin is proprietary software developed for the Polytrans platform.
