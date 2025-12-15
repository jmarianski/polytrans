# Quick Start: Dodawanie Nowego Providera

## Minimum Konfiguracji (tylko Managed Assistants)

Jeśli chcesz dodać providera tylko dla **managed assistants** (bez direct translation), potrzebujesz tylko **2 pliki**:

### 1. Settings Provider (`includes/ClaudeSettingsProvider.php`)

```php
<?php
namespace PolyTrans\Providers\Claude;

use PolyTrans\Providers\SettingsProviderInterface;

class ClaudeSettingsProvider implements SettingsProviderInterface
{
    public function get_provider_id() { return 'claude'; }
    public function get_tab_label() { return 'Claude Configuration'; }
    public function get_tab_description() { return 'Configure your Claude API key.'; }
    public function get_required_js_files() { return []; }
    public function get_required_css_files() { return []; }
    
    public function get_settings_keys() {
        return ['claude_api_key', 'claude_model'];
    }
    
    public function render_settings_ui(array $settings, array $languages, array $language_names) {
        // Universal UI automatycznie wygeneruje API key + model selection
        return;
    }
    
    public function validate_settings(array $posted_data) {
        return [
            'claude_api_key' => sanitize_text_field($posted_data['claude_api_key'] ?? ''),
            'claude_model' => sanitize_text_field($posted_data['claude_model'] ?? 'claude-3-5-sonnet-20241022'),
        ];
    }
    
    public function get_default_settings() {
        return ['claude_api_key' => '', 'claude_model' => 'claude-3-5-sonnet-20241022'];
    }
    
    public function is_configured(array $settings) {
        return !empty($settings['claude_api_key'] ?? '');
    }
    
    public function get_configuration_status(array $settings) {
        return $this->is_configured($settings) ? '' : 'Claude API key not configured';
    }
    
    public function enqueue_assets() {}
    public function get_ajax_handlers() { return []; }
    public function register_ajax_handlers() {}
    
    public function get_provider_manifest(array $settings) {
        return [
            'provider_id' => 'claude',
            'capabilities' => ['chat'], // lub ['assistants', 'chat'] jeśli ma Assistants API
            'chat_endpoint' => 'https://api.anthropic.com/v1/messages',
            'models_endpoint' => 'https://api.anthropic.com/v1/models',
            'auth_type' => 'bearer',
            'auth_header' => 'x-api-key',
            'api_key_setting' => 'claude_api_key',
            'api_key_configured' => !empty($settings['claude_api_key'] ?? ''),
            'base_url' => 'https://api.anthropic.com/v1',
            'supports_system_prompt' => true,
        ];
    }
    
    public function validate_api_key(string $api_key): bool {
        // Implementacja walidacji
        try {
            $response = wp_remote_get('https://api.anthropic.com/v1/models', [
                'headers' => ['x-api-key' => $api_key, 'anthropic-version' => '2023-06-01'],
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
        $api_key = $settings['claude_api_key'] ?? '';
        if (empty($api_key)) {
            return $this->get_fallback_models();
        }
        
        try {
            $response = wp_remote_get('https://api.anthropic.com/v1/models', [
                'headers' => ['x-api-key' => $api_key, 'anthropic-version' => '2023-06-01'],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return $this->get_fallback_models();
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $grouped = ['Claude Models' => []];
            
            foreach ($data['data'] ?? [] as $model) {
                $grouped['Claude Models'][$model['id']] = $model['name'] ?? $model['id'];
            }
            
            return $grouped;
        } catch (\Exception $e) {
            return $this->get_fallback_models();
        }
    }
    
    private function get_fallback_models() {
        return [
            'Claude Models' => [
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                'claude-3-opus-20240229' => 'Claude 3 Opus',
            ],
        ];
    }
}
```

### 2. Chat Client Adapter (`includes/ClaudeChatClientAdapter.php`)

```php
<?php
namespace PolyTrans\Providers\Claude;

use PolyTrans\Providers\ChatClientInterface;

class ClaudeChatClientAdapter implements ChatClientInterface
{
    private $api_key;
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function get_provider_id() {
        return 'claude';
    }
    
    public function chat_completion($messages, $parameters) {
        // Konwersja z OpenAI format do Claude format
        $system_message = '';
        $user_messages = [];
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_message = $msg['content'];
            } else {
                $user_messages[] = ['role' => 'user', 'content' => $msg['content']];
            }
        }
        
        $body = [
            'model' => $parameters['model'] ?? 'claude-3-5-sonnet-20241022',
            'max_tokens' => $parameters['max_tokens'] ?? 4096,
            'messages' => $user_messages,
        ];
        
        if (!empty($system_message)) {
            $body['system'] = $system_message;
        }
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'data' => null, 'error' => $response->get_error_message()];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            return [
                'success' => false,
                'data' => null,
                'error' => $body_data['error']['message'] ?? 'API error',
                'error_code' => $status_code === 429 ? 'rate_limit' : 'api_error',
                'status' => $status_code,
            ];
        }
        
        return ['success' => true, 'data' => $body_data, 'error' => null];
    }
    
    public function extract_content($response) {
        // Claude format: content[0].text
        return $response['content'][0]['text'] ?? null;
    }
}
```

### 3. Rejestracja w `polytrans.php` (lub w osobnym pluginie)

```php
// W polytrans.php lub w osobnym pluginie

// 1. Rejestracja providera (opcjonalnie, jeśli potrzebujesz direct translation)
add_action('polytrans_register_providers', function($registry) {
    require_once __DIR__ . '/includes/Providers/Claude/ClaudeProvider.php';
    $registry->register_provider(new \PolyTrans\Providers\Claude\ClaudeProvider());
});

// 2. Rejestracja chat client (WYMAGANE dla managed assistants)
add_filter('polytrans_chat_client_factory_create', function($client, $provider_id, $settings) {
    if ($provider_id === 'claude' && $client === null) {
        require_once __DIR__ . '/includes/Providers/Claude/ClaudeChatClientAdapter.php';
        $api_key = $settings['claude_api_key'] ?? '';
        if (!empty($api_key)) {
            return new \PolyTrans\Providers\Claude\ClaudeChatClientAdapter($api_key);
        }
    }
    return $client;
}, 10, 3);
```

## Co Dostajesz Automatycznie:

✅ **Tab w Settings** - automatycznie wygenerowany  
✅ **API Key field** - automatycznie wygenerowany  
✅ **Model selection** - automatycznie wygenerowany  
✅ **Walidacja API key** - działa przez universal endpoint  
✅ **Refresh models** - działa przez universal endpoint  
✅ **Managed Assistants** - automatycznie dostępne w workflow  
✅ **System prompt support** - automatycznie obsługiwane  

## Co Musisz Zaimplementować:

1. ✅ **SettingsProviderInterface** - manifest + metody
2. ✅ **ChatClientInterface** - jeśli chcesz managed assistants
3. ✅ **Rejestracja przez filtry** - 2 linijki kodu

## Przykład: Claude (pełna implementacja)

Zobacz: `docs/examples/polytrans-deepseek/` - pełny przykład z wszystkimi plikami.

## Gotowe! 🎉

Po dodaniu tych 2-3 plików i rejestracji, Claude/Gemini/DeepSeek będą automatycznie dostępne w systemie.

