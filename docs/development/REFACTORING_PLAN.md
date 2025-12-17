# Plan Refaktoryzacji Dużych Klas

**Data utworzenia**: 2025-12-16  
**Wersja**: 1.0  
**Cel**: Zmniejszenie klas z 700-1100 linii do ~200 linii każda

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

## 1. OpenAISettingsProvider (1,127 linii) 🔴 **PRIORYTET 1**

### Obecna Struktura

**Główne odpowiedzialności**:
1. Renderowanie UI ustawień (render_settings_ui)
2. Walidacja ustawień (validate_settings)
3. Zarządzanie asystentami (render_assistant_mapping_table, ajax_load_openai_assistants)
4. Zarządzanie modelami (get_grouped_models, ajax_get_openai_models, fetch_models_from_api)
5. Zarządzanie path rules (render_path_rules_table)
6. Walidacja API key (ajax_validate_openai_key, validate_api_key)
7. AJAX handlers (register_ajax_handlers)
8. Provider manifest (get_provider_manifest)

**Metody** (34 metody):
- Interface methods: `get_provider_id()`, `get_tab_label()`, `get_tab_description()`, `get_settings_keys()`, `get_required_js_files()`, `get_required_css_files()`
- UI Rendering: `render_settings_ui()`, `render_assistant_mapping_table()`, `render_model_selection()`, `render_path_rules_table()`
- Validation: `validate_settings()`, `validate_openai_api_key()`, `validate_api_key()`
- API Integration: `fetch_models_from_api()`, `load_assistants()`, `load_models()`
- AJAX Handlers: `ajax_validate_openai_key()`, `ajax_load_openai_assistants()`, `ajax_get_openai_models()`, `ajax_get_providers_config()`
- Helpers: `get_language_pairs()`, `get_language_name()`, `get_model_group()`, `get_model_label()`, `get_fallback_models()`, `get_grouped_models()`, `get_all_available_models()`

### Plan Refaktoryzacji

#### Krok 1: Wyodrębnij AssistantManager
**Nowa klasa**: `OpenAI\Settings\AssistantManager`

```php
namespace PolyTrans\Providers\OpenAI\Settings;

class AssistantManager {
    public function render_mapping_table($languages, $language_names, $assistants, $source_language): string;
    public function load_assistants(array $settings): array;
    public function ajax_load_assistants(): void;
    private function fetch_assistants_from_api(string $api_key): array;
}
```

**Metody do przeniesienia**:
- `render_assistant_mapping_table()` (~80 linii)
- `ajax_load_openai_assistants()` (~160 linii)
- `load_assistants()` (~50 linii)
- Logika związana z asystentami

**Szacowany rozmiar**: ~300 linii

---

#### Krok 2: Wyodrębnij ModelManager
**Nowa klasa**: `OpenAI\Settings\ModelManager`

```php
namespace PolyTrans\Providers\OpenAI\Settings;

class ModelManager {
    public function render_model_selection($selected_model): string;
    public function get_grouped_models($selected_model = null): array;
    public function load_models(array $settings): array;
    public function ajax_get_models(): void;
    private function fetch_models_from_api(string $api_key): array;
    private function get_model_group(string $model_id): string;
    private function get_model_label(string $model_id): string;
    private function get_fallback_models(): array;
    private function get_all_available_models(): array;
}
```

**Metody do przeniesienia**:
- `render_model_selection()` (~40 linii)
- `get_grouped_models()` (~30 linii)
- `fetch_models_from_api()` (~80 linii)
- `get_model_group()`, `get_model_label()`, `get_fallback_models()`, `get_all_available_models()` (~150 linii)
- `ajax_get_openai_models()` (~40 linii)
- `load_models()` (~25 linii)

**Szacowany rozmiar**: ~365 linii

---

#### Krok 3: Wyodrębnij PathRulesManager
**Nowa klasa**: `OpenAI\Settings\PathRulesManager`

```php
namespace PolyTrans\Providers\OpenAI\Settings;

class PathRulesManager {
    public function render_path_rules_table($path_rules, $languages, $language_names): string;
    public function validate_path_rules(array $rules): array;
    private function get_language_pairs(array $languages): array;
    private function get_language_name(string $lang_code, array $languages, array $language_names): string;
}
```

**Metody do przeniesienia**:
- `render_path_rules_table()` (~70 linii)
- Logika walidacji path rules z `validate_settings()` (~50 linii)
- `get_language_pairs()` (~20 linii)
- `get_language_name()` (~15 linii)

**Szacowany rozmiar**: ~155 linii

---

#### Krok 4: Wyodrębnij ApiKeyValidator
**Nowa klasa**: `OpenAI\Settings\ApiKeyValidator`

```php
namespace PolyTrans\Providers\OpenAI\Settings;

class ApiKeyValidator {
    public function validate_api_key(string $api_key): bool;
    public function ajax_validate_key(): void;
    private function validate_openai_api_key(string $api_key): bool;
}
```

**Metody do przeniesienia**:
- `validate_api_key()` (~10 linii)
- `ajax_validate_openai_key()` (~35 linii)
- `validate_openai_api_key()` (~10 linii)

**Szacowany rozmiar**: ~55 linii

---

#### Krok 5: Wyodrębnij SettingsRenderer
**Nowa klasa**: `OpenAI\Settings\SettingsRenderer`

```php
namespace PolyTrans\Providers\OpenAI\Settings;

class SettingsRenderer {
    public function render(array $settings, array $languages, array $language_names): string;
    private function render_api_key_field(string $api_key): string;
    private function render_source_language_field(string $source_lang, array $languages): string;
}
```

**Metody do przeniesienia**:
- Główna logika renderowania z `render_settings_ui()` (~80 linii)
- Inline styles (~50 linii)

**Szacowany rozmiar**: ~130 linii

---

#### Krok 6: Refaktoryzuj główną klasę
**Nowa struktura**: `OpenAISettingsProvider`

```php
namespace PolyTrans\Providers\OpenAI;

use PolyTrans\Providers\OpenAI\Settings\AssistantManager;
use PolyTrans\Providers\OpenAI\Settings\ModelManager;
use PolyTrans\Providers\OpenAI\Settings\PathRulesManager;
use PolyTrans\Providers\OpenAI\Settings\ApiKeyValidator;
use PolyTrans\Providers\OpenAI\Settings\SettingsRenderer;

class OpenAISettingsProvider implements SettingsProviderInterface {
    private AssistantManager $assistant_manager;
    private ModelManager $model_manager;
    private PathRulesManager $path_rules_manager;
    private ApiKeyValidator $api_key_validator;
    private SettingsRenderer $renderer;
    
    public function __construct() {
        $this->assistant_manager = new AssistantManager();
        $this->model_manager = new ModelManager();
        $this->path_rules_manager = new PathRulesManager();
        $this->api_key_validator = new ApiKeyValidator();
        $this->renderer = new SettingsRenderer(
            $this->assistant_manager,
            $this->model_manager,
            $this->path_rules_manager
        );
    }
    
    // Interface methods (delegacja)
    public function render_settings_ui(...) {
        return $this->renderer->render(...);
    }
    
    public function validate_settings(...) {
        // Orchestracja walidacji
    }
    
    public function get_ajax_handlers() {
        return [
            'polytrans_validate_openai_key' => [$this->api_key_validator, 'ajax_validate_key'],
            'polytrans_load_openai_assistants' => [$this->assistant_manager, 'ajax_load_assistants'],
            'polytrans_get_openai_models' => [$this->model_manager, 'ajax_get_models'],
            // ...
        ];
    }
}
```

**Szacowany rozmiar**: ~200 linii

---

### Podsumowanie Refaktoryzacji OpenAISettingsProvider

| Klasa | Szacowany rozmiar | Odpowiedzialność |
|-------|------------------|------------------|
| `OpenAISettingsProvider` | ~200 linii | Orchestracja, interface implementation |
| `AssistantManager` | ~300 linii | Zarządzanie asystentami |
| `ModelManager` | ~365 linii | Zarządzanie modelami |
| `PathRulesManager` | ~155 linii | Zarządzanie path rules |
| `ApiKeyValidator` | ~55 linii | Walidacja API key |
| `SettingsRenderer` | ~130 linii | Renderowanie UI |
| **RAZEM** | **~1,205 linii** | (podzielone na 6 klas) |

**Redukcja**: Z 1,127 linii w jednej klasie → ~200 linii w głównej klasie + 5 pomocniczych klas

---

## 2. WorkflowOutputProcessor (1,105 linii) 🔴 **PRIORYTET 2**

### Obecna Struktura

**Główne odpowiedzialności**:
1. Przetwarzanie output actions (`process_step_outputs`)
2. Tworzenie change objects (`create_change_object`, `enhance_change_object_for_display`)
3. Wykonywanie zmian (`execute_change`, `apply_change_to_context`)
4. Aktualizacja postów (update_post_title, update_post_content, update_post_excerpt, update_post_meta, etc.)
5. Parsowanie wartości (parse_post_status, parse_post_date)
6. Zarządzanie kontekstem (`ensure_context_has_post_data`, `refresh_context_from_database`)
7. User attribution

**Metody** (24 metody):
- Main: `process_step_outputs()`
- Action processing: `process_single_action()`, `get_variable_value()`, `auto_detect_response_value()`
- Post updates: `update_post_title()`, `update_post_content()`, `update_post_excerpt()`, `update_post_meta()`, `append_to_post_content()`, `prepend_to_post_content()`, `update_post_status()`, `update_post_date()`
- Change objects: `create_change_object()`, `enhance_change_object_for_display()`, `apply_change_to_context()`, `execute_change()`
- Parsing: `parse_post_status()`, `parse_post_date()`
- Context: `ensure_context_has_post_data()`, `refresh_context_from_database()`
- Helpers: `get_valid_post_statuses()`

### Plan Refaktoryzacji

#### Krok 1: Wyodrębnij ActionExecutors
**Nowa klasa**: `PostProcessing\Output\ActionExecutors\PostTitleUpdater`

```php
namespace PolyTrans\PostProcessing\Output\ActionExecutors;

class PostTitleUpdater {
    public function update(string $value, array $context): array;
}
```

**Podobnie dla**:
- `PostContentUpdater`
- `PostExcerptUpdater`
- `PostMetaUpdater`
- `PostStatusUpdater`
- `PostDateUpdater`
- `PostContentAppender`
- `PostContentPrepend`
- `OptionSaver`

**Szacowany rozmiar**: ~50-80 linii każda (9 klas × ~65 linii = ~585 linii)

---

#### Krok 2: Wyodrębnij ChangeObjectFactory
**Nowa klasa**: `PostProcessing\Output\ChangeObjectFactory`

```php
namespace PolyTrans\PostProcessing\Output;

class ChangeObjectFactory {
    public function create(array $step_results, array $action, array $context): array;
    public function enhance_for_display(array $change, array $context): array;
    private function get_variable_value(array $step_results, string $variable_path);
    private function auto_detect_response_value(array $data);
}
```

**Metody do przeniesienia**:
- `create_change_object()` (~45 linii)
- `enhance_change_object_for_display()` (~80 linii)
- `get_variable_value()` (~30 linii)
- `auto_detect_response_value()` (~40 linii)

**Szacowany rozmiar**: ~195 linii

---

#### Krok 3: Wyodrębnij ValueParsers
**Nowa klasa**: `PostProcessing\Output\Parsers\ValueParser`

```php
namespace PolyTrans\PostProcessing\Output\Parsers;

class ValueParser {
    public function parse_post_status(string $value): string;
    public function parse_post_date(string $value): ?string;
    public function get_valid_post_statuses(): array;
}
```

**Metody do przeniesienia**:
- `parse_post_status()` (~50 linii)
- `parse_post_date()` (~50 linii)
- `get_valid_post_statuses()` (~15 linii)

**Szacowany rozmiar**: ~115 linii

---

#### Krok 4: Wyodrębnij ContextManager
**Nowa klasa**: `PostProcessing\Output\ContextManager`

```php
namespace PolyTrans\PostProcessing\Output;

class ContextManager {
    public function ensure_has_post_data(array $context): array;
    public function refresh_from_database(array $context): array;
    public function apply_change(array $context, array $change): array;
}
```

**Metody do przeniesienia**:
- `ensure_context_has_post_data()` (~35 linii)
- `refresh_context_from_database()` (~70 linii)
- `apply_change_to_context()` (~80 linii)

**Szacowany rozmiar**: ~185 linii

---

#### Krok 5: Wyodrębnij UserAttributionManager
**Nowa klasa**: `PostProcessing\Output\UserAttributionManager`

```php
namespace PolyTrans\PostProcessing\Output;

class UserAttributionManager {
    public function set_attribution_user(?int $user_id, array $workflow): ?int;
    public function restore_original_user(int $original_user_id, ?int $attribution_user_id): void;
}
```

**Metody do przeniesienia**:
- Logika user attribution z `process_step_outputs()` (~30 linii)

**Szacowany rozmiar**: ~50 linii

---

#### Krok 6: Refaktoryzuj główną klasę
**Nowa struktura**: `WorkflowOutputProcessor`

```php
namespace PolyTrans\PostProcessing;

use PolyTrans\PostProcessing\Output\ChangeObjectFactory;
use PolyTrans\PostProcessing\Output\ContextManager;
use PolyTrans\PostProcessing\Output\UserAttributionManager;
use PolyTrans\PostProcessing\Output\ActionExecutors\PostTitleUpdater;
// ... inne executors

class WorkflowOutputProcessor {
    private ChangeObjectFactory $change_factory;
    private ContextManager $context_manager;
    private UserAttributionManager $attribution_manager;
    private array $executors = [];
    
    public function __construct() {
        $this->change_factory = new ChangeObjectFactory();
        $this->context_manager = new ContextManager();
        $this->attribution_manager = new UserAttributionManager();
        $this->init_executors();
    }
    
    private function init_executors(): void {
        $this->executors = [
            'update_post_title' => new PostTitleUpdater(),
            'update_post_content' => new PostContentUpdater(),
            // ...
        ];
    }
    
    public function process_step_outputs(...): array {
        // Orchestracja
    }
    
    private function execute_change(array $change, array $context): array {
        $executor = $this->executors[$change['type']] ?? null;
        if (!$executor) {
            return ['success' => false, 'error' => 'Unknown action type'];
        }
        return $executor->execute($change, $context);
    }
}
```

**Szacowany rozmiar**: ~200 linii

---

### Podsumowanie Refaktoryzacji WorkflowOutputProcessor

| Klasa | Szacowany rozmiar | Odpowiedzialność |
|-------|------------------|------------------|
| `WorkflowOutputProcessor` | ~200 linii | Orchestracja |
| `ChangeObjectFactory` | ~195 linii | Tworzenie change objects |
| `ContextManager` | ~185 linii | Zarządzanie kontekstem |
| `ValueParser` | ~115 linii | Parsowanie wartości |
| `UserAttributionManager` | ~50 linii | User attribution |
| `PostTitleUpdater` | ~65 linii | Update title |
| `PostContentUpdater` | ~65 linii | Update content |
| `PostExcerptUpdater` | ~65 linii | Update excerpt |
| `PostMetaUpdater` | ~65 linii | Update meta |
| `PostStatusUpdater` | ~65 linii | Update status |
| `PostDateUpdater` | ~65 linii | Update date |
| `PostContentAppender` | ~65 linii | Append content |
| `PostContentPrepend` | ~65 linii | Prepend content |
| `OptionSaver` | ~65 linii | Save option |
| **RAZEM** | **~1,265 linii** | (podzielone na 14 klas) |

**Redukcja**: Z 1,105 linii w jednej klasie → ~200 linii w głównej klasie + 13 pomocniczych klas

---

## 3. LogsManager (1,095 linii) 🔴 **PRIORYTET 3**

### Obecna Struktura

**Główne odpowiedzialności**:
1. Tworzenie tabeli bazy danych (`create_logs_table`, `check_and_adapt_table_structure`)
2. Zapisywanie logów (`log`, `log_to_database`)
3. Pobieranie logów (`get_logs`, `ajax_refresh_logs`)
4. Filtrowanie i paginacja
5. Renderowanie UI (admin_logs_page - już zmigrowane do Twig)

### Plan Refaktoryzacji

#### Krok 1: Wyodrębnij DatabaseSchemaManager
**Nowa klasa**: `Core\Logs\DatabaseSchemaManager`

```php
namespace PolyTrans\Core\Logs;

class DatabaseSchemaManager {
    public function create_table(): bool;
    public function check_and_adapt_structure(string $table_name): void;
    private function get_table_column_details(string $table_name): array;
    private function migrate_table_structure(string $table_name, array $existing_columns): void;
}
```

**Metody do przeniesienia**:
- `create_logs_table()` (~50 linii)
- `check_and_adapt_table_structure()` (~200 linii)
- Helpery związane ze schematem

**Szacowany rozmiar**: ~300 linii

---

#### Krok 2: Wyodrębnij LogWriter
**Nowa klasa**: `Core\Logs\LogWriter`

```php
namespace PolyTrans\Core\Logs;

class LogWriter {
    public function write(string $message, string $level, array $context = []): void;
    private function log_to_database(string $message, string $level, array $context): void;
    private function log_to_error_log(string $message, string $level, array $context): void;
    private function is_db_logging_enabled(): bool;
}
```

**Metody do przeniesienia**:
- `log()` (~100 linii)
- `log_to_database()` (~150 linii)
- `is_db_logging_enabled()` (~20 linii)

**Szacowany rozmiar**: ~270 linii

---

#### Krok 3: Wyodrębnij LogReader
**Nowa klasa**: `Core\Logs\LogReader`

```php
namespace PolyTrans\Core\Logs;

class LogReader {
    public function get_logs(array $filters = [], int $page = 1, int $per_page = 50): array;
    public function get_log_count(array $filters = []): int;
    private function build_query(array $filters): array;
}
```

**Metody do przeniesienia**:
- `get_logs()` (~200 linii)
- Logika budowania zapytań SQL

**Szacowany rozmiar**: ~250 linii

---

#### Krok 4: Wyodrębnij LogFilters
**Nowa klasa**: `Core\Logs\LogFilters`

```php
namespace PolyTrans\Core\Logs;

class LogFilters {
    public function apply_filters(array $logs, array $filters): array;
    public function filter_by_level(array $logs, string $level): array;
    public function filter_by_source(array $logs, string $source): array;
    public function filter_by_date_range(array $logs, string $start_date, string $end_date): array;
}
```

**Metody do przeniesienia**:
- Logika filtrowania z `get_logs()` (~100 linii)

**Szacowany rozmiar**: ~120 linii

---

#### Krok 5: Refaktoryzuj główną klasę
**Nowa struktura**: `LogsManager`

```php
namespace PolyTrans\Core;

use PolyTrans\Core\Logs\DatabaseSchemaManager;
use PolyTrans\Core\Logs\LogWriter;
use PolyTrans\Core\Logs\LogReader;
use PolyTrans\Core\Logs\LogFilters;

class LogsManager {
    private static DatabaseSchemaManager $schema_manager;
    private static LogWriter $writer;
    private static LogReader $reader;
    private static LogFilters $filters;
    
    public static function init(): void {
        self::$schema_manager = new DatabaseSchemaManager();
        self::$writer = new LogWriter();
        self::$reader = new LogReader();
        self::$filters = new LogFilters();
    }
    
    public static function create_logs_table(): bool {
        return self::$schema_manager->create_table();
    }
    
    public static function log(string $message, string $level = 'info', array $context = []): void {
        self::$writer->write($message, $level, $context);
    }
    
    public static function get_logs(array $filters = [], int $page = 1, int $per_page = 50): array {
        return self::$reader->get_logs($filters, $page, $per_page);
    }
    
    // AJAX handlers
    public static function ajax_refresh_logs(): void {
        // Delegacja do LogReader
    }
}
```

**Szacowany rozmiar**: ~150 linii

---

### Podsumowanie Refaktoryzacji LogsManager

| Klasa | Szacowany rozmiar | Odpowiedzialność |
|-------|------------------|------------------|
| `LogsManager` | ~150 linii | Facade, static interface |
| `DatabaseSchemaManager` | ~300 linii | Zarządzanie schematem DB |
| `LogWriter` | ~270 linii | Zapisywanie logów |
| `LogReader` | ~250 linii | Czytanie logów |
| `LogFilters` | ~120 linii | Filtrowanie logów |
| **RAZEM** | **~1,090 linii** | (podzielone na 5 klas) |

**Redukcja**: Z 1,095 linii w jednej klasie → ~150 linii w głównej klasie + 4 pomocnicze klasy

---

## 4. BackgroundProcessor (995 linii) 🔴 **PRIORYTET 5**

### Obecna Struktura

**Główne odpowiedzialności**:
1. Spawning procesów (`spawn`, `spawn_exec`, `spawn_http_request`)
2. Wykonywanie zadań (`process_task`)
3. Zarządzanie tokenami i transients
4. Logowanie

### Plan Refaktoryzacji

#### Krok 1: Wyodrębnij ProcessSpawner
**Nowa klasa**: `Core\Background\ProcessSpawner`

```php
namespace PolyTrans\Core\Background;

class ProcessSpawner {
    public function spawn(array $args, string $action): bool;
    private function spawn_exec(array $args, string $action): bool;
    private function spawn_http_request(array $args, string $action): bool;
    private function is_exec_available(): bool;
}
```

**Metody do przeniesienia**:
- `spawn()` (~30 linii)
- `spawn_exec()` (~100 linii)
- `spawn_http_request()` (~80 linii)
- `is_exec_available()` (~25 linii)

**Szacowany rozmiar**: ~235 linii

---

#### Krok 2: Wyodrębnij TaskProcessor
**Nowa klasa**: `Core\Background\TaskProcessor`

```php
namespace PolyTrans\Core\Background;

class TaskProcessor {
    public function process(array $args, string $action): void;
    private function process_translation_task(array $args): void;
    private function process_workflow_test(array $args): void;
    private function process_workflow_execute(array $args): void;
}
```

**Metody do przeniesienia**:
- `process_task()` (~700 linii) - podzielić na mniejsze metody

**Szacowany rozmiar**: ~300 linii

---

#### Krok 3: Wyodrębnij TokenManager
**Nowa klasa**: `Core\Background\TokenManager`

```php
namespace PolyTrans\Core\Background;

class TokenManager {
    public function generate_token(): string;
    public function store_task_data(string $token, array $args, string $action): void;
    public function get_task_data(string $token): ?array;
    public function cleanup_token(string $token): void;
}
```

**Metody do przeniesienia**:
- Logika tokenów z `spawn()` i `process_task()` (~50 linii)

**Szacowany rozmiar**: ~80 linii

---

#### Krok 4: Refaktoryzuj główną klasę
**Nowa struktura**: `BackgroundProcessor`

```php
namespace PolyTrans\Core;

use PolyTrans\Core\Background\ProcessSpawner;
use PolyTrans\Core\Background\TaskProcessor;
use PolyTrans\Core\Background\TokenManager;

class BackgroundProcessor {
    private static ProcessSpawner $spawner;
    private static TaskProcessor $processor;
    private static TokenManager $token_manager;
    
    public static function init(): void {
        self::$spawner = new ProcessSpawner();
        self::$processor = new TaskProcessor();
        self::$token_manager = new TokenManager();
    }
    
    public static function spawn(array $args, string $action = 'process-translation'): bool {
        return self::$spawner->spawn($args, $action);
    }
    
    public static function process_task(array $args, string $action): void {
        self::$processor->process($args, $action);
    }
}
```

**Szacowany rozmiar**: ~50 linii

---

### Podsumowanie Refaktoryzacji BackgroundProcessor

| Klasa | Szacowany rozmiar | Odpowiedzialność |
|-------|------------------|------------------|
| `BackgroundProcessor` | ~50 linii | Facade, static interface |
| `ProcessSpawner` | ~235 linii | Spawning procesów |
| `TaskProcessor` | ~300 linii | Wykonywanie zadań |
| `TokenManager` | ~80 linii | Zarządzanie tokenami |
| **RAZEM** | **~665 linii** | (podzielone na 4 klasy) |

**Redukcja**: Z 995 linii w jednej klasie → ~50 linii w głównej klasie + 3 pomocnicze klasy

---

## 5. WorkflowManager (927 linii) 🔴 **PRIORYTET 6**

### Obecna Struktura

**Główne odpowiedzialności**:
1. Zarządzanie workflow (CRUD)
2. Triggerowanie workflow (`trigger_workflows`)
3. Wykonywanie workflow (`execute_workflow`)
4. Warunki workflow (`should_execute_workflow`, `evaluate_workflow_conditions`)
5. AJAX handlers
6. Zarządzanie data providers i steps

### Plan Refaktoryzacji

#### Krok 1: Wyodrębnij WorkflowConditionEvaluator
**Nowa klasa**: `PostProcessing\Workflow\ConditionEvaluator`

```php
namespace PolyTrans\PostProcessing\Workflow;

class ConditionEvaluator {
    public function should_execute(array $workflow, array $context): bool;
    public function evaluate_conditions(array $conditions, array $context): bool;
    private function evaluate_single_condition(array $condition, array $context): bool;
}
```

**Metody do przeniesienia**:
- `should_execute_workflow()` (~70 linii)
- `evaluate_workflow_conditions()` (~40 linii)

**Szacowany rozmiar**: ~150 linii

---

#### Krok 2: Wyodrębnij WorkflowScheduler
**Nowa klasa**: `PostProcessing\Workflow\WorkflowScheduler`

```php
namespace PolyTrans\PostProcessing\Workflow;

class WorkflowScheduler {
    public function schedule_execution(array $workflow, array $context): void;
    public function check_execution_status(string $execution_id): ?array;
}
```

**Metody do przeniesienia**:
- `schedule_workflow_execution()` (~15 linii)
- `ajax_check_execution_status()` (~50 linii)

**Szacowany rozmiar**: ~100 linii

---

#### Krok 3: Wyodrębnij WorkflowAjaxHandlers
**Nowa klasa**: `PostProcessing\Workflow\AjaxHandlers`

```php
namespace PolyTrans\PostProcessing\Workflow;

class AjaxHandlers {
    public function execute_workflow(): void;
    public function test_workflow(): void;
    public function execute_workflow_manual(): void;
    public function get_workflows_for_post(): void;
}
```

**Metody do przeniesienia**:
- `ajax_execute_workflow()` (~70 linii)
- `ajax_test_workflow()` (~100 linii)
- `ajax_execute_workflow_manual()` (~150 linii)
- `ajax_get_workflows_for_post()` (~30 linii)

**Szacowany rozmiar**: ~350 linii

---

#### Krok 4: Refaktoryzuj główną klasę
**Nowa struktura**: `WorkflowManager`

```php
namespace PolyTrans\PostProcessing;

use PolyTrans\PostProcessing\Workflow\ConditionEvaluator;
use PolyTrans\PostProcessing\Workflow\WorkflowScheduler;
use PolyTrans\PostProcessing\Workflow\AjaxHandlers;

class WorkflowManager {
    private WorkflowStorageManager $storage_manager;
    private WorkflowExecutor $executor;
    private VariableManager $variable_manager;
    private ConditionEvaluator $condition_evaluator;
    private WorkflowScheduler $scheduler;
    private AjaxHandlers $ajax_handlers;
    
    public function trigger_workflows(...): void {
        // Użyj condition_evaluator
    }
    
    public function execute_workflow(...): array {
        // Orchestracja
    }
}
```

**Szacowany rozmiar**: ~200 linii

---

### Podsumowanie Refaktoryzacji WorkflowManager

| Klasa | Szacowany rozmiar | Odpowiedzialność |
|-------|------------------|------------------|
| `WorkflowManager` | ~200 linii | Orchestracja |
| `ConditionEvaluator` | ~150 linii | Ewaluacja warunków |
| `WorkflowScheduler` | ~100 linii | Planowanie wykonania |
| `AjaxHandlers` | ~350 linii | AJAX handlers |
| **RAZEM** | **~800 linii** | (podzielone na 4 klasy) |

**Redukcja**: Z 927 linii w jednej klasie → ~200 linii w głównej klasie + 3 pomocnicze klasy

---

## 📋 Harmonogram Implementacji

### Faza 1: OpenAISettingsProvider (1 tydzień)
1. Dzień 1-2: Utwórz `AssistantManager` i `ModelManager`
2. Dzień 3: Utwórz `PathRulesManager` i `ApiKeyValidator`
3. Dzień 4: Utwórz `SettingsRenderer`
4. Dzień 5: Refaktoryzuj główną klasę i testy

### Faza 2: WorkflowOutputProcessor (1 tydzień)
1. Dzień 1-2: Utwórz `ActionExecutors` (9 klas)
2. Dzień 3: Utwórz `ChangeObjectFactory` i `ValueParser`
3. Dzień 4: Utwórz `ContextManager` i `UserAttributionManager`
4. Dzień 5: Refaktoryzuj główną klasę i testy

### Faza 3: LogsManager (3-4 dni)
1. Dzień 1: Utwórz `DatabaseSchemaManager`
2. Dzień 2: Utwórz `LogWriter` i `LogReader`
3. Dzień 3: Utwórz `LogFilters` i refaktoryzuj główną klasę
4. Dzień 4: Testy

### Faza 4: BackgroundProcessor (2-3 dni)
1. Dzień 1: Utwórz `ProcessSpawner` i `TaskProcessor`
2. Dzień 2: Utwórz `TokenManager` i refaktoryzuj główną klasę
3. Dzień 3: Testy

### Faza 5: WorkflowManager (3-4 dni)
1. Dzień 1: Utwórz `ConditionEvaluator`
2. Dzień 2: Utwórz `WorkflowScheduler` i `AjaxHandlers`
3. Dzień 3: Refaktoryzuj główną klasę
4. Dzień 4: Testy

---

## ✅ Checklist Refaktoryzacji

### OpenAISettingsProvider
- [ ] Utwórz `AssistantManager`
- [ ] Utwórz `ModelManager`
- [ ] Utwórz `PathRulesManager`
- [ ] Utwórz `ApiKeyValidator`
- [ ] Utwórz `SettingsRenderer`
- [ ] Refaktoryzuj główną klasę
- [ ] Zaktualizuj testy
- [ ] Zaktualizuj dokumentację

### WorkflowOutputProcessor
- [ ] Utwórz wszystkie `ActionExecutors` (9 klas)
- [ ] Utwórz `ChangeObjectFactory`
- [ ] Utwórz `ValueParser`
- [ ] Utwórz `ContextManager`
- [ ] Utwórz `UserAttributionManager`
- [ ] Refaktoryzuj główną klasę
- [ ] Zaktualizuj testy

### LogsManager
- [ ] Utwórz `DatabaseSchemaManager`
- [ ] Utwórz `LogWriter`
- [ ] Utwórz `LogReader`
- [ ] Utwórz `LogFilters`
- [ ] Refaktoryzuj główną klasę
- [ ] Zaktualizuj testy

### BackgroundProcessor
- [ ] Utwórz `ProcessSpawner`
- [ ] Utwórz `TaskProcessor`
- [ ] Utwórz `TokenManager`
- [ ] Refaktoryzuj główną klasę
- [ ] Zaktualizuj testy

### WorkflowManager
- [ ] Utwórz `ConditionEvaluator`
- [ ] Utwórz `WorkflowScheduler`
- [ ] Utwórz `AjaxHandlers`
- [ ] Refaktoryzuj główną klasę
- [ ] Zaktualizuj testy

---

## 🎯 Metryki Sukcesu

### Przed Refaktoryzacją
- Największa klasa: 1,127 linii
- Średnia wielkość klasy: ~500 linii
- Liczba klas > 500 linii: 7

### Po Refaktoryzacji
- Największa klasa: ~300 linii (max)
- Średnia wielkość klasy: ~200 linii
- Liczba klas > 500 linii: 0
- Liczba nowych klas: ~35

---

**Ostatnia aktualizacja**: 2025-12-16

