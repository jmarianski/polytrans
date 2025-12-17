# Plan Refaktoryzacji - Podejście DRY z Wzorcami Projektowymi

**Data utworzenia**: 2025-12-16  
**Wersja**: 2.0  
**Cel**: Wyekstraktować wspólne wzorce i duplikacje zamiast przenosić kod

---

## 🔍 Analiza Duplikacji i Wspólnych Wzorców

### 1. Duplikacje w WorkflowOutputProcessor

**Problem**: Wszystkie metody `update_post_*` mają identyczną strukturę:

```php
private function update_post_title($value, $context) {
    $post_id = $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
    if (!$post_id) {
        return ['success' => false, 'error' => 'No post ID found in context'];
    }
    
    $result = wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($value)]);
    
    if (is_wp_error($result)) {
        return ['success' => false, 'error' => 'Failed to update post title: ' . $result->get_error_message()];
    }
    
    return ['success' => true, 'message' => sprintf('Updated post title for post ID %d', $post_id)];
}
```

**Wzorzec**: Strategy Pattern + Template Method

---

### 2. Duplikacje w AJAX Handlers

**Problem**: Wszystkie AJAX handlers mają identyczną strukturę:

```php
public function ajax_validate_openai_key() {
    // Check nonce
    $nonce_check = wp_verify_nonce($_POST['nonce'], 'polytrans_openai_nonce');
    if (!$nonce_check) {
        wp_send_json_error(__('Security check failed.', 'polytrans'));
    }
    
    // Check capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions...', 'polytrans'));
    }
    
    // Sanitize input
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    
    // Validate
    if (empty($api_key)) {
        wp_send_json_error(__('API key is required.', 'polytrans'));
    }
    
    // Execute action
    $is_valid = $this->validate_openai_api_key($api_key);
    
    // Return response
    if ($is_valid) {
        wp_send_json_success(__('API key is valid!', 'polytrans'));
    } else {
        wp_send_json_error(__('Invalid API key.', 'polytrans'));
    }
}
```

**Wzorzec**: Template Method Pattern + Base AJAX Handler

---

### 3. Duplikacje w API Requests

**Problem**: Wiele klas wykonuje podobne API requests:

```php
// W OpenAIClient, GeminiChatClientAdapter, ClaudeChatClientAdapter, etc.
$response = wp_remote_post($url, [
    'headers' => ['Authorization' => 'Bearer ' . $api_key],
    'body' => json_encode($body),
    'timeout' => 120
]);

if (is_wp_error($response)) {
    return ['success' => false, 'error' => $response->get_error_message()];
}

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

if ($status_code !== 200) {
    return ['success' => false, 'error' => 'API error'];
}

$data = json_decode($body, true);
return ['success' => true, 'data' => $data];
```

**Wzorzec**: HTTP Client Wrapper + Response Parser

---

### 4. Duplikacje w Renderowaniu Tabel

**Problem**: Wiele miejsc renderuje podobne tabele HTML (assistants, path rules, language pairs)

**Wzorzec**: Table Builder / Renderer Utility

---

### 5. Duplikacje w Walidacji

**Problem**: Podobna logika walidacji w wielu miejscach (API keys, settings, etc.)

**Wzorzec**: Validator Utility Classes

---

## 🎯 Proponowane Rozwiązania

### 1. Strategy Pattern dla Post Updates

**Utility Class**: `PostProcessing\Output\Actions\PostUpdateAction`

```php
namespace PolyTrans\PostProcessing\Output\Actions;

/**
 * Abstract base class for post update actions
 * Uses Strategy Pattern to handle different update types
 */
abstract class PostUpdateAction {
    protected function get_post_id(array $context): ?int {
        return $context['translated_post_id'] ?? $context['original_post_id'] ?? null;
    }
    
    protected function validate_post_id(?int $post_id): array {
        if (!$post_id) {
            return ['success' => false, 'error' => 'No post ID found in context'];
        }
        return ['success' => true];
    }
    
    protected function update_post(array $post_data): array {
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => sprintf('Failed to update post: %s', $result->get_error_message())
            ];
        }
        
        return ['success' => true, 'post_id' => $result];
    }
    
    abstract protected function prepare_post_data($value, int $post_id): array;
    abstract protected function get_action_name(): string;
    
    public function execute($value, array $context): array {
        $validation = $this->validate_post_id($this->get_post_id($context));
        if (!$validation['success']) {
            return $validation;
        }
        
        $post_id = $this->get_post_id($context);
        $post_data = $this->prepare_post_data($value, $post_id);
        $result = $this->update_post($post_data);
        
        if ($result['success']) {
            $result['message'] = sprintf(
                'Updated %s for post ID %d',
                $this->get_action_name(),
                $post_id
            );
        }
        
        return $result;
    }
}

// Concrete implementations
class PostTitleUpdateAction extends PostUpdateAction {
    protected function prepare_post_data($value, int $post_id): array {
        return ['ID' => $post_id, 'post_title' => sanitize_text_field($value)];
    }
    protected function get_action_name(): string {
        return 'post title';
    }
}

class PostContentUpdateAction extends PostUpdateAction {
    protected function prepare_post_data($value, int $post_id): array {
        return ['ID' => $post_id, 'post_content' => wp_kses_post($value)];
    }
    protected function get_action_name(): string {
        return 'post content';
    }
}

class PostExcerptUpdateAction extends PostUpdateAction {
    protected function prepare_post_data($value, int $post_id): array {
        return ['ID' => $post_id, 'post_excerpt' => sanitize_textarea_field($value)];
    }
    protected function get_action_name(): string {
        return 'post excerpt';
    }
}

class PostMetaUpdateAction extends PostUpdateAction {
    private string $meta_key;
    
    public function __construct(string $meta_key) {
        $this->meta_key = sanitize_key($meta_key);
    }
    
    protected function prepare_post_data($value, int $post_id): array {
        // Override - use update_post_meta instead
        update_post_meta($post_id, $this->meta_key, $value);
        return ['ID' => $post_id]; // Dummy for parent
    }
    
    public function execute($value, array $context): array {
        $post_id = $this->get_post_id($context);
        if (!$post_id) {
            return ['success' => false, 'error' => 'No post ID found in context'];
        }
        
        update_post_meta($post_id, $this->meta_key, $value);
        return [
            'success' => true,
            'message' => sprintf('Updated post meta "%s" for post ID %d', $this->meta_key, $post_id)
        ];
    }
    
    protected function get_action_name(): string {
        return sprintf('post meta "%s"', $this->meta_key);
    }
}
```

**Korzyści**:
- Eliminuje ~300 linii duplikacji
- Łatwe dodawanie nowych typów akcji
- Centralna obsługa błędów
- Testowalne

---

### 2. Base AJAX Handler (Template Method Pattern)

**Utility Class**: `Core\Ajax\BaseAjaxHandler`

```php
namespace PolyTrans\Core\Ajax;

/**
 * Base class for AJAX handlers
 * Implements Template Method Pattern for common AJAX flow
 */
abstract class BaseAjaxHandler {
    /**
     * Template method - defines the skeleton of AJAX handling
     */
    final public function handle(): void {
        // Step 1: Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error(__('Security check failed.', 'polytrans'));
            return;
        }
        
        // Step 2: Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error(__('You do not have sufficient permissions.', 'polytrans'));
            return;
        }
        
        // Step 3: Sanitize input
        $input = $this->sanitize_input();
        
        // Step 4: Validate input
        $validation = $this->validate_input($input);
        if (!$validation['valid']) {
            $this->send_error($validation['error']);
            return;
        }
        
        // Step 5: Execute action (hook method)
        try {
            $result = $this->execute_action($input);
            $this->send_success($result);
        } catch (\Exception $e) {
            $this->send_error($e->getMessage());
        }
    }
    
    // Hook methods - to be implemented by subclasses
    abstract protected function get_nonce_action(): string;
    abstract protected function get_required_capability(): string;
    abstract protected function sanitize_input(): array;
    abstract protected function validate_input(array $input): array;
    abstract protected function execute_action(array $input): array;
    
    // Helper methods
    protected function verify_nonce(): bool {
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        return wp_verify_nonce($nonce, $this->get_nonce_action());
    }
    
    protected function check_capabilities(): bool {
        return current_user_can($this->get_required_capability());
    }
    
    protected function send_success(array $data = []): void {
        wp_send_json_success($data);
    }
    
    protected function send_error(string $message): void {
        wp_send_json_error($message);
    }
}

// Example usage
namespace PolyTrans\Providers\OpenAI\Ajax;

use PolyTrans\Core\Ajax\BaseAjaxHandler;

class ValidateApiKeyHandler extends BaseAjaxHandler {
    protected function get_nonce_action(): string {
        return 'polytrans_openai_nonce';
    }
    
    protected function get_required_capability(): string {
        return 'manage_options';
    }
    
    protected function sanitize_input(): array {
        return [
            'api_key' => sanitize_text_field($_POST['api_key'] ?? '')
        ];
    }
    
    protected function validate_input(array $input): array {
        if (empty($input['api_key'])) {
            return ['valid' => false, 'error' => __('API key is required.', 'polytrans')];
        }
        return ['valid' => true];
    }
    
    protected function execute_action(array $input): array {
        $validator = new \PolyTrans\Providers\OpenAI\Settings\ApiKeyValidator();
        $is_valid = $validator->validate($input['api_key']);
        
        return [
            'valid' => $is_valid,
            'message' => $is_valid 
                ? __('API key is valid!', 'polytrans')
                : __('Invalid API key.', 'polytrans')
        ];
    }
}
```

**Korzyści**:
- Eliminuje ~200 linii duplikacji w AJAX handlers
- Centralna obsługa security
- Spójny error handling
- Łatwe testowanie

---

### 3. HTTP Client Wrapper

**Utility Class**: `Core\Http\HttpClient`

```php
namespace PolyTrans\Core\Http;

/**
 * HTTP Client wrapper for consistent API requests
 * Handles common patterns: auth, errors, timeouts, retries
 */
class HttpClient {
    private string $base_url;
    private array $default_headers = [];
    private int $timeout = 120;
    private bool $verify_ssl = true;
    
    public function __construct(string $base_url, array $config = []) {
        $this->base_url = rtrim($base_url, '/');
        $this->default_headers = $config['headers'] ?? [];
        $this->timeout = $config['timeout'] ?? 120;
        $this->verify_ssl = $config['verify_ssl'] ?? true;
    }
    
    public function set_auth(string $type, string $value): self {
        if ($type === 'bearer') {
            $this->default_headers['Authorization'] = 'Bearer ' . $value;
        } elseif ($type === 'api_key') {
            $this->default_headers['X-API-Key'] = $value;
        }
        return $this;
    }
    
    public function post(string $endpoint, array $data = [], array $options = []): HttpResponse {
        return $this->request('POST', $endpoint, $data, $options);
    }
    
    public function get(string $endpoint, array $query = [], array $options = []): HttpResponse {
        $url = $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url, null, $options);
    }
    
    private function request(string $method, string $endpoint, $body = null, array $options = []): HttpResponse {
        $url = $this->base_url . '/' . ltrim($endpoint, '/');
        $headers = array_merge($this->default_headers, $options['headers'] ?? []);
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => $options['timeout'] ?? $this->timeout,
            'sslverify' => $options['verify_ssl'] ?? $this->verify_ssl,
        ];
        
        if ($body !== null) {
            $args['body'] = is_array($body) ? json_encode($body) : $body;
            if (!isset($headers['Content-Type'])) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        }
        
        $response = wp_remote_request($url, $args);
        
        return new HttpResponse($response);
    }
}

/**
 * HTTP Response wrapper
 */
class HttpResponse {
    private $wp_response;
    
    public function __construct($wp_response) {
        $this->wp_response = $wp_response;
    }
    
    public function is_error(): bool {
        return is_wp_error($this->wp_response);
    }
    
    public function get_error(): ?string {
        return is_wp_error($this->wp_response) 
            ? $this->wp_response->get_error_message() 
            : null;
    }
    
    public function get_status_code(): int {
        return wp_remote_retrieve_response_code($this->wp_response);
    }
    
    public function get_body(): string {
        return wp_remote_retrieve_body($this->wp_response);
    }
    
    public function get_json(): ?array {
        $body = $this->get_body();
        $data = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
    
    public function is_success(): bool {
        return !$this->is_error() && $this->get_status_code() >= 200 && $this->get_status_code() < 300;
    }
    
    public function get_error_message(): string {
        if ($this->is_error()) {
            return $this->get_error();
        }
        
        $json = $this->get_json();
        if (isset($json['error']['message'])) {
            return $json['error']['message'];
        }
        
        return sprintf('HTTP %d: %s', $this->get_status_code(), $this->get_body());
    }
}

// Usage example
$client = new HttpClient('https://api.openai.com/v1');
$client->set_auth('bearer', $api_key);

$response = $client->post('/chat/completions', [
    'model' => 'gpt-4',
    'messages' => [...]
]);

if ($response->is_success()) {
    $data = $response->get_json();
    // Process data
} else {
    $error = $response->get_error_message();
    // Handle error
}
```

**Korzyści**:
- Eliminuje ~400 linii duplikacji w API calls
- Spójna obsługa błędów
- Łatwe testowanie (mock HttpClient)
- Centralna konfiguracja (timeout, SSL, etc.)

---

### 4. Table Renderer Utility

**Utility Class**: `Core\UI\TableRenderer`

```php
namespace PolyTrans\Core\UI;

/**
 * Utility class for rendering HTML tables
 * Reduces duplication in table rendering code
 */
class TableRenderer {
    public static function render(array $config): string {
        $columns = $config['columns'] ?? [];
        $rows = $config['rows'] ?? [];
        $attributes = $config['attributes'] ?? [];
        $row_attributes = $config['row_attributes'] ?? [];
        
        $html = '<table' . self::build_attributes($attributes) . '>';
        
        // Header
        if (!empty($columns)) {
            $html .= '<thead><tr>';
            foreach ($columns as $column) {
                $html .= '<th' . self::build_attributes($column['attributes'] ?? []) . '>';
                $html .= esc_html($column['label'] ?? '');
                $html .= '</th>';
            }
            $html .= '</tr></thead>';
        }
        
        // Body
        $html .= '<tbody>';
        foreach ($rows as $row) {
            $row_attrs = is_callable($row_attributes) 
                ? $row_attributes($row) 
                : ($row_attributes[$row['key'] ?? ''] ?? []);
            
            $html .= '<tr' . self::build_attributes($row_attrs) . '>';
            
            foreach ($columns as $column) {
                $html .= '<td>';
                $value = self::get_cell_value($row, $column);
                $html .= is_callable($column['render']) 
                    ? $column['render']($value, $row) 
                    : esc_html($value);
                $html .= '</td>';
            }
            
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        
        return $html;
    }
    
    private static function get_cell_value(array $row, array $column): mixed {
        $key = $column['key'] ?? '';
        if (is_callable($column['value'])) {
            return $column['value']($row);
        }
        return $row[$key] ?? '';
    }
    
    private static function build_attributes(array $attributes): string {
        $html = '';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        return $html;
    }
}

// Usage example
echo TableRenderer::render([
    'columns' => [
        ['key' => 'pair', 'label' => __('Translation Pair', 'polytrans')],
        ['key' => 'assistant', 'label' => __('Assistant', 'polytrans'), 
         'render' => function($value, $row) {
             return '<select>' . /* ... */ . '</select>';
         }]
    ],
    'rows' => $language_pairs,
    'attributes' => ['class' => 'widefat fixed striped', 'id' => 'assistants-table'],
    'row_attributes' => function($row) {
        return ['data-pair' => $row['key']];
    }
]);
```

**Korzyści**:
- Eliminuje ~200 linii duplikacji w renderowaniu tabel
- Spójny wygląd tabel
- Łatwe utrzymanie
- Możliwość użycia w Twig templates

---

### 5. Validator Utility Classes

**Utility Classes**: `Core\Validation\*`

```php
namespace PolyTrans\Core\Validation;

/**
 * Base validator with common validation patterns
 */
abstract class BaseValidator {
    abstract public function validate($value, array $context = []): array;
    
    protected function success(): array {
        return ['valid' => true, 'errors' => []];
    }
    
    protected function error(string $message, string $field = ''): array {
        return [
            'valid' => false,
            'errors' => [$field => $message]
        ];
    }
}

/**
 * API Key Validator - can be extended for different providers
 */
class ApiKeyValidator extends BaseValidator {
    private string $provider_id;
    private string $validation_endpoint;
    
    public function __construct(string $provider_id, string $validation_endpoint) {
        $this->provider_id = $provider_id;
        $this->validation_endpoint = $validation_endpoint;
    }
    
    public function validate(string $api_key, array $context = []): array {
        if (empty($api_key)) {
            return $this->error(__('API key is required.', 'polytrans'), 'api_key');
        }
        
        // Provider-specific validation
        $client = $this->create_client($api_key);
        $is_valid = $client->validate();
        
        if (!$is_valid) {
            return $this->error(__('Invalid API key.', 'polytrans'), 'api_key');
        }
        
        return $this->success();
    }
    
    abstract protected function create_client(string $api_key): ApiKeyValidationClient;
}

/**
 * Settings Validator - validates provider settings
 */
class SettingsValidator {
    public static function validate_required_fields(array $data, array $required_fields): array {
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = sprintf(__('%s is required.', 'polytrans'), $field);
            }
        }
        
        return empty($errors) 
            ? ['valid' => true, 'errors' => []] 
            : ['valid' => false, 'errors' => $errors];
    }
    
    public static function sanitize_text_field(string $value): string {
        return sanitize_text_field($value);
    }
    
    public static function sanitize_array(array $data, array $rules): array {
        $sanitized = [];
        foreach ($rules as $key => $rule) {
            if (isset($data[$key])) {
                $sanitized[$key] = self::apply_sanitization($data[$key], $rule);
            }
        }
        return $sanitized;
    }
    
    private static function apply_sanitization($value, string $rule) {
        switch ($rule) {
            case 'text':
                return sanitize_text_field($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            case 'key':
                return sanitize_key($value);
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            default:
                return $value;
        }
    }
}
```

**Korzyści**:
- Eliminuje ~150 linii duplikacji w walidacji
- Spójna walidacja
- Łatwe rozszerzanie
- Reużywalne

---

## 📊 Podsumowanie Refaktoryzacji

### Przed Refaktoryzacją

| Klasa | Rozmiar | Duplikacje |
|-------|---------|------------|
| `WorkflowOutputProcessor` | 1,105 linii | ~300 linii (update methods) |
| `OpenAISettingsProvider` | 1,127 linii | ~200 linii (AJAX handlers) |
| Różne API clients | ~500 linii | ~400 linii (HTTP requests) |
| Różne renderers | ~300 linii | ~200 linii (tables) |
| Różne validators | ~200 linii | ~150 linii (validation) |

**Łączne duplikacje**: ~1,250 linii

### Po Refaktoryzacji

**Nowe Utility Classes**:
1. `PostProcessing\Output\Actions\PostUpdateAction` (Strategy) - ~150 linii
2. `Core\Ajax\BaseAjaxHandler` (Template Method) - ~100 linii
3. `Core\Http\HttpClient` + `HttpResponse` - ~200 linii
4. `Core\UI\TableRenderer` - ~100 linii
5. `Core\Validation\*` - ~150 linii

**Razem**: ~700 linii utility classes

**Redukcja duplikacji**: ~1,250 linii → ~700 linii (44% redukcja)

**Redukcja w głównych klasach**:
- `WorkflowOutputProcessor`: 1,105 → ~400 linii (-64%)
- `OpenAISettingsProvider`: 1,127 → ~600 linii (-47%)
- API clients: ~500 → ~200 linii (-60%)

---

## 🎯 Plan Implementacji

### Faza 1: Utility Classes (1 tydzień)
1. **Dzień 1-2**: `HttpClient` + `HttpResponse`
2. **Dzień 3**: `BaseAjaxHandler`
3. **Dzień 4**: `PostUpdateAction` (Strategy)
4. **Dzień 5**: `TableRenderer` + `Validators`

### Faza 2: Refaktoryzacja z użyciem Utilities (1 tydzień)
1. **Dzień 1-2**: Refaktoryzuj `WorkflowOutputProcessor` używając `PostUpdateAction`
2. **Dzień 3-4**: Refaktoryzuj AJAX handlers używając `BaseAjaxHandler`
3. **Dzień 5**: Refaktoryzuj API clients używając `HttpClient`

### Faza 3: Testy i dokumentacja (2-3 dni)
1. Testy dla utility classes
2. Aktualizacja dokumentacji
3. Przykłady użycia

---

## ✅ Checklist

- [ ] Utwórz `Core\Http\HttpClient`
- [ ] Utwórz `Core\Ajax\BaseAjaxHandler`
- [ ] Utwórz `PostProcessing\Output\Actions\PostUpdateAction`
- [ ] Utwórz `Core\UI\TableRenderer`
- [ ] Utwórz `Core\Validation\*`
- [ ] Refaktoryzuj `WorkflowOutputProcessor`
- [ ] Refaktoryzuj AJAX handlers
- [ ] Refaktoryzuj API clients
- [ ] Testy
- [ ] Dokumentacja

---

**Ostatnia aktualizacja**: 2025-12-16

