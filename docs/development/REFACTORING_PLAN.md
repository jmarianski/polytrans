# Plan Refaktoryzacji - Podejście DRY z Wzorcami Projektowymi

**Data utworzenia**: 2025-12-16  
**Wersja**: 2.0  
**Cel**: Wyekstraktować wspólne wzorce i duplikacje zamiast przenosić kod  
**Zintegrowano z**: Planem wersji 1.7.0

---

## 📊 Przegląd Największych Klas

| Klasa | Obecny rozmiar | Docelowy rozmiar | Priorytety |
|-------|---------------|------------------|------------|
| `OpenAISettingsProvider` | 1,127 linii | ~200 linii | 🔴 **PRIORYTET 1** |
| `WorkflowOutputProcessor` | 1,105 linii | ~200 linii | 🔴 **PRIORYTET 2** |
| `LogsManager` | 1,095 linii | ~200 linii | 🔴 **PRIORYTET 3** |
| `PostprocessingMenu` | 1,071 linii | ~200 linii | 🟡 **PRIORYTET 4** |
| `BackgroundProcessor` | 995 linii | ~200 linii | 🔴 **PRIORYTET 5** |
| `WorkflowManager` | 927 linii | ~200 linii | 🔴 **PRIORYTET 6** |
| `TranslationHandler` | 793 linii | ~200 linii | 🟡 **PRIORYTET 7** |

---

## 🔍 Analiza Duplikacji i Wspólnych Wzorców

### 1. Duplikacje w WorkflowOutputProcessor

**Problem**: Wszystkie metody `update_post_*` mają identyczną strukturę (~300 linii duplikacji).

**Wzorzec**: Strategy Pattern + Template Method

**Rozwiązanie**: `PostProcessing\Output\Actions\PostUpdateAction` (abstract) + concrete actions

---

### 2. Duplikacje w AJAX Handlers

**Problem**: Wszystkie AJAX handlers mają identyczną strukturę (~200 linii duplikacji).

**Wzorzec**: Template Method Pattern + Base AJAX Handler

**Rozwiązanie**: `Core\Ajax\BaseAjaxHandler`

---

### 3. Duplikacje w API Requests

**Problem**: Wiele klas wykonuje podobne API requests (~400 linii duplikacji).

**Wzorzec**: HTTP Client Wrapper + Response Parser

**Rozwiązanie**: `Core\Http\HttpClient` + `HttpResponse`

---

### 4. Duplikacje w Renderowaniu Tabel

**Problem**: Wiele miejsc renderuje podobne tabele HTML (~200 linii duplikacji).

**Wzorzec**: Table Builder / Renderer Utility

**Rozwiązanie**: `Core\UI\TableRenderer`

---

### 5. Duplikacje w Walidacji

**Problem**: Podobna logika walidacji w wielu miejscach (~150 linii duplikacji).

**Wzorzec**: Validator Utility Classes

**Rozwiązanie**: `Core\Validation\*`

---

## 🎯 Proponowane Rozwiązania

### 1. Strategy Pattern dla Post Updates

**Utility Class**: `PostProcessing\Output\Actions\PostUpdateAction`

```php
namespace PolyTrans\PostProcessing\Output\Actions;

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

// Concrete implementations (9 klas)
class PostTitleUpdateAction extends PostUpdateAction { /* ... */ }
class PostContentUpdateAction extends PostUpdateAction { /* ... */ }
class PostExcerptUpdateAction extends PostUpdateAction { /* ... */ }
class PostMetaUpdateAction extends PostUpdateAction { /* ... */ }
class PostStatusUpdateAction extends PostUpdateAction { /* ... */ }
class PostDateUpdateAction extends PostUpdateAction { /* ... */ }
class PostContentAppender extends PostUpdateAction { /* ... */ }
class PostContentPrepend extends PostUpdateAction { /* ... */ }
class OptionSaver extends PostUpdateAction { /* ... */ }
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

abstract class BaseAjaxHandler {
    final public function handle(): void {
        if (!$this->verify_nonce()) {
            $this->send_error(__('Security check failed.', 'polytrans'));
            return;
        }
        
        if (!$this->check_capabilities()) {
            $this->send_error(__('You do not have sufficient permissions.', 'polytrans'));
            return;
        }
        
        $input = $this->sanitize_input();
        $validation = $this->validate_input($input);
        if (!$validation['valid']) {
            $this->send_error($validation['error']);
            return;
        }
        
        try {
            $result = $this->execute_action($input);
            $this->send_success($result);
        } catch (\Exception $e) {
            $this->send_error($e->getMessage());
        }
    }
    
    abstract protected function get_nonce_action(): string;
    abstract protected function get_required_capability(): string;
    abstract protected function sanitize_input(): array;
    abstract protected function validate_input(array $input): array;
    abstract protected function execute_action(array $input): array;
    
    // Helper methods...
}
```

**Korzyści**:
- Eliminuje ~200 linii duplikacji w AJAX handlers
- Centralna obsługa security
- Spójny error handling
- Łatwe testowanie

---

### 3. HTTP Client Wrapper

**Utility Class**: `Core\Http\HttpClient` + `HttpResponse`

```php
namespace PolyTrans\Core\Http;

class HttpClient {
    private string $base_url;
    private array $default_headers = [];
    private int $timeout = 120;
    
    public function set_auth(string $type, string $value): self {
        if ($type === 'bearer') {
            $this->default_headers['Authorization'] = 'Bearer ' . $value;
        }
        return $this;
    }
    
    public function post(string $endpoint, array $data = []): HttpResponse {
        // Implementation...
    }
    
    public function get(string $endpoint, array $query = []): HttpResponse {
        // Implementation...
    }
}

class HttpResponse {
    public function is_success(): bool { /* ... */ }
    public function get_json(): ?array { /* ... */ }
    public function get_error_message(): string { /* ... */ }
}
```

**Korzyści**:
- Eliminuje ~400 linii duplikacji w API calls
- Spójna obsługa błędów
- Łatwe testowanie (mock HttpClient)
- Centralna konfiguracja

---

### 4. Table Renderer Utility

**Utility Class**: `Core\UI\TableRenderer`

```php
namespace PolyTrans\Core\UI;

class TableRenderer {
    public static function render(array $config): string {
        // Unified table rendering logic
    }
}
```

**Korzyści**:
- Eliminuje ~200 linii duplikacji w renderowaniu tabel
- Spójny wygląd tabel
- Łatwe utrzymanie

---

### 5. Validator Utility Classes

**Utility Classes**: `Core\Validation\*`

```php
namespace PolyTrans\Core\Validation;

abstract class BaseValidator {
    abstract public function validate($value, array $context = []): array;
}

class ApiKeyValidator extends BaseValidator { /* ... */ }
class SettingsValidator { /* ... */ }
```

**Korzyści**:
- Eliminuje ~150 linii duplikacji w walidacji
- Spójna walidacja
- Łatwe rozszerzanie

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

## 🎯 Plan Implementacji (zgodnie z 1.7.0)

### Faza 2: Utility Classes & Refactoring (1.5 tygodnia)

#### 2.1. HTTP Client Wrapper
- [ ] Utwórz `Core\Http\HttpClient`
- [ ] Utwórz `Core\Http\HttpResponse`
- [ ] Refaktoryzuj `OpenAIClient` używając HttpClient
- [ ] Refaktoryzuj `GeminiChatClientAdapter` używając HttpClient
- [ ] Refaktoryzuj `ClaudeChatClientAdapter` używając HttpClient
- [ ] Testy dla HttpClient

#### 2.2. Base AJAX Handler
- [ ] Utwórz `Core\Ajax\BaseAjaxHandler`
- [ ] Refaktoryzuj `OpenAISettingsProvider::ajax_validate_openai_key()`
- [ ] Refaktoryzuj inne AJAX handlers w providerach
- [ ] Testy dla BaseAjaxHandler

#### 2.3. Background Processor Refactoring
- [ ] Utwórz `Core\Background\ProcessSpawner`
- [ ] Utwórz `Core\Background\TaskProcessor`
- [ ] Utwórz `Core\Background\TokenManager`
- [ ] Refaktoryzuj `BackgroundProcessor` jako facade
- [ ] Testy

#### 2.4. Post Update Actions (Strategy Pattern)
- [ ] Utwórz abstract `PostUpdateAction`
- [ ] Utwórz wszystkie concrete actions (9 klas)
- [ ] Refaktoryzuj `WorkflowOutputProcessor` używając actions
- [ ] Testy

---

## ✅ Checklist Refaktoryzacji

### Utility Classes
- [ ] Utwórz `Core\Http\HttpClient` + `HttpResponse`
- [ ] Utwórz `Core\Ajax\BaseAjaxHandler`
- [ ] Utwórz `Core\Background\ProcessSpawner`, `TaskProcessor`, `TokenManager`
- [ ] Utwórz `PostProcessing\Output\Actions\PostUpdateAction` + 9 concrete actions
- [ ] Utwórz `Core\UI\TableRenderer`
- [ ] Utwórz `Core\Validation\*`

### Refaktoryzacja z użyciem Utilities
- [ ] Refaktoryzuj `WorkflowOutputProcessor` używając `PostUpdateAction`
- [ ] Refaktoryzuj AJAX handlers używając `BaseAjaxHandler`
- [ ] Refaktoryzuj API clients używając `HttpClient`
- [ ] Refaktoryzuj `BackgroundProcessor` jako facade

### Testy
- [ ] Testy dla utility classes
- [ ] Testy dla refaktoryzowanych klas
- [ ] Integration tests

---

## 📈 Metryki Sukcesu

### Przed Refaktoryzacją
- Największa klasa: 1,127 linii
- Średnia wielkość klasy: ~500 linii
- Liczba klas > 500 linii: 7
- Duplikacje: ~1,250 linii

### Po Refaktoryzacji
- Największa klasa: ~300 linii (max)
- Średnia wielkość klasy: ~200 linii
- Liczba klas > 500 linii: 0
- Liczba nowych utility classes: 15+
- Duplikacje: ~700 linii (44% redukcja)

---

**Ostatnia aktualizacja**: 2025-12-16  
**Zintegrowano z**: [VERSION_1.7.0_PLAN.md](../roadmap/VERSION_1.7.0_PLAN.md)
