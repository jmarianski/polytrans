# Version 1.7.0 - Comprehensive Refactoring & Extensibility

**Target Release**: Q1 2026  
**Status**: Planning  
**Created**: 2025-12-16  
**Updated**: 2025-12-16  
**Goal**: Complete PSR-4 migration, introduce extensibility hooks, refactor utilities, clean documentation, enable behavior modification

---

## 🎯 Główne Cele Wersji 1.7.0

1. **Dokończenie migracji PSR-4** - Usunięcie wszystkich `PolyTrans_Class_Name` referencji (377 referencji)
2. **System rozszerzalności** - Hooki i interceptory dla modyfikacji zachowania serwisu
3. **Refaktoryzacja utilities** - HttpClient, BaseAjaxHandler, BackgroundProcessor jako utils
4. **Porządkowanie dokumentacji** - Archiwizacja, konsolidacja, aktualizacja
5. **Rozszerzalność pathingów** - Możliwość modyfikacji ścieżek translacji
6. **Rozszerzalność manifestów** - Dodawanie handlerów do endpointów

---

## 🎯 Główne Cele Wersji 1.7.0

1. **Dokończenie migracji PSR-4** - Usunięcie wszystkich `PolyTrans_Class_Name` referencji
2. **System rozszerzalności** - Hooki i interceptory dla modyfikacji zachowania
3. **Refaktoryzacja utilities** - HttpClient, BaseAjaxHandler, etc.
4. **Porządkowanie dokumentacji** - Archiwizacja, konsolidacja, aktualizacja
5. **Rozszerzalność pathingów** - Możliwość modyfikacji ścieżek translacji
6. **Rozszerzalność manifestów** - Dodawanie handlerów do endpointów

---

## 📋 Plan Działania

### Faza 1: Porządkowanie Dokumentacji (1 tydzień)

#### 1.1. Archiwizacja Przestarzałych Dokumentów
- [ ] Utwórz `docs/archive/` z podkatalogami
- [ ] Przenieś `development/phase-0/` → `archive/phase-0/` (Phase 0 zakończona)
- [ ] Przenieś `roadmap/VERSION_1.6.0_*` → `archive/roadmap/` (wersja wydana)
- [ ] Przenieś `planning/REFACTORING_AUTOLOADER_PLAN.md` → `archive/planning/` (PSR-4 zrealizowany)
- [ ] Sprawdź i zarchiwizuj `planning/ASSISTANTS_SYSTEM_PLAN.md` jeśli zrealizowany

#### 1.2. Konsolidacja Duplikatów
- [ ] Połącz `REFACTORING_PLAN.md` + `REFACTORING_PLAN_DRY.md` → `REFACTORING_PLAN.md`
- [ ] Połącz `QUALITY_ASSESSMENT.md` + `QUALITY_IMPROVEMENTS.md` → `QUALITY_ASSESSMENT.md`
- [ ] Sprawdź i połącz `EXTERNAL_PLUGIN_QUICK_START.md` + `QUICK_START_ADD_PROVIDER.md`

#### 1.3. Reorganizacja Struktury
- [ ] Przenieś `ASSISTANTS_USER_GUIDE.md` → `user-guide/ASSISTANTS.md`
- [ ] Przenieś `EXTERNAL_PLUGIN_QUICK_START.md` → `developer/`
- [ ] Zaktualizuj `TWIG_MIGRATION_STATUS.md` (status: complete)

#### 1.4. Aktualizacja Indeksów i Naprawa Dokumentacji
- [x] Zaktualizuj `INDEX.md` (wersja 1.3.5 → 1.7.0)
- [x] Zaktualizuj `README.md` (wersja 1.5.0 → 1.6.14, linki, struktura)
- [x] Napraw wszystkie broken links (phase-0/, QUICK_START.md)
- [x] Standaryzuj formatowanie

#### 1.5. Aktualizacja Zawartości Dokumentacji
- [x] Utworzyć zawartość `QUICK_START.md` (pusty plik)
- [x] Zaktualizować `polytrans.php` POLYTRANS_VERSION (1.6.12 → 1.6.14)
- [x] Ujednolicić PHP requirements (8.1+ wszędzie)
- [x] Zaktualizować `CONTRIBUTING.md` (PSR-4 zamiast PolyTrans_Class_Name)
- [x] Dodać Claude i Gemini do `INSTALLATION.md`
- [x] Zweryfikować i zsynchronizować API endpoints
- [x] Dodać Quick Start do `PROVIDER_EXTENSIBILITY_GUIDE.md`
- [x] Zaktualizować `CONFIGURATION.md` z Claude i Gemini
- [x] Zaktualizować `INTERFACE.md` z poprawną strukturą menu
- [x] Zaktualizować `API-DOCUMENTATION.md` z rzeczywistymi endpointami

**Deliverables**: Czysta struktura dokumentacji, zaktualizowane indeksy, aktualna zawartość

---

### Faza 2: Utility Classes & Refactoring (1.5 tygodnia)

**Priorytety**:
1. HttpClient + HttpResponse (największa redukcja duplikacji)
2. BaseAjaxHandler (używane wszędzie)
3. BackgroundProcessor refactoring (jako util)
4. Post Update Actions (Strategy pattern)

#### 2.1. HTTP Client Wrapper
**Cel**: Eliminacja duplikacji w API requests (~400 linii)

**Nowe klasy**:
- `Core\Http\HttpClient` - Wrapper dla wp_remote_*
- `Core\Http\HttpResponse` - Response wrapper z parsowaniem

**Lokalizacja**: `includes/Core/Http/`

**Użycie**:
```php
$client = new HttpClient('https://api.openai.com/v1');
$client->set_auth('bearer', $api_key);
$response = $client->post('/chat/completions', $data);

if ($response->is_success()) {
    $data = $response->get_json();
} else {
    $error = $response->get_error_message();
}
```

**Refaktoryzacja**:
- [x] Utwórz `Core\Http\HttpClient`
- [x] Utwórz `Core\Http\HttpResponse`
- [x] Refaktoryzuj `OpenAIClient` używając HttpClient
- [x] Refaktoryzuj `GeminiChatClientAdapter` używając HttpClient
- [x] Refaktoryzuj `ClaudeChatClientAdapter` używając HttpClient
- [x] Refaktoryzuj `GeminiSettingsProvider` (fetch models)
- [x] Refaktoryzuj `ClaudeSettingsProvider` (fetch models)
- [ ] Testy dla HttpClient

**Szacowana redukcja**: ~400 linii duplikacji → ~200 linii utility

---

#### 2.2. Base AJAX Handler (Template Method Pattern)
**Cel**: Eliminacja duplikacji w AJAX handlers (~200 linii)

**Nowa klasa**:
- `Core\Ajax\BaseAjaxHandler` - Template method dla AJAX

**Lokalizacja**: `includes/Core/Ajax/`

**Użycie**:
```php
class ValidateApiKeyHandler extends BaseAjaxHandler {
    protected function get_nonce_action(): string {
        return 'polytrans_openai_nonce';
    }
    
    protected function execute_action(array $input): array {
        // Tylko logika biznesowa
    }
}
```

**Refaktoryzacja**:
- [ ] Utwórz `Core\Ajax\BaseAjaxHandler`
- [ ] Refaktoryzuj `OpenAISettingsProvider::ajax_validate_openai_key()`
- [ ] Refaktoryzuj `OpenAISettingsProvider::ajax_load_openai_assistants()`
- [ ] Refaktoryzuj `OpenAISettingsProvider::ajax_get_openai_models()`
- [ ] Refaktoryzuj inne AJAX handlers w providerach
- [ ] Testy dla BaseAjaxHandler

**Szacowana redukcja**: ~200 linii duplikacji → ~100 linii utility

---

#### 2.3. Background Processor Refactoring
**Cel**: Refaktoryzacja BackgroundProcessor jako utility

**Nowe klasy**:
- `Core\Background\ProcessSpawner` - Spawning procesów
- `Core\Background\TaskProcessor` - Wykonywanie zadań
- `Core\Background\TokenManager` - Zarządzanie tokenami

**Lokalizacja**: `includes/Core/Background/`

**Refaktoryzacja**:
- [ ] Utwórz `Core\Background\ProcessSpawner`
- [ ] Utwórz `Core\Background\TaskProcessor`
- [ ] Utwórz `Core\Background\TokenManager`
- [ ] Refaktoryzuj `BackgroundProcessor` jako facade
- [ ] Testy

**Szacowana redukcja**: 995 linii → ~50 linii facade + 3 utility classes

---

#### 2.4. Post Update Actions (Strategy Pattern)
**Cel**: Eliminacja duplikacji w WorkflowOutputProcessor (~300 linii)

**Nowe klasy**:
- `PostProcessing\Output\Actions\PostUpdateAction` (abstract)
- `PostProcessing\Output\Actions\PostTitleUpdateAction`
- `PostProcessing\Output\Actions\PostContentUpdateAction`
- `PostProcessing\Output\Actions\PostExcerptUpdateAction`
- `PostProcessing\Output\Actions\PostMetaUpdateAction`
- `PostProcessing\Output\Actions\PostStatusUpdateAction`
- `PostProcessing\Output\Actions\PostDateUpdateAction`
- `PostProcessing\Output\Actions\PostContentAppender`
- `PostProcessing\Output\Actions\PostContentPrepend`
- `PostProcessing\Output\Actions\OptionSaver`

**Lokalizacja**: `includes/PostProcessing/Output/Actions/`

**Refaktoryzacja**:
- [ ] Utwórz abstract `PostUpdateAction`
- [ ] Utwórz wszystkie concrete actions (9 klas)
- [ ] Refaktoryzuj `WorkflowOutputProcessor` używając actions
- [ ] Testy

**Szacowana redukcja**: ~300 linii duplikacji → ~150 linii utility + 9 action classes

---

**Deliverables**: Utility classes, refaktoryzowane klasy używające utilities

---

### Faza 3: Dokończenie Migracji PSR-4 (1 tydzień)

**Priorytety**:
1. Zamiana wszystkich 377 referencji `PolyTrans_` na PSR-4
2. Usunięcie `LegacyAutoloader.php`
3. Aktualizacja `Bootstrap.php`
4. Zachowanie `Compatibility.php` dla BC

#### 3.1. Analiza Obecnych Referencji
**Cel**: Znalezienie wszystkich `PolyTrans_Class_Name` referencji

**Obecny stan**:
- **377 referencji** do `PolyTrans_` w kodzie
- `LegacyAutoloader` jest pusty (wszystkie klasy zmigrowane)
- `Compatibility.php` ma ~50 aliasów dla backward compatibility
- Większość referencji to użycia przez aliases

**Zadania**:
- [ ] Znajdź wszystkie `PolyTrans_::` static calls (grep: `PolyTrans_[A-Z]`)
- [ ] Znajdź wszystkie `\PolyTrans_` użycia
- [ ] Utwórz listę wszystkich referencji do zamiany
- [ ] Zidentyfikuj które klasy są używane tylko przez aliases

---

#### 3.2. Zamiana Referencji na PSR-4
**Cel**: Zamiana wszystkich `PolyTrans_` referencji na `\PolyTrans\` namespace

**Klasy główne do migracji**:
- [ ] `PolyTrans` (main class) → `PolyTrans\Core\Plugin` lub pozostawić jako facade
- [ ] Wszystkie użycia `PolyTrans_Logs_Manager` → `\PolyTrans\Core\LogsManager`
- [ ] Wszystkie użycia `PolyTrans_Workflow_Manager` → `\PolyTrans\PostProcessing\WorkflowManager`
- [ ] Wszystkie użycia `PolyTrans_Background_Processor` → `\PolyTrans\Core\BackgroundProcessor`
- [ ] Wszystkie użycia `PolyTrans_Translation_Handler` → `\PolyTrans\Scheduler\TranslationHandler`
- [ ] Wszystkie użycia `PolyTrans_Translation_Extension` → `\PolyTrans\Core\TranslationExtension`
- [ ] Wszystkie użycia `PolyTrans_Translation_Coordinator` → `\PolyTrans\Receiver\TranslationCoordinator`
- [ ] Wszystkie użycia `PolyTrans_*` w providerach → odpowiednie namespace
- [ ] Wszystkie użycia `PolyTrans_*` w receiver/managers → odpowiednie namespace
- [ ] Wszystkie użycia `PolyTrans_*` w postprocessing → odpowiednie namespace

**Strategia**:
1. Zamień wszystkie referencje w kodzie na PSR-4
2. Zachowaj `Compatibility.php` dla backward compatibility (zewnętrzne pluginy)
3. Usuń `LegacyAutoloader.php` (już pusty)
4. Zaktualizuj `Bootstrap.php` (usuń legacy autoloader)

---

#### 3.3. Automatyczna Zamiana Referencji
**Cel**: Masowa zamiana wszystkich referencji

**Narzędzia**:
- [ ] Utwórz skrypt migracyjny lub użyj find/replace z regex
- [ ] Pattern: `PolyTrans_([A-Z][a-zA-Z_]+)` → `\PolyTrans\{namespace}\{Class}`
- [ ] Pattern: `\PolyTrans_([A-Z][a-zA-Z_]+)` → `\PolyTrans\{namespace}\{Class}`

**Pliki do aktualizacji** (40 plików z referencjami):
- [ ] `includes/Menu/*.php` (6 plików)
- [ ] `includes/Core/*.php` (13 plików)
- [ ] `includes/PostProcessing/*.php` (16 plików)
- [ ] `includes/Providers/*.php` (19 plików)
- [ ] `includes/Receiver/*.php` (11 plików)
- [ ] `includes/Scheduler/*.php` (2 pliki)
- [ ] `includes/class-polytrans.php` (główny plik)

**Zadania**:
- [ ] Zamień wszystkie `PolyTrans_Class::method()` → `\PolyTrans\Namespace\Class::method()`
- [ ] Zamień wszystkie `\PolyTrans_Class::method()` → `\PolyTrans\Namespace\Class::method()`
- [ ] Dodaj `use` statements na początku plików
- [ ] Zaktualizuj type hints w PHPDoc
- [ ] Zaktualizuj type hints w parametrach (jeśli możliwe)

---

#### 3.4. Usunięcie Legacy Code
**Cel**: Usunięcie przestarzałego kodu

**Zadania**:
- [ ] Usuń `LegacyAutoloader.php` (już pusty, wszystkie klasy zmigrowane)
- [ ] Zaktualizuj `Bootstrap.php` (usuń `registerLegacyAutoloader()`)
- [ ] Zaktualizuj `class-polytrans.php` (użyj PSR-4 zamiast legacy)
- [ ] **Zachowaj** `Compatibility.php` (potrzebne dla zewnętrznych pluginów)
- [ ] Dodaj komentarz w `Compatibility.php` że aliases są tylko dla BC
- [ ] Zaktualizuj dokumentację o deprecation notice dla `PolyTrans_` klas

---

**Deliverables**: Wszystkie klasy w PSR-4, brak `PolyTrans_` referencji

---

### Faza 4: System Rozszerzalności - Hooki i Interceptory (2 tygodnie)

**Priorytety**:
1. Path resolution hooks (najważniejsze - modyfikacja pathingów)
2. Translation flow hooks
3. Workflow execution hooks
4. Output processing hooks
5. Manifest extensions
6. Endpoint handler registry

#### 4.1. Hook System dla Translation Flow
**Cel**: Umożliwienie modyfikacji procesu translacji

**Nowe hooki**:

```php
// Pre-translation hooks
do_action('polytrans_before_prepare_content', $post_id, $source_lang, $target_lang, $content);
$content = apply_filters('polytrans_prepare_content', $content, $post_id, $source_lang, $target_lang);

// Pre-provider hooks
do_action('polytrans_before_translate', $content, $source_lang, $target_lang, $provider_id, $settings);
$content = apply_filters('polytrans_pre_translate', $content, $source_lang, $target_lang, $provider_id, $settings);

// Post-translation hooks
$translation_result = apply_filters('polytrans_post_translate', $translation_result, $content, $source_lang, $target_lang, $provider_id, $settings);
do_action('polytrans_after_translate', $translation_result, $content, $source_lang, $target_lang);

// Pre-create post hooks
do_action('polytrans_before_create_translated_post', $translation_result, $original_post_id, $source_lang, $target_lang);
$translation_result = apply_filters('polytrans_pre_create_translated_post', $translation_result, $original_post_id, $source_lang, $target_lang);

// Post-create hooks
do_action('polytrans_translation_complete', $original_post_id, $translated_post_id, $source_lang, $target_lang, $translation_result);
```

**Implementacja**:
- [ ] Dodaj hooki w `TranslationHandler::translate()`
- [ ] Dodaj hooki w `TranslationExtension::handle_translation_request()`
- [ ] Dodaj hooki w `Receiver\PostCreator::create_translated_post()`
- [ ] Dokumentacja hooków w `developer/API-DOCUMENTATION.md`

---

#### 4.2. Path Resolution Interceptors
**Cel**: Umożliwienie modyfikacji ścieżek translacji - **PRIORYTET WYSOKI**

**Nowe hooki**:

```php
// Path resolution hooks (w TranslationPathExecutor::resolve_path)
do_action('polytrans_before_resolve_path', $source_lang, $target_lang, $path_rules, $settings);
$path = apply_filters('polytrans_resolve_translation_path', $path, $source_lang, $target_lang, $path_rules, $settings);
do_action('polytrans_after_resolve_path', $path, $source_lang, $target_lang);

// Path step execution hooks (w TranslationPathExecutor::execute_step)
do_action('polytrans_before_path_step', $content, $source_lang, $target_lang, $assistant_id, $step_index, $path);
$content = apply_filters('polytrans_pre_path_step', $content, $source_lang, $target_lang, $assistant_id, $step_index);
$step_result = apply_filters('polytrans_path_step_result', $step_result, $content, $source_lang, $target_lang, $assistant_id, $step_index);
do_action('polytrans_after_path_step', $step_result, $content, $source_lang, $target_lang, $assistant_id, $step_index);

// Path rule modification hooks
$path_rules = apply_filters('polytrans_path_rules', $path_rules, $provider_id, $settings);
$assistants_mapping = apply_filters('polytrans_assistants_mapping', $assistants_mapping, $provider_id, $settings);
```

**Implementacja**:
- [ ] Dodaj hooki w `TranslationPathExecutor::resolve_path()`
- [ ] Dodaj hooki w `TranslationPathExecutor::execute()` (przed/po każdym kroku)
- [ ] Dodaj hooki w `OpenAIProvider::resolve_translation_path()`
- [ ] Dodaj hooki w `GoogleProvider::translate()` (jeśli używa paths)
- [ ] Dodaj filter dla path_rules w `TranslationPathExecutor::execute()`
- [ ] Dokumentacja w `developer/API-DOCUMENTATION.md`

**Przykłady użycia**:
```php
// Przechwyć i zmodyfikuj path przed wykonaniem
add_filter('polytrans_resolve_translation_path', function($path, $source_lang, $target_lang, $path_rules, $settings) {
    // Wymuś bezpośrednią translację zamiast multi-step dla określonych języków
    if ($source_lang === 'en' && $target_lang === 'pl' && count($path) > 2) {
        return [$source_lang, $target_lang];
    }
    return $path;
}, 10, 4);

// Dodaj custom processing po każdym kroku path
add_action('polytrans_after_path_step', function($step_result, $content, $source_lang, $target_lang, $assistant_id, $step_index) {
    // Wykonaj custom processing po każdym kroku
    if ($step_index === 0 && $step_result['success']) {
        // Custom logic after first step - np. walidacja, transformacja
        $step_result['translated_content']['content'] = my_custom_transform($step_result['translated_content']['content']);
    }
    return $step_result;
}, 10, 6);

// Modyfikuj path rules dynamicznie
add_filter('polytrans_path_rules', function($path_rules, $provider_id, $settings) {
    // Dodaj custom rule na podstawie warunków
    if ($provider_id === 'openai' && some_condition()) {
        $path_rules[] = [
            'source' => 'custom',
            'target' => 'custom',
            'intermediate' => 'en',
        ];
    }
    return $path_rules;
}, 10, 3);

// Przechwyć i zastąp wykonanie kroku
add_filter('polytrans_path_step_result', function($step_result, $content, $source_lang, $target_lang, $assistant_id, $step_index) {
    // Jeśli custom handler zwróci wynik, użyj go zamiast domyślnego
    $custom_result = apply_filters('polytrans_custom_path_step_handler', null, $content, $source_lang, $target_lang, $assistant_id, $step_index);
    if ($custom_result !== null) {
        return $custom_result;
    }
    return $step_result;
}, 5, 6); // Priority 5 = runs before default
```

---

#### 4.3. Workflow Execution Hooks
**Cel**: Rozszerzalność workflow execution

**Nowe hooki**:

```php
// Workflow execution hooks
do_action('polytrans_before_workflow_execution', $workflow, $context);
$context = apply_filters('polytrans_pre_workflow_execution', $context, $workflow);
$result = apply_filters('polytrans_workflow_execution_result', $result, $workflow, $context);
do_action('polytrans_after_workflow_execution', $result, $workflow, $context);

// Step execution hooks
do_action('polytrans_before_workflow_step', $step, $context, $workflow);
$step_result = apply_filters('polytrans_workflow_step_result', $step_result, $step, $context);
do_action('polytrans_after_workflow_step', $step_result, $step, $context, $workflow);
```

**Implementacja**:
- [ ] Dodaj hooki w `WorkflowExecutor::execute()`
- [ ] Dodaj hooki w `WorkflowExecutor::execute_step()`
- [ ] Dokumentacja

---

#### 4.4. Output Processing Hooks
**Cel**: Rozszerzalność output processing

**Nowe hooki**:

```php
// Output action hooks
do_action('polytrans_before_output_action', $action, $step_results, $context);
$change_result = apply_filters('polytrans_output_action_result', $change_result, $action, $step_results, $context);
do_action('polytrans_after_output_action', $change_result, $action, $context);

// Change execution hooks
do_action('polytrans_before_execute_change', $change, $context);
$execute_result = apply_filters('polytrans_execute_change_result', $execute_result, $change, $context);
do_action('polytrans_after_execute_change', $execute_result, $change, $context);
```

**Implementacja**:
- [ ] Dodaj hooki w `WorkflowOutputProcessor::process_step_outputs()`
- [ ] Dodaj hooki w `WorkflowOutputProcessor::execute_change()`
- [ ] Dokumentacja

---

**Deliverables**: Kompletny system hooków dla modyfikacji zachowania

---

### Faza 5: Rozszerzalność Manifestów (1 tydzień)

#### 5.1. Provider Manifest Extensions
**Cel**: Umożliwienie dodawania handlerów do manifestów providerów - **PRIORYTET WYSOKI**

**Nowy system**:

```php
// W SettingsProviderInterface::get_provider_manifest()
public function get_provider_manifest(array $settings) {
    $manifest = [
        'provider_id' => 'openai',
        'capabilities' => ['assistants', 'chat'],
        'handlers' => [
            'validate_key' => [$this, 'ajax_validate_openai_key'],
            'load_assistants' => [$this, 'ajax_load_openai_assistants'],
            'load_models' => [$this, 'ajax_get_openai_models'],
        ],
        // ... standard manifest
    ];
    
    // Allow extensions - można dodać custom handlers
    $manifest = apply_filters('polytrans_provider_manifest', $manifest, $this->get_provider_id(), $settings);
    
    // Provider-specific filter
    $manifest = apply_filters("polytrans_provider_manifest_{$this->get_provider_id()}", $manifest, $settings);
    
    return $manifest;
}
```

**Hooki**:
```php
// Extend manifest - dodaj custom handlers
add_filter('polytrans_provider_manifest', function($manifest, $provider_id, $settings) {
    if ($provider_id === 'openai') {
        // Dodaj custom handler do istniejących
        $manifest['handlers']['custom_action'] = 'my_custom_handler_function';
        
        // Dodaj custom capabilities
        $manifest['capabilities'][] = 'custom_feature';
        
        // Dodaj custom endpoints
        $manifest['custom_endpoints'] = [
            'my_endpoint' => 'https://api.example.com/my-endpoint',
        ];
    }
    return $manifest;
}, 10, 3);

// Provider-specific extension
add_filter('polytrans_provider_manifest_openai', function($manifest, $settings) {
    // Tylko dla OpenAI
    $manifest['custom_config'] = [
        'feature_flag' => true,
    ];
    return $manifest;
}, 10, 2);
```

**Implementacja**:
- [ ] Dodaj filter w `SettingsProviderInterface::get_provider_manifest()`
- [ ] Dodaj provider-specific filter (`polytrans_provider_manifest_{provider_id}`)
- [ ] Zaktualizuj wszystkie implementacje (OpenAI, Google, Claude, Gemini)
- [ ] Zaktualizuj `EndpointHandlerRegistry` do użycia manifest handlers
- [ ] Dokumentacja z przykładami

---

#### 5.2. Endpoint Handler Registry
**Cel**: Rejestracja custom handlerów dla endpointów - **PRIORYTET WYSOKI**

**Nowa klasa**: `Core\Api\EndpointHandlerRegistry`

```php
namespace PolyTrans\Core\Api;

/**
 * Endpoint Handler Registry
 * Allows registering custom handlers for provider endpoints
 * Enables intercepting and modifying endpoint behavior
 */
class EndpointHandlerRegistry {
    private static array $handlers = [];
    
    /**
     * Register a handler for an endpoint
     * 
     * @param string $endpoint Endpoint name (e.g., 'polytrans_validate_provider_key')
     * @param callable $handler Handler function
     * @param int $priority Priority (higher = runs first)
     * @param string|null $provider_id Optional: provider-specific handler
     */
    public static function register(string $endpoint, callable $handler, int $priority = 10, ?string $provider_id = null): void {
        $key = $provider_id ? "{$endpoint}_{$provider_id}" : $endpoint;
        if (!isset(self::$handlers[$key])) {
            self::$handlers[$key] = [];
        }
        if (!isset(self::$handlers[$key][$priority])) {
            self::$handlers[$key][$priority] = [];
        }
        self::$handlers[$key][$priority][] = $handler;
    }
    
    /**
     * Execute handlers for an endpoint
     * Returns first non-null result, or null if no handler handled it
     * 
     * @param string $endpoint Endpoint name
     * @param array $args Arguments
     * @param string|null $provider_id Optional: provider ID
     * @return mixed Handler result or null
     */
    public static function execute(string $endpoint, array $args = [], ?string $provider_id = null): mixed {
        // Try provider-specific first
        if ($provider_id) {
            $key = "{$endpoint}_{$provider_id}";
            $result = self::execute_handlers($key, $args);
            if ($result !== null) {
                return $result;
            }
        }
        
        // Fallback to generic
        return self::execute_handlers($endpoint, $args);
    }
    
    private static function execute_handlers(string $key, array $args): mixed {
        if (!isset(self::$handlers[$key])) {
            return null;
        }
        
        krsort(self::$handlers[$key]); // Higher priority first
        
        foreach (self::$handlers[$key] as $priority => $handlers) {
            foreach ($handlers as $handler) {
                $result = $handler($args);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if endpoint has handlers
     */
    public static function has_handlers(string $endpoint, ?string $provider_id = null): bool {
        $key = $provider_id ? "{$endpoint}_{$provider_id}" : $endpoint;
        return isset(self::$handlers[$key]) && !empty(self::$handlers[$key]);
    }
}
```

**Użycie**:
```php
// Zarejestruj custom handler dla wszystkich providerów
EndpointHandlerRegistry::register('polytrans_validate_provider_key', function($args) {
    // Custom validation logic
    if ($args['provider'] === 'my_provider') {
        return ['valid' => my_custom_validation($args['api_key'])];
    }
    return null; // Let default handler run
}, 20); // Higher priority = runs first

// Provider-specific handler (runs before generic)
EndpointHandlerRegistry::register('polytrans_validate_provider_key', function($args) {
    // Only for OpenAI
    return ['valid' => validate_openai_key_custom($args['api_key'])];
}, 30, 'openai'); // Provider-specific, higher priority

// W AJAX handler
public function ajax_validate_key() {
    // Check if custom handler exists
    $custom_result = EndpointHandlerRegistry::execute('polytrans_validate_provider_key', [
        'provider' => 'openai',
        'api_key' => $_POST['api_key']
    ], 'openai');
    
    if ($custom_result !== null) {
        wp_send_json_success($custom_result);
        return;
    }
    
    // Default handler
    $is_valid = $this->validate_api_key($_POST['api_key']);
    wp_send_json_success(['valid' => $is_valid]);
}
```

**Implementacja**:
- [ ] Utwórz `Core\Api\EndpointHandlerRegistry`
- [ ] Zintegruj z `BaseAjaxHandler` (sprawdź registry przed wykonaniem)
- [ ] Zaktualizuj AJAX handlers do użycia registry
- [ ] Dodaj hooki do rejestracji przez manifest
- [ ] Dokumentacja z przykładami

---

#### 5.3. Path Rule Extensions & Custom Path Handlers
**Cel**: Rozszerzalność path rules i możliwość dodania custom path handlers - **PRIORYTET WYSOKI**

**Hooki**:
```php
// Path rule resolution (w TranslationPathExecutor::execute)
$path_rules = apply_filters('polytrans_path_rules', $path_rules, $provider_id, $settings);

// Assistants mapping extension
$assistants_mapping = apply_filters('polytrans_assistants_mapping', $assistants_mapping, $provider_id, $settings);

// Custom path handler registration
$custom_handlers = apply_filters('polytrans_path_handlers', [], $provider_id, $settings);
```

**Custom Path Handler Interface**:
```php
namespace PolyTrans\Core\Path;

interface PathHandlerInterface {
    /**
     * Check if this handler can handle the path step
     */
    public function can_handle(string $source_lang, string $target_lang, array $settings): bool;
    
    /**
     * Execute the path step
     */
    public function execute(array $content, string $source_lang, string $target_lang, array $settings): array;
}

// Rejestracja custom handlera
add_filter('polytrans_path_handlers', function($handlers, $provider_id, $settings) {
    $handlers[] = new MyCustomPathHandler();
    return $handlers;
}, 10, 3);
```

**Przykład Custom Path Handler**:
```php
class CustomTranslationPathHandler implements PathHandlerInterface {
    public function can_handle(string $source_lang, string $target_lang, array $settings): bool {
        // Handle only specific language pairs
        return $source_lang === 'custom' && $target_lang === 'custom';
    }
    
    public function execute(array $content, string $source_lang, string $target_lang, array $settings): array {
        // Custom translation logic
        // Może używać innego API, cache, etc.
        return [
            'success' => true,
            'translated_content' => $translated,
            'error' => null
        ];
    }
}
```

**Implementacja**:
- [ ] Utwórz `Core\Path\PathHandlerInterface`
- [ ] Dodaj filter `polytrans_path_rules` w `TranslationPathExecutor::execute()`
- [ ] Dodaj filter `polytrans_assistants_mapping` w `TranslationPathExecutor::execute()`
- [ ] Dodaj filter `polytrans_path_handlers` w `TranslationPathExecutor::execute()`
- [ ] Zaktualizuj `TranslationPathExecutor` do sprawdzania custom handlers przed domyślnymi
- [ ] Dokumentacja z przykładami

---

**Deliverables**: System rozszerzalności manifestów i endpointów

---

### Faza 6: Integracja i Testy (1 tydzień)

#### 6.1. Testy Utility Classes
- [ ] Testy dla `HttpClient` i `HttpResponse`
- [ ] Testy dla `BaseAjaxHandler`
- [ ] Testy dla `ProcessSpawner`, `TaskProcessor`, `TokenManager`
- [ ] Testy dla `PostUpdateAction` i wszystkich actions

#### 6.2. Testy Hooków
- [ ] Testy dla translation flow hooks
- [ ] Testy dla path resolution hooks
- [ ] Testy dla workflow execution hooks
- [ ] Testy dla output processing hooks

#### 6.3. Testy Rozszerzalności
- [ ] Testy dla manifest extensions
- [ ] Testy dla endpoint handler registry
- [ ] Testy dla path rule extensions

#### 6.4. Integration Tests
- [ ] End-to-end testy z hookami
- [ ] Testy z custom handlers
- [ ] Testy backward compatibility

---

### Faza 7: Dokumentacja i Release (3 dni)

#### 7.1. Aktualizacja Dokumentacji
- [ ] Zaktualizuj `developer/API-DOCUMENTATION.md` z hookami
- [ ] Utwórz `developer/HOOKS_REFERENCE.md` (kompletna lista hooków)
- [ ] Zaktualizuj `developer/PROVIDER_EXTENSIBILITY_GUIDE.md` z nowymi możliwościami
- [ ] Zaktualizuj `developer/ARCHITECTURE.md` z nowymi klasami
- [ ] Utwórz przykłady użycia hooków

#### 7.2. Changelog i Release
- [ ] Zaktualizuj `CHANGELOG.md` z wszystkimi zmianami
- [ ] Zaktualizuj wersję w `polytrans.php` (1.6.14 → 1.7.0)
- [ ] Tag release: `v1.7.0`
- [ ] Release notes

---

## 📊 Podsumowanie Zmian

### Nowe Klasy i Utility
- `Core\Http\HttpClient` + `HttpResponse`
- `Core\Ajax\BaseAjaxHandler`
- `Core\Background\ProcessSpawner`, `TaskProcessor`, `TokenManager`
- `PostProcessing\Output\Actions\*` (9 action classes)
- `Core\Api\EndpointHandlerRegistry`
- `Core\Plugin` (zamiast `PolyTrans`)

### Nowe Hooki
- **Translation Flow**: 8 hooków
- **Path Resolution**: 6 hooków
- **Workflow Execution**: 6 hooków
- **Output Processing**: 4 hooków
- **Manifest Extensions**: 2 hooki
- **Path Rules**: 1 hook

**Razem**: ~27 nowych hooków

### Redukcja Kodu
- **Duplikacje**: ~1,250 linii → ~700 linii utilities (44% redukcja)
- **BackgroundProcessor**: 995 → ~50 linii (-95%)
- **WorkflowOutputProcessor**: 1,105 → ~400 linii (-64%)
- **AJAX handlers**: ~200 linii duplikacji → ~100 linii utility (-50%)

### Migracja PSR-4
- Wszystkie klasy w namespace `PolyTrans\`
- Usunięcie `LegacyAutoloader`
- Aktualizacja wszystkich referencji

---

## ✅ Checklist Wersji 1.7.0

### Dokumentacja
- [ ] Archiwizacja Phase 0
- [ ] Konsolidacja duplikatów
- [ ] Reorganizacja struktury
- [ ] Aktualizacja indeksów

### Utility Classes
- [ ] HttpClient + HttpResponse
- [ ] BaseAjaxHandler
- [ ] Background utilities
- [ ] Post Update Actions

### PSR-4 Migration
- [ ] Analiza referencji
- [ ] Migracja klas
- [ ] Aktualizacja referencji
- [ ] Usunięcie legacy code

### Extensibility System
- [ ] Translation flow hooks
- [ ] Path resolution hooks
- [ ] Workflow execution hooks
- [ ] Output processing hooks
- [ ] Manifest extensions
- [ ] Endpoint handler registry
- [ ] Path rule extensions

### Testy
- [ ] Testy utilities
- [ ] Testy hooków
- [ ] Testy rozszerzalności
- [ ] Integration tests

### Dokumentacja i Release
- [ ] Aktualizacja dokumentacji
- [ ] Changelog
- [ ] Version bump
- [ ] Release

---

## 📅 Harmonogram

| Faza | Czas | Status |
|------|------|--------|
| Faza 1: Dokumentacja | 3-4 dni | 📋 |
| Faza 2: Utility Classes | 1.5 tygodnia | 📋 |
| Faza 3: PSR-4 Migration | 1 tydzień | 📋 |
| Faza 4: Extensibility Hooks | 2 tygodnie | 📋 |
| Faza 5: Manifest Extensions | 3-4 dni (część Fazy 4) | 📋 |
| Faza 6: Testy | 1 tydzień | 📋 |
| Faza 7: Dokumentacja & Release | 3 dni | 📋 |
| **RAZEM** | **~6-7 tygodni** | |

---

## 🎯 Metryki Sukcesu

### Przed 1.7.0
- Duplikacje: ~1,250 linii
- `PolyTrans_` referencje: **377**
- Hooki: ~10
- Utility classes: 0
- Możliwość modyfikacji pathingów: ❌
- Możliwość dodania custom handlers: ❌

### Po 1.7.0
- Duplikacje: ~700 linii (44% redukcja)
- `PolyTrans_` referencje: **0** (tylko aliases w Compatibility.php dla BC)
- Hooki: ~37 (27 nowych)
- Utility classes: 15+
- Możliwość modyfikacji pathingów: ✅ (hooki + custom handlers)
- Możliwość dodania custom handlers: ✅ (EndpointHandlerRegistry + manifest extensions)

---

**Ostatnia aktualizacja**: 2025-12-16

