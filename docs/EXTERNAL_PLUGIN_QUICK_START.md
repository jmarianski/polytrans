# Quick Start: Zewnętrzny Plugin - Dodawanie Providera

## Minimalna Struktura Pluginu

```
polytrans-your-provider/
├── polytrans-your-provider.php     # Main plugin file
└── includes/
    ├── YourProvider.php            # TranslationProviderInterface (opcjonalnie)
    ├── YourSettingsProvider.php    # SettingsProviderInterface (WYMAGANE)
    └── YourChatClientAdapter.php   # ChatClientInterface (WYMAGANE dla managed assistants)
```

## Krok 1: Main Plugin File

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

// Check minimum PolyTrans version (1.6.0+ for full extensibility)
if (defined('POLYTRANS_VERSION')) {
    $required_version = '1.6.0';
    if (version_compare(POLYTRANS_VERSION, $required_version, '<')) {
        add_action('admin_notices', function() use ($required_version) {
            echo '<div class="error"><p>PolyTrans Your Provider requires PolyTrans version ' . esc_html($required_version) . ' or higher.</p></div>';
        });
        return;
    }
}

/**
 * 1. Register provider (opcjonalnie - tylko jeśli potrzebujesz direct translation)
 */
add_action('polytrans_register_providers', function($registry) {
    require_once __DIR__ . '/includes/YourProvider.php';
    $registry->register_provider(new YourProvider());
});

/**
 * 2. Register chat client (WYMAGANE dla managed assistants)
 */
add_filter('polytrans_chat_client_factory_create', function($client, $provider_id, $settings) {
    if ($provider_id === 'your_provider' && $client === null) {
        require_once __DIR__ . '/includes/YourChatClientAdapter.php';
        $api_key = $settings['your_provider_api_key'] ?? '';
        if (!empty($api_key)) {
            return new YourChatClientAdapter($api_key);
        }
    }
    return $client;
}, 10, 3);

/**
 * 3. Register assistant client (opcjonalnie - tylko jeśli provider ma Assistants API)
 */
add_filter('polytrans_assistant_client_factory_create', function($client, $assistant_id, $settings) {
    if (strpos($assistant_id, 'your_provider_') === 0) {
        require_once __DIR__ . '/includes/YourAssistantClientAdapter.php';
        $api_key = $settings['your_provider_api_key'] ?? '';
        if (!empty($api_key)) {
            return new YourAssistantClientAdapter($api_key);
        }
    }
    return $client;
}, 10, 3);

/**
 * 4. Register provider ID detection (opcjonalnie - tylko jeśli masz Assistants API)
 */
add_filter('polytrans_assistant_client_factory_get_provider_id', function($provider_id, $assistant_id) {
    if (strpos($assistant_id, 'your_provider_') === 0) {
        return 'your_provider';
    }
    return $provider_id;
}, 10, 2);
```

## Krok 2: Settings Provider (WYMAGANE)

```php
<?php
/**
 * Your Settings Provider
 * Implements SettingsProviderInterface
 */

if (!defined('ABSPATH')) {
    exit;
}

class YourSettingsProvider implements \PolyTrans\Providers\SettingsProviderInterface
{
    public function get_provider_id() { return 'your_provider'; }
    public function get_tab_label() { return 'Your Provider Configuration'; }
    public function get_tab_description() { return 'Configure your Your Provider API key.'; }
    public function get_required_js_files() { return []; }
    public function get_required_css_files() { return []; }
    
    public function get_settings_keys() {
        return ['your_provider_api_key', 'your_provider_model'];
    }
    
    public function render_settings_ui(array $settings, array $languages, array $language_names) {
        // Universal UI automatycznie wygeneruje API key + model selection
        // Nie musisz nic implementować!
        return;
    }
    
    public function validate_settings(array $posted_data) {
        return [
            'your_provider_api_key' => sanitize_text_field($posted_data['your_provider_api_key'] ?? ''),
            'your_provider_model' => sanitize_text_field($posted_data['your_provider_model'] ?? 'default-model'),
        ];
    }
    
    public function get_default_settings() {
        return [
            'your_provider_api_key' => '',
            'your_provider_model' => 'default-model',
        ];
    }
    
    public function is_configured(array $settings) {
        return !empty($settings['your_provider_api_key'] ?? '');
    }
    
    public function get_configuration_status(array $settings) {
        return $this->is_configured($settings) ? '' : 'API key not configured';
    }
    
    public function enqueue_assets() {}
    public function get_ajax_handlers() { return []; }
    public function register_ajax_handlers() {}
    
    /**
     * Provider Manifest - najważniejsza metoda!
     */
    public function get_provider_manifest(array $settings) {
        $api_key = $settings['your_provider_api_key'] ?? '';
        
        return [
            'provider_id' => 'your_provider',
            'capabilities' => ['chat'], // lub ['assistants', 'chat'] jeśli ma Assistants API
            'chat_endpoint' => 'https://api.yourprovider.com/v1/chat/completions',
            'models_endpoint' => 'https://api.yourprovider.com/v1/models',
            'auth_type' => 'bearer', // 'bearer', 'api_key', 'none'
            'auth_header' => 'Authorization', // lub 'x-api-key', etc.
            'api_key_setting' => 'your_provider_api_key',
            'api_key_configured' => !empty($api_key) && current_user_can('manage_options'),
            'base_url' => 'https://api.yourprovider.com/v1',
            'supports_system_prompt' => true, // czy wspiera system prompt
        ];
    }
    
    public function validate_api_key(string $api_key): bool {
        if (empty($api_key)) {
            return false;
        }
        
        try {
            $response = wp_remote_get('https://api.yourprovider.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 10,
            ]);
            
            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function load_assistants(array $settings): array {
        // Jeśli nie ma Assistants API, zwróć pustą tablicę
        return [];
    }
    
    public function load_models(array $settings): array {
        $api_key = $settings['your_provider_api_key'] ?? '';
        
        if (empty($api_key)) {
            return $this->get_fallback_models();
        }
        
        try {
            $response = wp_remote_get('https://api.yourprovider.com/v1/models', [
                'headers' => ['Authorization' => 'Bearer ' . $api_key],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return $this->get_fallback_models();
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $grouped = ['Your Provider Models' => []];
            
            foreach ($data['data'] ?? [] as $model) {
                $grouped['Your Provider Models'][$model['id']] = $model['name'] ?? $model['id'];
            }
            
            return $grouped;
        } catch (\Exception $e) {
            return $this->get_fallback_models();
        }
    }
    
    private function get_fallback_models() {
        return [
            'Your Provider Models' => [
                'default-model' => 'Default Model',
            ],
        ];
    }
}
```

## Krok 3: Chat Client Adapter (WYMAGANE dla managed assistants)

```php
<?php
/**
 * Your Chat Client Adapter
 * Implements ChatClientInterface for managed assistants
 */

if (!defined('ABSPATH')) {
    exit;
}

class YourChatClientAdapter implements \PolyTrans\Providers\ChatClientInterface
{
    private $api_key;
    private $base_url;
    
    public function __construct($api_key, $base_url = 'https://api.yourprovider.com/v1') {
        $this->api_key = $api_key;
        $this->base_url = rtrim($base_url, '/');
    }
    
    public function get_provider_id() {
        return 'your_provider';
    }
    
    public function chat_completion($messages, $parameters) {
        // Build request body
        $body = array_merge(
            ['messages' => $messages],
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
    
    public function extract_content($response) {
        // Extract content from your provider's response format
        // Example for OpenAI-like format:
        return $response['choices'][0]['message']['content'] ?? null;
        
        // Example for Claude-like format:
        // return $response['content'][0]['text'] ?? null;
    }
}
```

## Krok 4: Translation Provider (OPCJONALNIE - tylko jeśli potrzebujesz direct translation)

```php
<?php
/**
 * Your Translation Provider
 * Implements TranslationProviderInterface
 * OPCJONALNE - tylko jeśli chcesz direct translation (nie managed assistants)
 */

if (!defined('ABSPATH')) {
    exit;
}

class YourProvider implements \PolyTrans\Providers\TranslationProviderInterface
{
    public function get_id() { return 'your_provider'; }
    public function get_name() { return 'Your Provider'; }
    public function get_description() { return 'Your Provider description'; }
    
    public function get_settings_provider_class() {
        return YourSettingsProvider::class;
    }
    
    public function has_settings_ui() {
        return true;
    }
    
    public function translate($content, $source_lang, $target_lang, $settings) {
        // Implementacja direct translation
        // ...
        return [
            'success' => true,
            'translated_content' => $translated_content
        ];
    }
    
    public function is_configured($settings) {
        return !empty($settings['your_provider_api_key'] ?? '');
    }
}
```

## Co Dostajesz Automatycznie (bez kodu):

✅ **Tab w Settings** - automatycznie wygenerowany  
✅ **API Key field** - automatycznie wygenerowany  
✅ **Model selection** - automatycznie wygenerowany  
✅ **Walidacja API key** - działa przez universal endpoint  
✅ **Refresh models** - działa przez universal endpoint  
✅ **Managed Assistants** - automatycznie dostępne w workflow  
✅ **Universal JS/CSS** - wszystko działa automatycznie  

## Podsumowanie - Minimum dla Zewnętrznego Pluginu:

### Tylko Managed Assistants (bez direct translation):
1. ✅ **SettingsProvider** - 1 plik
2. ✅ **ChatClientAdapter** - 1 plik  
3. ✅ **Rejestracja przez filter** - 1 linijka w main file

**Razem: 2 pliki + 1 linijka kodu**

### Z Direct Translation:
1. ✅ **SettingsProvider** - 1 plik
2. ✅ **ChatClientAdapter** - 1 plik
3. ✅ **TranslationProvider** - 1 plik (opcjonalnie)
4. ✅ **Rejestracja przez hook + filter** - 2 linijki w main file

**Razem: 3 pliki + 2 linijki kodu**

## Przykład: DeepSeek

Pełny przykład znajduje się w: `docs/examples/polytrans-deepseek/`

## Gotowe! 🎉

Po dodaniu tych plików i rejestracji, twój provider będzie automatycznie dostępny w PolyTrans!

