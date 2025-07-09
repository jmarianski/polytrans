# PolyTrans Plugin Architecture

This document describes the modular architecture of the PolyTrans plugin.

## Directory Structure

```
polytrans/
├── polytrans.php                    # Main plugin file
├── includes/
│   ├── class-polytrans.php         # Main plugin class
│   ├── api/                         # REST API endpoints
│   │   └── class-translation-api.php
│   ├── core/                        # Core WordPress integration
│   │   ├── class-translation-meta-box.php
│   │   ├── class-translation-notifications.php
│   │   ├── class-tag-translation.php
│   │   └── class-user-autocomplete.php
│   ├── translator/                  # Translation providers
│   │   ├── class-google-translate-integration.php
│   │   ├── class-openai-integration.php
│   │   └── class-openai-settings-ui.php
│   ├── scheduler/                   # Translation scheduling
│   │   ├── class-translation-scheduler.php
│   │   └── class-translation-handler.php
│   ├── settings/                    # Admin settings interface
│   │   └── class-translation-settings.php
│   └── receiver/                    # Translation receiver architecture
│       ├── class-translation-coordinator.php
│       ├── class-translation-receiver-extension.php
│       └── managers/
│           ├── class-translation-request-validator.php
│           ├── class-translation-post-creator.php
│           ├── class-translation-metadata-manager.php
│           ├── class-translation-taxonomy-manager.php
│           ├── class-translation-language-manager.php
│           ├── class-translation-notification-manager.php
│           ├── class-translation-status-manager.php
│           └── class-translation-security-manager.php
├── assets/
│   ├── js/                          # JavaScript files
│   │   ├── core/                    # Core WordPress integration JS
│   │   │   ├── tag-translation-admin.js
│   │   │   └── user-autocomplete.js
│   │   ├── translator/              # Translation provider JS
│   │   │   └── openai-integration.js
│   │   ├── scheduler/               # Translation scheduling JS
│   │   │   └── translation-scheduler.js
│   │   └── settings/                # Admin settings JS
│   │       └── translation-settings-admin.js
│   └── css/                         # CSS files
│       ├── core/                    # Core WordPress integration CSS
│       │   └── tag-translation-admin.css
│       ├── translator/              # Translation provider CSS
│       │   └── openai-integration.css
│       ├── scheduler/               # Translation scheduling CSS
│       │   └── translation-scheduler.css
│       └── settings/                # Admin settings CSS
│           └── translation-settings-admin.css
└── languages/                       # Translation files
    └── polytrans.pot
```

## Module Descriptions

### Core (`/core/`)
WordPress integration components that handle UI elements and basic functionality:
- **Translation Meta Box**: Post editor meta box for translation controls
- **Translation Notifications**: Email notification system
- **Tag Translation**: Tag translation management
- **User Autocomplete**: User search and selection functionality

### Translator (`/translator/`)
Translation service providers and their integrations:
- **Google Translate Integration**: Direct Google Translate API integration
- **OpenAI Integration**: OpenAI GPT-based translation with custom assistants
- **OpenAI Settings UI**: Admin interface for OpenAI configuration

### Scheduler (`/scheduler/`)
Translation job scheduling and management:
- **Translation Scheduler**: Main scheduling interface and controls
- **Translation Handler**: Processes translation requests and coordinates with providers

### Settings (`/settings/`)
Admin interface and configuration management:
- **Translation Settings**: Main admin settings page with tabbed interface

### Receiver (`/receiver/`)
Advanced translation receiver architecture for processing completed translations:
- **Translation Coordinator**: Orchestrates the entire translation processing pipeline
- **Translation Receiver Extension**: REST API endpoint with security validation
- **Managers**: Specialized classes handling different aspects:
  - Request validation
  - Post creation
  - Metadata management
  - Taxonomy handling
  - Language assignment
  - Notifications
  - Status tracking
  - Security validation

### API (`/api/`)
REST API endpoints for external integration:
- **Translation API**: REST endpoints for receiving translations and status queries

### Assets (`/assets/`)
Frontend resources organized by functionality:
- **Core assets**: JavaScript and CSS for WordPress integration features (meta boxes, user autocomplete, tag translation)
- **Translator assets**: Frontend code for translation provider interfaces (OpenAI integration)
- **Scheduler assets**: UI for translation scheduling functionality
- **Settings assets**: Admin interface styling and interaction

This modular organization ensures that assets are loaded only when needed and makes the codebase easier to maintain.

## Benefits of This Architecture

1. **Separation of Concerns**: Each module handles a specific aspect of functionality
2. **Maintainability**: Easy to locate and modify specific features
3. **Extensibility**: New translation providers can be added to `/translator/`
4. **Testability**: Individual modules can be tested independently
5. **Code Organization**: Related functionality is grouped together
6. **Scalability**: Easy to add new modules or extend existing ones

## Adding New Translation Providers

To add a new translation provider:

1. Create a new class in `/translator/` following the naming convention
2. Implement the required methods for translation
3. Add the provider to the settings interface
4. Update the translation handler to support the new provider

## Development Guidelines

- Follow WordPress coding standards
- Use the `PolyTrans_` prefix for all class names
- Document all public methods
- Handle errors gracefully with proper logging
- Validate and sanitize all inputs
- Use WordPress hooks and filters where appropriate
