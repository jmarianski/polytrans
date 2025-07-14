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
│   ├── providers/                   # Translation provider system
│   │   ├── interface-translation-provider.php
│   │   ├── interface-settings-provider.php
│   │   ├── class-provider-registry.php
│   │   ├── google/
│   │   │   └── class-google-provider.php
│   │   └── openai/
│   │       ├── class-openai-provider.php
│   │       ├── class-openai-settings-provider.php
│   │       └── class-openai-settings-ui.php
│   ├── translator/                  # Translation extension
│   │   └── class-translation-extension.php
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

### Providers (`/providers/`)
Hot-pluggable translation provider system:
- **Translation Provider Interface**: Defines core translation functionality
- **Settings Provider Interface**: Handles provider-specific configuration UI
- **Provider Registry**: Central registry for all translation providers
- **Google Provider**: Google Translate integration (no configuration needed)
- **OpenAI Provider**: OpenAI GPT translation with custom assistant support

### Translator (`/translator/`)
Translation service extension:
- **Translation Extension**: Handles incoming translation requests via REST API

### Scheduler (`/scheduler/`)
Translation job scheduling and management:
- **Translation Scheduler**: Main scheduling interface and controls
- **Translation Handler**: Processes translation requests and coordinates with providers

### Settings (`/settings/`)
Admin interface and configuration management:
- **Translation Settings**: Main admin settings page with dynamic provider tabs

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

## Hot-Pluggable Provider Architecture

The plugin features a completely hot-pluggable translation provider system that separates translation functionality from settings management.

### 1. Translation Provider Interface
- **File**: `includes/providers/interface-translation-provider.php`
- **Purpose**: Defines pure translation functionality
- **Key Methods**:
  - `get_id()` - Unique provider identifier
  - `get_name()` / `get_description()` - Display information
  - `translate()` - Core translation functionality
  - `is_configured()` - Check if provider is ready to use
  - `get_settings_provider_class()` - Points to settings UI class (or null)

### 2. Settings Provider Interface
- **File**: `includes/providers/interface-settings-provider.php`
- **Purpose**: Handles all settings UI, validation, and asset management
- **Key Methods**:
  - `render_settings_ui()` - Render provider-specific settings
  - `validate_settings()` - Sanitize and validate POST data
  - `get_required_js_files()` / `get_required_css_files()` - Asset dependencies
  - `enqueue_assets()` - Load scripts and styles
  - `is_configured()` / `get_configuration_status()` - Validation feedback

### 3. Provider Registry
- **File**: `includes/providers/class-provider-registry.php`
- **Purpose**: Central registry for all translation providers
- **Features**:
  - Automatic loading of built-in providers
  - WordPress filter `polytrans_register_providers` for third-party registration
  - Dynamic provider discovery and tab generation

### Built-in Providers

#### Google Translate Provider
- **Translation**: `includes/providers/google/class-google-provider.php`
- **Settings**: None (no configuration needed)
- **Features**: Simple, fast translation using Google Translate public API

#### OpenAI Provider
- **Translation**: `includes/providers/openai/class-openai-provider.php`
- **Settings**: `includes/providers/openai/class-openai-settings-provider.php`
- **Features**: 
  - API key configuration
  - Custom assistant management per language pair
  - Multi-step translation support
  - Live testing interface
  - Asset management (JS/CSS)

### Dynamic Settings UI

The settings page automatically:
1. **Discovers Providers**: Scans registry for all registered providers
2. **Generates Provider List**: Creates radio buttons dynamically from provider info
3. **Creates Dynamic Tabs**: Adds tabs only for providers with settings UI
4. **Handles Tab Visibility**: Shows/hides provider tabs based on selection
5. **Enqueues Assets**: Loads provider-specific JS/CSS automatically
6. **Validates Settings**: Routes POST data to appropriate provider validators

## Multi-Server Translation Architecture

The plugin supports a complete 3-server translation architecture that can be deployed across multiple WordPress installations or run locally on a single server:

1. **Scheduler Server** - Schedules translations and manages the workflow
2. **Translator Server** - Performs the actual translation work
3. **Receiver Server** - Receives and processes translated content

### Translation Workflows

#### Local Translation (Single Server) - Async
```
User clicks "Translate" 
→ Scheduler sends HTTP request to own /translation/translate endpoint (async)
→ Translation Extension receives request and translates using Google/OpenAI
→ Translation Extension sends result to own /translation/receive-post endpoint
→ Receiver creates post and sends notifications
```

#### Remote Translation (Multi-Server) - Async
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

### Key Benefits of Async Architecture

- **Non-blocking UI**: User interface doesn't freeze during translation
- **Scalable**: Can handle multiple translation requests simultaneously
- **Consistent**: Same HTTP-based workflow for local and remote translations
- **Reliable**: Failed requests can be retried and logged properly
- **Fire-and-forget**: Scheduler doesn't wait for translation completion

### Configuration

The multi-server architecture is configured via the `polytrans_settings` option:
- `translation_provider`: 'google' or 'openai'
- `translation_endpoint`: URL of remote translator server (optional)
- `translation_receiver_endpoint`: URL where results should be sent back
- `translation_receiver_secret`: Security token
- `translation_receiver_secret_method`: Authentication method

### REST API Endpoints

- **POST** `/wp-json/polytrans/v1/translation/translate` - Receive and process translation requests
- **POST** `/wp-json/polytrans/v1/translation/receive-post` - Receive completed translations

## Benefits of This Architecture

1. **Separation of Concerns**: Each module handles a specific aspect of functionality
2. **Maintainability**: Easy to locate and modify specific features
3. **Extensibility**: New translation providers can be added to `/translator/`
4. **Testability**: Individual modules can be tested independently
5. **Code Organization**: Related functionality is grouped together
6. **Scalability**: Easy to add new modules or extend existing ones

## Adding New Translation Providers

The plugin's hot-pluggable architecture makes it easy to add new translation providers. Here's how:

### Step 1: Create Translation Provider

```php
class My_Custom_Translation_Provider implements PolyTrans_Translation_Provider_Interface
{
    public function get_id() 
    { 
        return 'my-provider'; 
    }
    
    public function get_name() 
    { 
        return 'My Custom Provider'; 
    }
    
    public function get_description() 
    { 
        return 'Custom translation service integration'; 
    }
    
    public function translate(array $content, string $source_lang, string $target_lang, array $settings)
    {
        // Your translation logic here
        return [
            'success' => true, 
            'translated_content' => $translated_content, 
            'error' => ''
        ];
    }
    
    public function is_configured(array $settings) 
    { 
        return true; 
    }
    
    public function get_supported_languages() 
    { 
        return []; 
    }
    
    public function get_settings_provider_class() 
    { 
        return 'My_Custom_Settings_Provider'; // or null if no settings needed
    }
}
```

### Step 2: Create Settings Provider (Optional)

```php
class My_Custom_Settings_Provider implements PolyTrans_Settings_Provider_Interface
{
    public function get_provider_id() 
    { 
        return 'my-provider'; 
    }
    
    public function get_tab_label() 
    { 
        return 'My Provider Settings'; 
    }
    
    public function render_settings_ui(array $settings) 
    {
        // Render your settings form
        echo '<input type="text" name="my_api_key" value="' . esc_attr($settings['my_api_key'] ?? '') . '" />';
    }
    
    public function validate_settings(array $post_data) 
    {
        return [
            'my_api_key' => sanitize_text_field($post_data['my_api_key'] ?? '')
        ];
    }
    
    public function is_configured(array $settings) 
    { 
        return !empty($settings['my_api_key']); 
    }
    
    public function get_configuration_status(array $settings) 
    { 
        return $this->is_configured($settings) ? 'configured' : 'not_configured'; 
    }
}
```

### Step 3: Register Your Provider

```php
add_filter('polytrans_register_providers', function($providers) {
    $providers[] = new My_Custom_Translation_Provider();
    return $providers;
});
```

### Benefits of This Architecture

1. **Hot-Pluggable**: Providers can be added without modifying core code
2. **Separation of Concerns**: Translation logic separate from UI management
3. **Dynamic UI**: Settings interface automatically adapts to available providers
4. **Maintainable**: Each provider is self-contained and independent
5. **Extensible**: Easy to add new providers or extend existing ones

## Development Guidelines

- Follow WordPress coding standards
- Use the `PolyTrans_` prefix for all class names
- Document all public methods
- Handle errors gracefully with proper logging
- Validate and sanitize all inputs
- Use WordPress hooks and filters where appropriate
