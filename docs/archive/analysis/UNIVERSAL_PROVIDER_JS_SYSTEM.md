# Uniwersalny System JS dla Providerów Settings

## Obecny Stan

### OpenAI-specific JS (`openai-integration.js`)

**Problemy:**
- Hardcoded selektory: `#openai-api-key`, `#validate-openai-key`, `#openai-model`
- Hardcoded endpoint: `polytrans_validate_openai_key`
- Nie można łatwo dodać nowego providera bez duplikacji kodu

**Co działa dobrze:**
- `polytrans_get_provider_models` już przyjmuje `provider_id` jako parametr
- Refresh models już używa uniwersalnego endpointu

## Propozycja: Uniwersalny System

### 1. Uniwersalne Endpointy AJAX

#### A. Walidacja API Key - Uniwersalny Endpoint

**Obecny:** `polytrans_validate_openai_key` (specyficzny dla OpenAI)

**Nowy:** `polytrans_validate_provider_key` (uniwersalny)

```php
// W TranslationSettings.php lub osobnej klasie UniversalProviderHandler
public function ajax_validate_provider_key() {
    // Check nonce
    if (!check_ajax_referer('polytrans_settings_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $provider_id = sanitize_text_field($_POST['provider_id'] ?? '');
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    
    if (empty($provider_id)) {
        wp_send_json_error('Provider ID is required');
        return;
    }
    
    if (empty($api_key)) {
        wp_send_json_error('API key is required');
        return;
    }
    
    // Get provider from registry
    $registry = \PolyTrans_Provider_Registry::get_instance();
    $provider = $registry->get_provider($provider_id);
    
    if (!$provider) {
        wp_send_json_error('Provider not found');
        return;
    }
    
    // Get settings provider
    $settings_provider_class = $provider->get_settings_provider_class();
    if (!$settings_provider_class || !class_exists($settings_provider_class)) {
        wp_send_json_error('Settings provider not found');
        return;
    }
    
    $settings_provider = new $settings_provider_class();
    
    // Check if provider has validation method
    if (method_exists($settings_provider, 'validate_api_key')) {
        $is_valid = $settings_provider->validate_api_key($api_key);
        
        if ($is_valid) {
            wp_send_json_success('API key is valid!');
        } else {
            wp_send_json_error('Invalid API key.');
        }
    } else {
        // Fallback: check if key is not empty (basic validation)
        wp_send_json_success('API key saved (validation not available for this provider)');
    }
}
```

#### B. Rozszerzenie SettingsProviderInterface

```php
interface SettingsProviderInterface {
    // ... istniejące metody
    
    /**
     * Validate API key for this provider
     * @param string $api_key API key to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_api_key(string $api_key): bool;
}
```

### 2. Uniwersalny JS Manager

#### Struktura pliku: `assets/js/settings/provider-settings-universal.js`

```javascript
/**
 * Universal Provider Settings Manager
 * Handles API key validation, model loading, and UI interactions for all providers
 */
(function ($) {
    'use strict';

    /**
     * Universal Provider Manager
     * Works with any provider using data attributes
     */
    window.UniversalProviderManager = {
        providers: {}, // Cache for initialized providers
        
        /**
         * Initialize provider settings for a specific provider tab
         * @param {string} providerId Provider ID (e.g., 'openai', 'claude')
         * @param {object} config Provider-specific configuration
         */
        initProvider: function(providerId, config) {
            if (this.providers[providerId]) {
                return; // Already initialized
            }
            
            var provider = {
                id: providerId,
                config: config || {},
                initialized: false
            };
            
            this.bindProviderEvents(provider);
            this.loadProviderModels(provider);
            
            provider.initialized = true;
            this.providers[providerId] = provider;
        },
        
        /**
         * Bind events for a specific provider
         */
        bindProviderEvents: function(provider) {
            var providerId = provider.id;
            var prefix = provider.config.prefix || providerId;
            
            // API Key validation - use data attributes
            $(document).on('click', '[data-provider="' + providerId + '"][data-action="validate-key"]', function(e) {
                e.preventDefault();
                UniversalProviderManager.validateApiKey(provider, $(this));
            });
            
            // API Key visibility toggle
            $(document).on('click', '[data-provider="' + providerId + '"][data-action="toggle-visibility"]', function(e) {
                e.preventDefault();
                UniversalProviderManager.toggleApiKeyVisibility(provider, $(this));
            });
            
            // Model refresh
            $(document).on('click', '[data-provider="' + providerId + '"][data-action="refresh-models"]', function(e) {
                e.preventDefault();
                UniversalProviderManager.refreshModels(provider, $(this));
            });
        },
        
        /**
         * Validate API key for a provider
         */
        validateApiKey: function(provider, $button) {
            var providerId = provider.id;
            var $apiKeyInput = $('[data-provider="' + providerId + '"][data-field="api-key"]');
            var $messageContainer = $('[data-provider="' + providerId + '"][data-field="validation-message"]');
            
            if (!$apiKeyInput.length) {
                console.error('API key input not found for provider:', providerId);
                return;
            }
            
            var apiKey = $apiKeyInput.val().trim();
            
            if (!apiKey) {
                this.showMessage($messageContainer, 'error', 'Please enter an API key');
                return;
            }
            
            var originalText = $button.text();
            $button.prop('disabled', true).text('Validating...');
            
            if ($messageContainer.length) {
                $messageContainer.empty();
            }
            
            var ajaxUrl = this.getAjaxUrl();
            var nonce = this.getNonce();
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_validate_provider_key',
                    provider_id: providerId,
                    api_key: apiKey,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage($messageContainer, 'success', response.data || 'API key is valid!');
                    } else {
                        this.showMessage($messageContainer, 'error', response.data || 'Invalid API key.');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage($messageContainer, 'error', 'Failed to validate API key. Please try again.');
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Toggle API key visibility
         */
        toggleApiKeyVisibility: function(provider, $button) {
            var providerId = provider.id;
            var $input = $('[data-provider="' + providerId + '"][data-field="api-key"]');
            
            if (!$input.length) {
                console.error('API key input not found for provider:', providerId);
                return;
            }
            
            var currentType = $input.attr('type');
            
            if (currentType === 'password') {
                $input.attr('type', 'text');
                $button.text('🔒');
            } else {
                $input.attr('type', 'password');
                $button.text('👁');
            }
        },
        
        /**
         * Load models for a provider
         */
        loadProviderModels: function(provider) {
            var providerId = provider.id;
            var $select = $('[data-provider="' + providerId + '"][data-field="model"]');
            
            if (!$select.length) {
                return; // Model select not on this page
            }
            
            var selectedModel = $select.val() || $select.data('selected-model') || '';
            var apiKey = $('[data-provider="' + providerId + '"][data-field="api-key"]').val() || '';
            
            this.fetchModels(providerId, apiKey, selectedModel, function(models) {
                this.updateModelSelect($select, models, selectedModel);
            }.bind(this));
        },
        
        /**
         * Fetch models from API
         */
        fetchModels: function(providerId, apiKey, selectedModel, callback) {
            var ajaxUrl = this.getAjaxUrl();
            var nonce = this.getNonce();
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_get_provider_models',
                    provider_id: providerId,
                    selected_model: selectedModel || '',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.models) {
                        callback(response.data.models);
                    } else {
                        // Fallback to cached models if available
                        if (typeof polytransProviders !== 'undefined' && 
                            polytransProviders[providerId] && 
                            polytransProviders[providerId].models) {
                            callback(polytransProviders[providerId].models);
                        }
                    }
                }.bind(this),
                error: function() {
                    // Fallback to cached models on error
                    if (typeof polytransProviders !== 'undefined' && 
                        polytransProviders[providerId] && 
                        polytransProviders[providerId].models) {
                        callback(polytransProviders[providerId].models);
                    }
                }.bind(this)
            });
        },
        
        /**
         * Refresh models for a provider
         */
        refreshModels: function(provider, $button) {
            var providerId = provider.id;
            var $select = $('[data-provider="' + providerId + '"][data-field="model"]');
            
            if (!$select.length) {
                return;
            }
            
            var selectedModel = $select.val() || $select.data('selected-model') || '';
            var apiKey = $('[data-provider="' + providerId + '"][data-field="api-key"]').val() || '';
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Refreshing...');
            
            this.fetchModels(providerId, apiKey, selectedModel, function(models) {
                this.updateModelSelect($select, models, selectedModel);
                $button.prop('disabled', false).text(originalText);
                
                // Show success message
                var $messageContainer = $('[data-provider="' + providerId + '"][data-field="model-message"]');
                if ($messageContainer.length) {
                    this.showMessage($messageContainer, 'success', 'Models refreshed');
                }
            }.bind(this));
        },
        
        /**
         * Update model select dropdown
         */
        updateModelSelect: function($select, groupedModels, selectedModel) {
            if (!$select.length) {
                return;
            }
            
            $select.empty();
            
            for (var groupName in groupedModels) {
                if (!groupedModels.hasOwnProperty(groupName)) {
                    continue;
                }
                
                var $optgroup = $('<optgroup></optgroup>').attr('label', groupName);
                var models = groupedModels[groupName];
                
                for (var modelId in models) {
                    if (!models.hasOwnProperty(modelId)) {
                        continue;
                    }
                    
                    var modelLabel = models[modelId];
                    var $option = $('<option></option>')
                        .attr('value', modelId)
                        .text(modelLabel);
                    
                    if (selectedModel && modelId === selectedModel) {
                        $option.prop('selected', true);
                    }
                    
                    $optgroup.append($option);
                }
                
                $select.append($optgroup);
            }
        },
        
        /**
         * Show message in container
         */
        showMessage: function($container, type, message) {
            if (!$container || !$container.length) {
                return;
            }
            
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var html = '<div class="notice ' + className + ' is-dismissible inline"><p>' + 
                      message + 
                      '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>';
            
            $container.html(html);
            
            // Initialize dismiss functionality
            $container.find('.notice-dismiss').on('click', function(e) {
                e.preventDefault();
                $(this).closest('.notice').fadeOut(function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Get AJAX URL
         */
        getAjaxUrl: function() {
            if (typeof PolyTransAjax !== 'undefined' && PolyTransAjax.ajaxurl) {
                return PolyTransAjax.ajaxurl;
            }
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }
            return null;
        },
        
        /**
         * Get nonce
         */
        getNonce: function() {
            if (typeof PolyTransAjax !== 'undefined' && PolyTransAjax.nonce) {
                return PolyTransAjax.nonce;
            }
            var $nonceField = $('input[name="_wpnonce"]');
            if ($nonceField.length) {
                return $nonceField.val();
            }
            return null;
        }
    };
    
    // Initialize providers when tabs are clicked
    $(document).on('click', '.nav-tab', function() {
        var targetTab = $(this).attr('href');
        var $tabContent = $(targetTab);
        
        if ($tabContent.hasClass('provider-settings-content')) {
            var providerId = $tabContent.data('provider-id');
            if (providerId && !UniversalProviderManager.providers[providerId]) {
                // Initialize provider when tab is opened
                UniversalProviderManager.initProvider(providerId);
            }
        }
    });
    
    // Initialize providers on page load if their tabs are visible
    $(function() {
        $('.provider-settings-content:visible').each(function() {
            var providerId = $(this).data('provider-id');
            if (providerId) {
                UniversalProviderManager.initProvider(providerId);
            }
        });
    });
    
})(jQuery);
```

### 3. Zmiana HTML dla OpenAI (i innych providerów)

#### Obecny HTML (hardcoded):
```html
<input type="password" id="openai-api-key" name="openai_api_key" ... />
<button id="validate-openai-key">Validate</button>
<select id="openai-model" name="openai_model">...</select>
<button id="refresh-openai-models">Refresh</button>
```

#### Nowy HTML (z data attributes):
```html
<!-- API Key Field -->
<input type="password" 
       data-provider="openai" 
       data-field="api-key" 
       id="openai-api-key" 
       name="openai_api_key" 
       value="..." />

<!-- Validate Button -->
<button type="button" 
        data-provider="openai" 
        data-action="validate-key">Validate</button>

<!-- Validation Message Container -->
<div data-provider="openai" data-field="validation-message"></div>

<!-- Visibility Toggle -->
<button type="button" 
        data-provider="openai" 
        data-action="toggle-visibility">👁</button>

<!-- Model Select -->
<select data-provider="openai" 
        data-field="model" 
        id="openai-model" 
        name="openai_model">...</select>

<!-- Refresh Models Button -->
<button type="button" 
        data-provider="openai" 
        data-action="refresh-models">Refresh</button>

<!-- Model Message Container -->
<div data-provider="openai" data-field="model-message"></div>
```

### 4. Migracja OpenAI do Uniwersalnego Systemu

#### Krok 1: Zaktualizować HTML w `OpenAISettingsProvider::render_settings_ui()`

```php
// Zmienić z:
<input type="password" id="openai-api-key" ... />
<button id="validate-openai-key">...</button>

// Na:
<input type="password" 
       data-provider="openai" 
       data-field="api-key" 
       id="openai-api-key" 
       ... />
<button type="button" 
        data-provider="openai" 
        data-action="validate-key">...</button>
```

#### Krok 2: Dodać data attribute do tab content

```php
// W TranslationSettings.php, linia ~296
<div id="<?php echo esc_attr($provider_id); ?>-settings" 
     class="tab-content provider-settings-content" 
     data-provider-id="<?php echo esc_attr($provider_id); ?>"
     style="display:none;">
```

#### Krok 3: Zarejestrować uniwersalny endpoint

```php
// W TranslationSettings.php lub osobnej klasie
add_action('wp_ajax_polytrans_validate_provider_key', [$this, 'ajax_validate_provider_key']);
```

#### Krok 4: Dodać metodę validate_api_key do OpenAISettingsProvider

```php
public function validate_api_key(string $api_key): bool {
    return $this->validate_openai_api_key($api_key);
}
```

#### Krok 5: Zaktualizować enqueue_assets

```php
// W TranslationSettings.php
public function enqueue_assets($hook_suffix) {
    // ... istniejący kod
    
    // Enqueue universal provider JS
    wp_enqueue_script(
        'polytrans-provider-settings-universal',
        POLYTRANS_PLUGIN_URL . 'assets/js/settings/provider-settings-universal.js',
        ['jquery'],
        POLYTRANS_VERSION,
        true
    );
    
    // Localize script with provider configs
    wp_localize_script('polytrans-provider-settings-universal', 'polytransProviders', [
        'openai' => [
            'models' => $this->get_openai_models(), // Fallback models
        ],
        // Future: 'claude' => [...], 'gemini' => [...]
    ]);
}
```

#### Krok 6: Zachować backward compatibility

- Zachować stare ID selektorów (`#openai-api-key`) dla backward compatibility
- Dodać data attributes obok ID
- Stary JS (`openai-integration.js`) może nadal działać, ale stopniowo migrować do uniwersalnego

### 5. Przykład dla Nowego Providera (Claude)

#### HTML (automatycznie generowany przez ClaudeSettingsProvider):
```html
<input type="password" 
       data-provider="claude" 
       data-field="api-key" 
       id="claude-api-key" 
       name="claude_api_key" />
<button type="button" 
        data-provider="claude" 
        data-action="validate-key">Validate</button>
<select data-provider="claude" 
        data-field="model" 
        id="claude-model" 
        name="claude_model">...</select>
```

#### PHP (ClaudeSettingsProvider):
```php
public function validate_api_key(string $api_key): bool {
    // Implementacja walidacji Claude API key
    $client = new ClaudeClient($api_key);
    return $client->validate_key();
}
```

**To wszystko!** JS automatycznie wykryje elementy z `data-provider="claude"` i będzie działał.

## Plan Migracji

### Faza 1: Dodanie Uniwersalnego Systemu (Bez Breaking Changes)
1. ✅ Stworzyć `provider-settings-universal.js`
2. ✅ Dodać endpoint `polytrans_validate_provider_key`
3. ✅ Dodać metodę `validate_api_key()` do `SettingsProviderInterface`
4. ✅ Zaimplementować w `OpenAISettingsProvider`
5. ✅ Dodać data attributes do HTML OpenAI (obok istniejących ID)

### Faza 2: Migracja OpenAI
1. ✅ Zaktualizować `openai-integration.js` aby używał uniwersalnego systemu
2. ✅ Albo stopniowo zastąpić funkcjonalność uniwersalnym systemem
3. ✅ Zachować backward compatibility

### Faza 3: Dokumentacja
1. ✅ Zaktualizować dokumentację dla deweloperów
2. ✅ Przykład dodania nowego providera

## Zalety

1. **Zero duplikacji kodu** - jeden JS dla wszystkich providerów
2. **Łatwe dodawanie nowych providerów** - wystarczy dodać data attributes
3. **Spójne UX** - wszystkie providery działają tak samo
4. **Backward compatible** - stare ID selektorów nadal działają
5. **Testowalne** - łatwo testować dla różnych providerów

## Przykład Użycia

```javascript
// Automatyczna inicjalizacja przy kliknięciu taba
// Lub ręczna:
UniversalProviderManager.initProvider('openai');
UniversalProviderManager.initProvider('claude', {
    prefix: 'claude', // Opcjonalne, domyślnie używa provider_id
});
```

