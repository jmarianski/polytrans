# PolyTrans DeepSeek Provider - Example Plugin

This is a **complete example plugin** demonstrating how to add a custom provider to PolyTrans.

## What This Plugin Does

- Adds DeepSeek as a translation provider
- Supports both translation and assistants
- Integrates with PolyTrans workflow system
- Uses universal JS system for settings UI
- Works with translation paths

## Installation

1. Copy this entire directory to `wp-content/plugins/polytrans-deepseek/`
2. Activate the plugin in WordPress admin
3. Go to PolyTrans > Settings
4. Enable DeepSeek in "Enabled Translation Providers"
5. Configure API key in "DeepSeek Configuration" tab

## File Structure

```
polytrans-deepseek/
├── polytrans-deepseek.php          # Main plugin file with registration hooks
├── includes/
│   ├── DeepSeekProvider.php        # TranslationProviderInterface implementation
│   ├── DeepSeekSettingsProvider.php # SettingsProviderInterface implementation
│   └── DeepSeekAssistantClientAdapter.php # AIAssistantClientInterface implementation
└── README.md                        # This file
```

## Key Implementation Points

### 1. Provider Registration

```php
add_action('polytrans_register_providers', function($registry) {
    require_once __DIR__ . '/includes/DeepSeekProvider.php';
    $registry->register_provider(new DeepSeekProvider());
});
```

### 2. Assistant Client Registration

```php
add_filter('polytrans_assistant_client_factory_create', function($client, $assistant_id, $settings) {
    if (strpos($assistant_id, 'deepseek_') === 0) {
        return new DeepSeekAssistantClientAdapter($api_key);
    }
    return $client;
}, 10, 3);
```

### 3. Provider ID Detection

```php
add_filter('polytrans_assistant_client_factory_get_provider_id', function($provider_id, $assistant_id) {
    if (strpos($assistant_id, 'deepseek_') === 0) {
        return 'deepseek';
    }
    return $provider_id;
}, 10, 2);
```

### 4. Universal JS System

PolyTrans automatycznie ładuje uniwersalny system JavaScript, który obsługuje wszystkie providery używające data attributes.

**Universal UI automatycznie generuje HTML z odpowiednimi data attributes:**
- `data-provider="deepseek"` - ID providera
- `data-field="api-key"` - pole API key
- `data-field="model"` - select z modelami
- `data-action="validate-key"` - przycisk walidacji
- `data-action="refresh-models"` - przycisk odświeżania modeli

**Automatyczna funkcjonalność:**
- ✅ Walidacja API key działa automatycznie
- ✅ Toggle visibility działa automatycznie
- ✅ Ładowanie modeli działa automatycznie
- ✅ Refresh modeli działa automatycznie

Nie musisz pisać własnego JavaScript!

### 5. Universal Endpoints

The plugin uses universal endpoints:
- `polytrans_validate_provider_key` - for API key validation
- `polytrans_load_assistants` - for loading assistants
- `polytrans_get_provider_models` - for loading models

## Customization

To adapt this for your own provider:

1. Replace "DeepSeek" with your provider name
2. Update API endpoints in `DeepSeekProvider.php` and `DeepSeekSettingsProvider.php`
3. Adjust assistant ID format (currently `deepseek_xxx`)
4. Update model names and endpoints
5. Customize translation logic if needed

## Testing

After installation, verify:

- [ ] DeepSeek appears in "Enabled Translation Providers"
- [ ] DeepSeek has its own tab in settings
- [ ] API key can be validated
- [ ] Models can be loaded
- [ ] Assistants appear in workflow dropdown
- [ ] DeepSeek can be used in translation paths
- [ ] Managed assistants work with DeepSeek

## Support

This is an example plugin. For questions about PolyTrans extensibility:
- See `docs/developer/PROVIDER_EXTENSIBILITY_GUIDE.md`
- Check PolyTrans source code for reference implementations

