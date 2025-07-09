# Hot-Pluggable Translation Provider Architecture

The PolyTrans plugin now features a completely hot-pluggable translation provider system that separates translation functionality from settings management.

## Architecture Overview

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

## Built-in Providers

### Google Translate Provider
- **Translation**: `includes/providers/google/class-google-provider.php`
- **Settings**: None (no configuration needed)
- **Features**: Simple, fast translation using Google Translate API

### OpenAI Provider
- **Translation**: `includes/providers/openai/class-openai-provider.php`
- **Settings**: `includes/providers/openai/class-openai-settings-provider.php`
- **Features**: 
  - API key configuration
  - Custom assistant management per language pair
  - Multi-step translation support
  - Live testing interface
  - Asset management (JS/CSS)

## Dynamic Settings UI

The settings page automatically:

1. **Discovers Providers**: Scans registry for all registered providers
2. **Generates Provider List**: Creates radio buttons dynamically from provider info
3. **Creates Dynamic Tabs**: Adds tabs only for providers with settings UI
4. **Handles Tab Visibility**: Shows/hides provider tabs based on selection
5. **Enqueues Assets**: Loads provider-specific JS/CSS automatically
6. **Validates Settings**: Routes POST data to appropriate provider validators

## Creating a Third-Party Provider

### Step 1: Create Translation Provider

```php
class My_Custom_Translation_Provider implements PolyTrans_Translation_Provider_Interface
{
    public function get_id() { return 'my-provider'; }
    
    public function get_name() { return 'My Custom Provider'; }
    
    public function get_description() { return 'Custom translation service'; }
    
    public function translate(array $content, string $source_lang, string $target_lang, array $settings)
    {
        // Your translation logic here
        return ['success' => true, 'translated_content' => $translated, 'error' => ''];
    }
    
    public function is_configured(array $settings) { return true; }
    
    public function get_supported_languages() { return []; }
    
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
    public function get_provider_id() { return 'my-provider'; }
    
    public function get_tab_label() { return 'My Provider Settings'; }
    
    public function render_settings_ui(array $settings, array $languages, array $language_names)
    {
        // Render your settings HTML here
        echo '<div class="my-provider-settings">...</div>';
    }
    
    public function validate_settings(array $posted_data)
    {
        // Validate and return sanitized settings
        return ['my_api_key' => sanitize_text_field($posted_data['my_api_key'] ?? '')];
    }
    
    public function get_required_js_files() { return ['path/to/my-script.js']; }
    
    public function get_required_css_files() { return ['path/to/my-styles.css']; }
    
    // ... implement other required methods
}
```

### Step 3: Register Your Provider

```php
add_action('polytrans_register_providers', function($registry) {
    // Load your provider classes
    require_once 'path/to/my-custom-translation-provider.php';
    require_once 'path/to/my-custom-settings-provider.php';
    
    // Register the translation provider
    $registry->register_provider(new My_Custom_Translation_Provider());
});
```

## Benefits of This Architecture

1. **Separation of Concerns**: Translation logic is separate from UI
2. **Hot-Pluggable**: Third-party providers can be added via WordPress filters
3. **Asset Management**: Each provider manages its own JS/CSS dependencies
4. **Dynamic UI**: Settings tabs appear/disappear automatically
5. **Extensible**: Easy to add new providers without modifying core code
6. **Clean Code**: Each provider focuses on its specific responsibility
7. **Maintainable**: Clear interfaces make debugging and updates easier

## Asset Loading

Settings providers can specify JavaScript and CSS files that will be automatically loaded when their settings tab is active. This enables:

- Provider-specific UI interactions
- API validation scripts
- Custom styling
- AJAX functionality

## Provider Discovery

The system automatically discovers and displays all registered providers in the settings UI. No manual configuration is needed - just register your provider and it appears in the dropdown with its own settings tab (if it has one).

This architecture makes PolyTrans truly extensible while maintaining clean separation between translation functionality and settings management.
