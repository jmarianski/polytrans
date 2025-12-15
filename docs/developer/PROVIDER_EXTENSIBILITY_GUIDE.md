# Guide: Dodawanie Własnego Providera do PolyTrans

## Wprowadzenie

PolyTrans został zaprojektowany z myślą o pełnej ekstensybilności. Możesz łatwo dodać własnego providera (np. DeepSeek, Mistral, itp.) bez modyfikowania kodu głównego pluginu.

## Minimalna Implementacja

### Krok 1: Struktura Pluginu

```
polytrans-your-provider/
├── polytrans-your-provider.php     # Main plugin file
├── includes/
│   ├── YourProvider.php            # TranslationProviderInterface
│   ├── YourSettingsProvider.php    # SettingsProviderInterface
│   └── YourAssistantClientAdapter.php # AIAssistantClientInterface (opcjonalnie)
└── assets/
    └── js/
        └── your-provider-integration.js # Opcjonalne, jeśli potrzebne
```

### Krok 2: Main Plugin File

```php
<?php
/**
 * Plugin Name: PolyTrans Your Provider
 * Description: Adds Your Provider as a translation provider for PolyTrans
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 8.1
 * Requires Plugins: polytrans
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if PolyTrans is active
if (!function_exists('PolyTrans')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>PolyTrans Your Provider requires PolyTrans plugin to be installed and activated.</p></div>';
    });
    return;
}

// Register provider
add_action('polytrans_register_providers', function($registry) {
    require_once __DIR__ . '/includes/YourProvider.php';
    $registry->register_provider(new YourProvider());
});

// Register assistant client adapter (if provider supports assistants)
add_action('polytrans_register_assistant_clients', function() {
    require_once __DIR__ . '/includes/YourAssistantClientAdapter.php';
    // Adapter będzie automatycznie wykryty przez AIAssistantClientFactory
    // na podstawie formatu assistant ID (np. 'your_provider_xxx')
});
```

### Krok 3: Translation Provider

```php
<?php
namespace YourNamespace;

use PolyTrans\Providers\TranslationProviderInterface;

class YourProvider implements TranslationProviderInterface
{
    public function get_id()
    {
        return 'your_provider'; // Unikalny ID (lowercase, underscores)
    }
    
    public function get_name()
    {
        return 'Your Provider Name';
    }
    
    public function get_description()
    {
        return 'Description of what your provider does';
    }
    
    public function get_settings_provider_class()
    {
        return YourSettingsProvider::class;
    }
    
    public function has_settings_ui()
    {
        return true; // Jeśli ma własny tab w settings
    }
    
    public function translate($content, $source_lang, $target_lang, $settings)
    {
        // Implementacja translacji
        $api_key = $settings['your_provider_api_key'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'API key not configured'
            ];
        }
        
        // Wywołaj API providera
        // ...
        
        return [
            'success' => true,
            'translated_content' => $translated_content
        ];
    }
    
    public function is_configured($settings)
    {
        $api_key = $settings['your_provider_api_key'] ?? '';
        return !empty($api_key);
    }
}
```

### Krok 4: Settings Provider

```php
<?php
namespace YourNamespace;

use PolyTrans\Providers\SettingsProviderInterface;

class YourSettingsProvider implements SettingsProviderInterface
{
    public function get_provider_id()
    {
        return 'your_provider';
    }
    
    public function get_tab_label()
    {
        return 'Your Provider Configuration';
    }
    
    public function get_tab_description()
    {
        return 'Configure your Your Provider API key and settings.';
    }
    
    public function get_required_js_files()
    {
        return []; // Lub ['assets/js/your-provider-integration.js']
    }
    
    public function get_required_css_files()
    {
        return [];
    }
    
    public function get_settings_keys()
    {
        return [
            'your_provider_api_key',
            'your_provider_model',
            // ... inne klucze
        ];
    }
    
    public function render_settings_ui(array $settings, array $languages, array $language_names)
    {
        // OPTIONAL: You don't need to implement this method!
        // PolyTrans will automatically generate universal UI based on your manifest:
        // - API Key field (if api_key_setting is defined in manifest)
        // - Model selection (if capabilities include 'chat' or 'assistants')
        //
        // Universal UI uses data attributes that work with universal JS system.
        // Only implement custom UI if you need additional fields beyond API key + model.
        
        // To use universal UI (recommended), simply return:
        return;
        
        // To use custom UI, implement your own HTML:
        /*
        $api_key = $settings['your_provider_api_key'] ?? '';
        $model = $settings['your_provider_model'] ?? 'default';
        ?>
        <div class="your-provider-config-section">
            <h2><?php echo esc_html($this->get_tab_label()); ?></h2>
            <p><?php echo esc_html($this->get_tab_description()); ?></p>
            
            <!-- Custom fields here -->
        </div>
        <?php
        */
    }
    
    public function validate_settings(array $posted_data)
    {
        $validated = [];
        
        if (isset($posted_data['your_provider_api_key'])) {
            $validated['your_provider_api_key'] = sanitize_text_field($posted_data['your_provider_api_key']);
        }
        
        if (isset($posted_data['your_provider_model'])) {
            $validated['your_provider_model'] = sanitize_text_field($posted_data['your_provider_model']);
        }
        
        return $validated;
    }
    
    public function get_default_settings()
    {
        return [
            'your_provider_api_key' => '',
            'your_provider_model' => 'default',
        ];
    }
    
    public function is_configured(array $settings)
    {
        return !empty($settings['your_provider_api_key'] ?? '');
    }
    
    public function get_configuration_status(array $settings)
    {
        if (!$this->is_configured($settings)) {
            return 'API key not configured';
        }
        return '';
    }
    
    public function enqueue_assets()
    {
        // Opcjonalne: własne JS/CSS
    }
    
    public function get_ajax_handlers()
    {
        return [
            // Opcjonalne: własne endpointy AJAX
            // 'polytrans_your_provider_custom' => [
            //     'callback' => [$this, 'ajax_custom_handler'],
            //     'is_static' => false
            // ],
        ];
    }
    
    public function register_ajax_handlers()
    {
        // Automatycznie wywoływane przez ProviderRegistry
        // Możesz dodać własne handlery tutaj
    }
    
    /**
     * Provider Manifest - definiuje capabilities i konfigurację
     */
    public function get_provider_manifest(array $settings)
    {
        $api_key = $settings['your_provider_api_key'] ?? '';
        
        return [
            'provider_id' => 'your_provider',
            'capabilities' => ['chat'], // Choose appropriate capabilities:
            // - 'translation': Direct translation API (e.g., Google Translate)
            // - 'chat': Chat/completion API (all AI models, can be used for managed assistants)
            // - 'assistants': Dedicated Assistants API (e.g., OpenAI Assistants, Claude Projects)
            // Examples:
            //   - Google: ['translation']
            //   - OpenAI: ['assistants', 'chat']
            //   - DeepSeek: ['chat'] (no Assistants API, but can be used for managed assistants)
            //   - Claude: ['assistants', 'chat']
            'chat_endpoint' => 'https://api.yourprovider.com/v1/chat/completions',
            'models_endpoint' => 'https://api.yourprovider.com/v1/models',
            // Only include assistants_endpoint if provider has 'assistants' capability:
            // 'assistants_endpoint' => 'https://api.yourprovider.com/v1/assistants',
            'auth_type' => 'bearer', // 'bearer', 'api_key', 'none'
            'auth_header' => 'Authorization',
            'api_key_setting' => 'your_provider_api_key',
            'api_key_configured' => !empty($api_key) && current_user_can('manage_options'),
            'base_url' => 'https://api.yourprovider.com/v1',
        ];
    }
    
    /**
     * Validate API key
     */
    public function validate_api_key(string $api_key): bool
    {
        if (empty($api_key)) {
            return false;
        }
        
        // Implementacja walidacji API key
        // Np. wywołanie endpointu /validate lub /models
        try {
            $response = wp_remote_get('https://api.yourprovider.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            return $status_code === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Load assistants from provider API
     * 
     * IMPORTANT: Only implement this if your provider has 'assistants' capability!
     * Providers with only 'chat' capability should return empty array.
     * They can still be used for managed assistants (via system prompt).
     */
    public function load_assistants(array $settings): array
    {
        // If provider doesn't have dedicated Assistants API, return empty array
        // Managed assistants can still use this provider via chat API with system prompt
        return [];
        
        // Only implement if provider has 'assistants' capability:
        /*
        $api_key = $settings['your_provider_api_key'] ?? '';
        
        if (empty($api_key)) {
            return [];
        }
        
        try {
            $response = wp_remote_get('https://api.yourprovider.com/v1/assistants', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return [];
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            $assistants = [];
            foreach ($data['data'] ?? [] as $assistant) {
                $assistants[] = [
                    'id' => $assistant['id'],
                    'name' => $assistant['name'] ?? 'Unnamed Assistant',
                    'description' => $assistant['description'] ?? '',
                    'model' => $assistant['model'] ?? 'default',
                    'provider' => 'your_provider',
                ];
            }
            
            return $assistants;
        } catch (\Exception $e) {
            return [];
        }
        */
    }
    
    /**
     * Load models from provider API (opcjonalnie)
     */
    public function load_models(array $settings): array
    {
        $api_key = $settings['your_provider_api_key'] ?? '';
        
        if (empty($api_key)) {
            return $this->get_fallback_models();
        }
        
        try {
            $response = wp_remote_get('https://api.yourprovider.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return $this->get_fallback_models();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Grupuj modele
            $grouped = [
                'Your Provider Models' => [],
            ];
            
            foreach ($data['data'] ?? [] as $model) {
                $grouped['Your Provider Models'][$model['id']] = $model['name'] ?? $model['id'];
            }
            
            return $grouped;
        } catch (\Exception $e) {
            return $this->get_fallback_models();
        }
    }
    
    private function get_fallback_models()
    {
        return [
            'Your Provider Models' => [
                'default' => 'Default Model',
                'advanced' => 'Advanced Model',
            ],
        ];
    }
}
```

### Krok 5: Chat Client Adapter (opcjonalnie, dla managed assistants)

Jeśli twój provider wspiera `chat` capability i chcesz, aby managed assistants działały automatycznie przez factory pattern, zaimplementuj `ChatClientInterface`:

```php
<?php
namespace YourNamespace;

use PolyTrans\Providers\ChatClientInterface;

class YourChatClientAdapter implements ChatClientInterface
{
    private $api_key;
    private $base_url;
    
    public function __construct($api_key, $base_url = 'https://api.yourprovider.com/v1')
    {
        $this->api_key = $api_key;
        $this->base_url = rtrim($base_url, '/');
    }
    
    public function get_provider_id()
    {
        return 'your_provider';
    }
    
    public function chat_completion($messages, $parameters)
    {
        // Build request body
        $body = array_merge(
            [
                'messages' => $messages,
            ],
            $parameters
        );
        
        // Make API request
        $response = wp_remote_post(
            $this->base_url . '/chat/completions',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body' => wp_json_encode($body),
                'timeout' => 120,
            ]
        );
        
        // Handle errors
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle API errors
        if ($status_code !== 200) {
            $error_message = $body_data['error']['message'] ?? 'Unknown API error';
            
            return [
                'success' => false,
                'data' => null,
                'error' => $error_message,
                'error_code' => $status_code === 429 ? 'rate_limit' : 'api_error',
                'status' => $status_code,
            ];
        }
        
        return [
            'success' => true,
            'data' => $body_data,
            'error' => null
        ];
    }
    
    public function extract_content($response)
    {
        // Extract content from your provider's response format
        // Example for OpenAI-like format:
        return $response['choices'][0]['message']['content'] ?? null;
        
        // Example for Claude-like format:
        // return $response['content'][0]['text'] ?? null;
    }
}
```

**Rejestracja Chat Client:**

```php
// W głównym pliku pluginu
add_filter('polytrans_chat_client_factory_create', function($client, $provider_id, $settings) {
    if ($provider_id === 'your_provider') {
        require_once __DIR__ . '/includes/YourChatClientAdapter.php';
        $api_key = $settings['your_provider_api_key'] ?? '';
        if (!empty($api_key)) {
            return new YourChatClientAdapter($api_key);
        }
    }
    return $client;
}, 10, 3);
```

**UWAGA:** Jeśli nie zaimplementujesz `ChatClientInterface`, managed assistants nadal będą działać, ale system użyje fallback extraction. Zalecane jest zaimplementowanie interfejsu dla lepszej kompatybilności i automatycznego wsparcia przez factory pattern.
```

### Krok 5: Assistant Client Adapter (jeśli provider wspiera asystentów)

```php
<?php
namespace YourNamespace;

use PolyTrans\Providers\AIAssistantClientInterface;

class YourAssistantClientAdapter implements AIAssistantClientInterface
{
    private $api_key;
    private $base_url;
    
    public function __construct($api_key, $base_url = 'https://api.yourprovider.com/v1')
    {
        $this->api_key = $api_key;
        $this->base_url = $base_url;
    }
    
    public function get_provider_id()
    {
        return 'your_provider';
    }
    
    public function supports_assistant_id($assistant_id)
    {
        // Detekcja formatu assistant ID (np. 'your_provider_xxx', 'yp_xxx')
        return strpos($assistant_id, 'your_provider_') === 0 || 
               strpos($assistant_id, 'yp_') === 0;
    }
    
    public function execute_assistant($assistant_id, $content, $source_lang, $target_lang)
    {
        // Implementacja wykonania asystenta
        // ...
        
        return [
            'success' => true,
            'translated_content' => $translated_content,
            'error' => null
        ];
    }
}
```

## Rejestracja w AIAssistantClientFactory

Factory automatycznie wykryje adapter na podstawie formatu assistant ID. Jeśli używasz niestandardowego formatu, możesz użyć hooka:

```php
add_filter('polytrans_assistant_client_factory_create', function($client, $assistant_id, $settings) {
    if (strpos($assistant_id, 'your_provider_') === 0) {
        require_once __DIR__ . '/includes/YourAssistantClientAdapter.php';
        $api_key = $settings['your_provider_api_key'] ?? '';
        return new YourAssistantClientAdapter($api_key);
    }
    return $client;
}, 10, 3);
```

## Hooki i Filtry Dostępne dla Zewnętrznych Pluginów

### Actions

```php
// Rejestracja providera
do_action('polytrans_register_providers', $registry);

// Rejestracja adapterów asystentów
do_action('polytrans_register_assistant_clients');
```

### Filters

```php
// Filtrowanie manifestu providera
apply_filters('polytrans_provider_manifest', $manifest, $provider_id, $settings);

// Filtrowanie listy asystentów
apply_filters('polytrans_load_assistants_{provider_id}', $assistants, $settings);

// Filtrowanie listy modeli
apply_filters('polytrans_load_models_{provider_id}', $models, $settings);

// Filtrowanie walidacji API key
apply_filters('polytrans_validate_api_key_{provider_id}', $is_valid, $api_key);

// Filtrowanie factory dla assistant clients
apply_filters('polytrans_assistant_client_factory_create', $client, $assistant_id, $settings);

// Filtrowanie factory dla chat clients (dla managed assistants)
apply_filters('polytrans_chat_client_factory_create', $client, $provider_id, $settings);
```

## Przykład: DeepSeek Provider

Pełny przykład pluginu `polytrans_deepseek` znajduje się w:
- `docs/examples/polytrans-deepseek/` (do stworzenia)

## Testing

Po zaimplementowaniu providera, sprawdź:

1. ✅ Provider pojawia się w "Enabled Translation Providers"
2. ✅ Provider ma własny tab w settings
3. ✅ API key można zapisać i zwalidować
4. ✅ Modele można załadować i wybrać
5. ✅ Asystenci są widoczni w workflow dropdown (jeśli wspiera assistants)
6. ✅ Provider można używać w translation paths
7. ✅ Managed assistants działają (jeśli wspiera assistants)

## Provider Capabilities

**WAŻNE:** PolyTrans rozróżnia trzy typy capabilities:

1. **`translation`** - Bezpośrednia translacja (np. Google Translate)
2. **`chat`** - Chat/completion API (wszystkie modele AI, mogą być używane dla managed assistants)
3. **`assistants`** - Dedykowane Assistants API (np. OpenAI Assistants, Claude Projects, Gemini Tuned Models)

Zobacz szczegółowy przewodnik: [`PROVIDER_CAPABILITIES.md`](PROVIDER_CAPABILITIES.md)

### Przykłady:

- **Google**: `['translation']` - tylko bezpośrednia translacja
- **OpenAI**: `['assistants', 'chat']` - ma Assistants API + może być używany dla managed assistants
- **DeepSeek**: `['chat']` - tylko chat API, może być używany dla managed assistants (z system prompt)
- **Claude**: `['assistants', 'chat']` - ma Projects API + może być używany dla managed assistants

### Managed Assistants vs Predefined Assistants

- **Managed Assistants** mogą używać providerów z `chat` capability (przez system prompt)
- **Predefined Assistants** mogą być ładowane tylko z providerów z `assistants` capability

## Universal JavaScript System

PolyTrans automatycznie ładuje uniwersalny system JavaScript (`provider-settings-universal.js`), który obsługuje wszystkie providery używające data attributes.

### Automatyczna Funkcjonalność

Jeśli używasz Universal UI (domyślne), następujące funkcje działają automatycznie:

1. **Walidacja API Key** - przycisk "Validate" automatycznie używa `polytrans_validate_provider_key`
2. **Toggle Visibility** - przycisk 👁 automatycznie pokazuje/ukrywa API key
3. **Ładowanie Modeli** - modele są automatycznie ładowane przy otwarciu taba
4. **Refresh Modeli** - przycisk "Refresh" automatycznie odświeża listę modeli

### Data Attributes

Universal JS używa następujących data attributes:

- `data-provider="provider_id"` - ID providera
- `data-field="api-key"` - pole API key
- `data-field="model"` - select z modelami
- `data-field="validation-message"` - kontener na komunikaty walidacji
- `data-field="model-message"` - kontener na komunikaty modeli
- `data-action="validate-key"` - przycisk walidacji
- `data-action="toggle-visibility"` - przycisk pokazywania hasła
- `data-action="refresh-models"` - przycisk odświeżania modeli

### Przykład HTML (Universal UI)

Universal UI automatycznie generuje HTML z odpowiednimi data attributes:

```html
<input data-provider="deepseek" data-field="api-key" ... />
<button data-provider="deepseek" data-action="validate-key">Validate</button>
<select data-provider="deepseek" data-field="model" ... />
<button data-provider="deepseek" data-action="refresh-models">Refresh</button>
```

### Custom UI z Universal JS

Jeśli tworzysz własne UI, możesz użyć tych samych data attributes:

```php
public function render_settings_ui(...) {
    ?>
    <div data-provider-id="your_provider">
        <input data-provider="your_provider" data-field="api-key" ... />
        <button data-provider="your_provider" data-action="validate-key">Validate</button>
        <div data-provider="your_provider" data-field="validation-message"></div>
        
        <select data-provider="your_provider" data-field="model" ... />
        <button data-provider="your_provider" data-action="refresh-models">Refresh</button>
        <div data-provider="your_provider" data-field="model-message"></div>
    </div>
    <?php
}
```

Universal JS automatycznie wykryje te elementy i doda obsługę.

## Wsparcie

Jeśli masz pytania lub potrzebujesz pomocy:
- Sprawdź przykładowy plugin `polytrans_deepseek`
- Zobacz implementację wbudowanych providerów (OpenAI, Google)
- Sprawdź dokumentację capabilities: [`PROVIDER_CAPABILITIES.md`](PROVIDER_CAPABILITIES.md)
- Sprawdź dokumentację API w `docs/developer/API-DOCUMENTATION.md`

