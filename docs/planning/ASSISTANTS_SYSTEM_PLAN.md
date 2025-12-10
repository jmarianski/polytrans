# Plan Implementacji: System Zarządzania Asystentami AI

## 1. Przegląd Wymagań Biznesowych

### 1.1 Cel Projektu
Stworzenie centralnego systemu zarządzania asystentami AI, który:
- Umożliwia definiowanie asystentów niezależnie od konkretnych workflow czy translacji
- Pozwala na reużycie asystentów w różnych kontekstach (workflow + translacje)
- Przygotowuje architekturę pod przyszłe wsparcie dla Claude i Gemini
- Zachowuje backward compatibility z istniejącymi workflow

### 1.2 Kluczowe Funkcjonalności

#### A. Menu Zarządzania Asystentami
- Lista wszystkich zdefiniowanych asystentów (tabela z kolumnami: ID, Nazwa, Provider, Model, Status)
- Możliwość dodawania nowych asystentów
- Edycja istniejących asystentów (osobny ekran)
- Usuwanie asystentów (z ostrzeżeniem o użyciu w workflow)
- Eksport/import konfiguracji (JSON)

#### B. Konfiguracja Asystenta
Każdy asystent zawiera:
- **Podstawowe**:
  - `name` - Nazwa asystenta
  - `description` - Opis (opcjonalny)
  - `provider` - Provider AI (na razie tylko "openai", architektura gotowa na "claude", "gemini")
  - `status` - active/inactive

- **Prompts & Messages**:
  - `system_prompt` - Wiadomość systemowa z obsługą interpolacji zmiennych (`{variable_name}`)
  - `user_message_template` - Template wiadomości użytkownika z interpolacją

- **Parametry API** (dla wszystkich providerów):
  - `model` - Model AI (np. "gpt-4o-mini")
  - `temperature` - 0.0-2.0 (dla OpenAI), różne zakresy dla innych
  - `top_p` - 0.0-1.0
  - `max_tokens` - Maksymalna długość odpowiedzi
  - `frequency_penalty` - -2.0 do 2.0 (OpenAI)
  - `presence_penalty` - -2.0 do 2.0 (OpenAI) [opcjonalne, możemy dodać]

- **Dodatkowe**:
  - `expected_format` - "text" lub "json"
  - `output_variables` - Array zmiennych do wyciągnięcia z JSON
  - `created_at`, `updated_at` - Timestamps
  - `created_by` - ID użytkownika

#### C. Integracja z Workflow
- W konfiguracji workflow step typu "ai_assistant":
  - **Backward Compatibility**: Jeśli workflow zawiera `system_prompt` i `user_message` w JSON → użyj ich (legacy mode)
  - **Nowy System**: Jeśli workflow zawiera `assistant_id` → załaduj konfigurację z bazy asystentów

- Przy zapisie workflow:
  - Jeśli użytkownik edytuje prompty/parametry asystenta w workflow → zapisz je do bazy asystentów (globalnie)
  - Ostrzeżenie: "Ten asystent jest używany w X workflow. Zmiana wpłynie na wszystkie."

#### D. Integracja z Translacjami (OpenAI Settings)
- Dropdown do wyboru asystenta z grupowaniem (jak przy modelach):
  - **Optgroup: "OpenAI Assistants"** → asystenci z OpenAI Assistants API (legacy, `asst_xxx`)
  - **Optgroup: "System Assistants"** → asystenci z naszej bazy

- Przy wyborze "System Assistant":
  - Załaduj konfigurację z bazy
  - Użyj **Chat Completions API** (nie Assistants API!)
  - System prompt z asystenta
  - User message: **Ignoruj pole "user message" z UI translacji** → użyj JSON z post content
  - Parametry: temperature, max_tokens, etc. z konfiguracji asystenta

---

## 2. Architektura Techniczna

### 2.1 Struktura Bazy Danych

#### Tabela: `wp_polytrans_assistants`

```sql
CREATE TABLE wp_polytrans_assistants (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  description text,
  provider varchar(50) NOT NULL DEFAULT 'openai',
  status varchar(20) NOT NULL DEFAULT 'active',

  -- Prompts
  system_prompt text NOT NULL,
  user_message_template text,

  -- API Parameters (JSON for flexibility)
  api_parameters text NOT NULL,  -- JSON: {model, temperature, top_p, max_tokens, frequency_penalty, ...}

  -- Response Configuration
  expected_format varchar(20) NOT NULL DEFAULT 'text',
  output_variables text,  -- JSON array

  -- Metadata
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  created_by bigint(20) unsigned,

  PRIMARY KEY (id),
  KEY provider (provider),
  KEY status (status),
  KEY created_by (created_by),
  KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Struktura `api_parameters` (JSON)**:
```json
{
  "model": "gpt-4o-mini",
  "temperature": 0.7,
  "top_p": 1.0,
  "max_tokens": 2000,
  "frequency_penalty": 0.0,
  "presence_penalty": 0.0
}
```

### 2.2 Struktura Klas PHP

#### A. `class-assistant-manager.php`
**Lokalizacja**: `/includes/assistants/class-assistant-manager.php`

```php
class PolyTrans_Assistant_Manager {
    // CRUD Operations
    public static function create_assistant($data);
    public static function update_assistant($id, $data);
    public static function delete_assistant($id);
    public static function get_assistant($id);
    public static function get_all_assistants($filters = []);

    // Validation
    public static function validate_assistant_data($data);

    // Utility
    public static function create_assistants_table();
    public static function get_assistants_for_dropdown($group_by_provider = true);
    public static function check_assistant_usage($id);  // Check if used in workflows/translations
}
```

#### B. `class-assistant-executor.php`
**Lokalizacja**: `/includes/assistants/class-assistant-executor.php`

```php
class PolyTrans_Assistant_Executor {
    /**
     * Execute assistant with given context
     *
     * @param int $assistant_id Assistant ID from database
     * @param array $context Variables for interpolation
     * @return array {success, data, error, execution_time, tokens_used}
     */
    public static function execute($assistant_id, $context);

    /**
     * Execute assistant directly with config (without DB lookup)
     */
    public static function execute_with_config($assistant_config, $context);

    // Private methods
    private static function interpolate_prompts($assistant_config, $context);
    private static function call_provider_api($assistant_config, $interpolated_prompts);
    private static function process_response($response, $assistant_config);
}
```

#### C. `class-assistants-menu.php`
**Lokalizacja**: `/includes/menu/class-assistants-menu.php`

```php
class PolyTrans_Assistants_Menu {
    /**
     * Register menu and pages
     */
    public static function register();

    /**
     * Render assistants list page
     */
    public static function render_list_page();

    /**
     * Render assistant edit page
     */
    public static function render_edit_page();

    /**
     * Handle AJAX requests
     */
    public static function ajax_load_assistants();
    public static function ajax_save_assistant();
    public static function ajax_delete_assistant();
    public static function ajax_validate_assistant();
}
```

#### D. Modyfikacje w istniejących klasach

**`class-ai-assistant-step.php`** (workflow step):
```php
// Dodaj nową logikę w execute():
public function execute($context, $step_config) {
    // BACKWARD COMPATIBILITY CHECK
    if (isset($step_config['system_prompt']) && !empty($step_config['system_prompt'])) {
        // LEGACY MODE: Use prompts from step_config directly
        return $this->execute_legacy($context, $step_config);
    }

    // NEW MODE: Load from assistant database
    if (isset($step_config['assistant_id'])) {
        $assistant = PolyTrans_Assistant_Manager::get_assistant($step_config['assistant_id']);
        if (!$assistant) {
            return ['success' => false, 'error' => 'Assistant not found'];
        }

        // Merge assistant config with step overrides
        $merged_config = array_merge($assistant, $step_config);
        return PolyTrans_Assistant_Executor::execute_with_config($merged_config, $context);
    }

    return ['success' => false, 'error' => 'No assistant or prompts configured'];
}
```

**`class-openai-translation-provider.php`** (dla translacji):
```php
// W metodzie translate() dodaj obsługę system assistants
private function get_translation_assistant_config($source_lang, $target_lang) {
    $pair_key = $source_lang . '_to_' . $target_lang;
    $assistant_ref = $this->settings['openai_assistants'][$pair_key] ?? '';

    if (empty($assistant_ref)) {
        return $this->get_default_translation_config();
    }

    // Check if it's a system assistant (format: "system:123")
    if (strpos($assistant_ref, 'system:') === 0) {
        $assistant_id = intval(substr($assistant_ref, 7));
        $assistant = PolyTrans_Assistant_Manager::get_assistant($assistant_id);

        if ($assistant) {
            return [
                'type' => 'system_assistant',
                'config' => $assistant,
                'use_chat_api' => true  // Force Chat API, not Assistants API
            ];
        }
    }

    // Legacy: OpenAI Assistant ID (format: "asst_xxx")
    if (strpos($assistant_ref, 'asst_') === 0) {
        return [
            'type' => 'openai_assistant',
            'assistant_id' => $assistant_ref,
            'use_assistants_api' => true
        ];
    }

    return $this->get_default_translation_config();
}
```

---

## 3. Plan Implementacji (Step-by-Step)

### FAZA 1: Infrastruktura Bazy Danych i Core Classes

#### Step 1.1: Utworzenie tabeli bazy danych
**Pliki**:
- `/includes/assistants/class-assistant-manager.php` (nowy)

**Zadania**:
- [x] Utworzyć metodę `create_assistants_table()` wzorowaną na `PolyTrans_Logs_Manager::create_logs_table()`
- [x] Dodać logikę adaptacji struktury tabeli (`check_and_adapt_table_structure()`)
- [x] Hook do aktywacji pluginu dla utworzenia tabeli

**SQL Migration**:
```php
// W polytrans.php głównym pliku:
register_activation_hook(__FILE__, 'polytrans_activate');

function polytrans_activate() {
    require_once plugin_dir_path(__FILE__) . 'includes/assistants/class-assistant-manager.php';
    PolyTrans_Assistant_Manager::create_assistants_table();
}
```

#### Step 1.2: Implementacja Assistant Manager (CRUD)
**Plik**: `/includes/assistants/class-assistant-manager.php`

**Metody do implementacji**:
```php
// CREATE
create_assistant($data): int|WP_Error

// READ
get_assistant($id): array|null
get_all_assistants($filters = []): array
get_assistants_for_dropdown($group_by_provider = true): array

// UPDATE
update_assistant($id, $data): bool|WP_Error

// DELETE
delete_assistant($id): bool|WP_Error

// VALIDATION
validate_assistant_data($data): array {valid: bool, errors: array}

// UTILITY
check_assistant_usage($id): array {workflows: [], translations: []}
```

**Walidacja danych**:
- `name`: required, max 255 chars
- `provider`: required, one of ['openai', 'claude', 'gemini']
- `system_prompt`: required, max 65535 chars (TEXT)
- `api_parameters`: valid JSON with required fields based on provider
- `expected_format`: one of ['text', 'json']

#### Step 1.3: Implementacja Assistant Executor
**Plik**: `/includes/assistants/class-assistant-executor.php`

**Flow wykonania**:
```
1. Load assistant config (from DB or passed directly)
2. Interpolate system_prompt & user_message_template using Variable Manager
3. Prepare API request based on provider
4. Call provider API (OpenAI Chat Completions)
5. Process response (text or JSON parsing)
6. Return standardized result
```

**Metoda główna**:
```php
public static function execute($assistant_id, $context) {
    // 1. Load
    $assistant = PolyTrans_Assistant_Manager::get_assistant($assistant_id);
    if (!$assistant) {
        return ['success' => false, 'error' => 'Assistant not found'];
    }

    // 2. Execute with config
    return self::execute_with_config($assistant, $context);
}

public static function execute_with_config($assistant_config, $context) {
    $start_time = microtime(true);

    // 3. Interpolate
    $interpolated = self::interpolate_prompts($assistant_config, $context);

    // 4. Call API
    $api_response = self::call_provider_api($assistant_config, $interpolated);

    if (!$api_response['success']) {
        return $api_response;
    }

    // 5. Process
    $processed = self::process_response($api_response['data'], $assistant_config);

    // 6. Return
    return [
        'success' => true,
        'data' => $processed,
        'execution_time' => microtime(true) - $start_time,
        'tokens_used' => $api_response['tokens_used'] ?? 0,
        'interpolated_prompts' => $interpolated
    ];
}
```

---

### FAZA 2: Admin UI dla Zarządzania Asystentami

#### Step 2.1: Rejestracja Menu
**Plik**: `/includes/menu/class-assistants-menu.php`

**Menu Structure**:
```
PolyTrans
├── Translation Settings
├── Assistants ← NOWE
│   ├── All Assistants (lista)
│   └── Add New (edit page z ID=0)
└── Post Processing
```

**Hook**:
```php
add_action('admin_menu', ['PolyTrans_Assistants_Menu', 'register'], 20);

public static function register() {
    add_submenu_page(
        'polytrans-settings',           // Parent slug
        __('AI Assistants', 'polytrans'),
        __('Assistants', 'polytrans'),
        'manage_options',
        'polytrans-assistants',
        [__CLASS__, 'render_list_page']
    );
}
```

#### Step 2.2: Strona Listingu Asystentów
**UI Elements**:
- **Nagłówek**: "AI Assistants" + przycisk "Add New Assistant"
- **Tabela**:
  - Kolumny: [Checkbox, Name, Provider, Model, Status, Used In, Actions]
  - Sortowanie: Name (A-Z), Created Date (newest first)
  - Bulk Actions: Delete, Activate, Deactivate

- **Filters**:
  - Provider: All | OpenAI | Claude | Gemini
  - Status: All | Active | Inactive

**HTML Structure** (podobny do workflow listing):
```html
<div class="wrap">
    <h1>
        <?php echo esc_html__('AI Assistants', 'polytrans'); ?>
        <a href="?page=polytrans-assistants&action=edit&id=0" class="page-title-action">
            <?php echo esc_html__('Add New Assistant', 'polytrans'); ?>
        </a>
    </h1>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="provider_filter">...</select>
            <select name="status_filter">...</select>
            <button type="submit" class="button">Filter</button>
        </div>
    </div>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <td class="check-column"><input type="checkbox" /></td>
                <th>Name</th>
                <th>Provider</th>
                <th>Model</th>
                <th>Status</th>
                <th>Used In</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assistants as $assistant): ?>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" name="assistant_ids[]" value="<?php echo $assistant['id']; ?>" />
                    </th>
                    <td>
                        <strong><?php echo esc_html($assistant['name']); ?></strong>
                        <?php if (!empty($assistant['description'])): ?>
                            <br><small><?php echo esc_html($assistant['description']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="provider-badge provider-<?php echo esc_attr($assistant['provider']); ?>">
                            <?php echo esc_html(ucfirst($assistant['provider'])); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($assistant['api_parameters']['model'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($assistant['status']); ?>">
                            <?php echo esc_html(ucfirst($assistant['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $usage = PolyTrans_Assistant_Manager::check_assistant_usage($assistant['id']);
                        $total = count($usage['workflows']) + count($usage['translations']);
                        echo $total > 0 ? sprintf(__('%d workflow(s)', 'polytrans'), $total) : '—';
                        ?>
                    </td>
                    <td>
                        <a href="?page=polytrans-assistants&action=edit&id=<?php echo $assistant['id']; ?>">
                            <?php echo esc_html__('Edit', 'polytrans'); ?>
                        </a> |
                        <a href="#" class="delete-assistant" data-id="<?php echo $assistant['id']; ?>" style="color:#a00;">
                            <?php echo esc_html__('Delete', 'polytrans'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

#### Step 2.3: Strona Edycji Asystenta
**UI Sections**:

1. **Basic Information**:
   - Name (text input, required)
   - Description (textarea, optional)
   - Provider (select: OpenAI/Claude/Gemini, disabled for now except OpenAI)
   - Status (select: Active/Inactive)

2. **Prompts Configuration**:
   - System Prompt (CodeMirror textarea, required, z syntax highlighting)
   - User Message Template (CodeMirror textarea, optional)
   - Variable hints box: "Available variables: {title}, {content}, {source_lang}, {target_lang}, ..."

3. **API Parameters** (dynamic based on provider):
   ```
   Model: [dropdown z modelami dla wybranego providera]
   Temperature: [range slider 0.0-2.0, step 0.1]
   Top P: [range slider 0.0-1.0, step 0.01]
   Max Tokens: [number input, min 1, max 4000]
   Frequency Penalty: [range slider -2.0 to 2.0, step 0.1]
   ```

4. **Response Configuration**:
   - Expected Format (select: Text/JSON)
   - Output Variables (text input for comma-separated, pokazywany tylko gdy JSON)

5. **Test Section**:
   - "Test Assistant" przycisk
   - Test context (JSON textarea)
   - Test result (readonly textarea)

**HTML Structure**:
```html
<div class="wrap">
    <h1>
        <?php echo $assistant_id ? esc_html__('Edit Assistant', 'polytrans') : esc_html__('Add New Assistant', 'polytrans'); ?>
    </h1>

    <form method="post" id="assistant-edit-form">
        <?php wp_nonce_field('polytrans_save_assistant'); ?>
        <input type="hidden" name="assistant_id" value="<?php echo $assistant_id; ?>" />

        <!-- Basic Info -->
        <div class="assistant-section">
            <h2><?php esc_html_e('Basic Information', 'polytrans'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="assistant-name"><?php esc_html_e('Name', 'polytrans'); ?> *</label></th>
                    <td><input type="text" id="assistant-name" name="name" required /></td>
                </tr>
                <tr>
                    <th><label for="assistant-description"><?php esc_html_e('Description', 'polytrans'); ?></label></th>
                    <td><textarea id="assistant-description" name="description" rows="3"></textarea></td>
                </tr>
                <tr>
                    <th><label for="assistant-provider"><?php esc_html_e('Provider', 'polytrans'); ?></label></th>
                    <td>
                        <select id="assistant-provider" name="provider">
                            <option value="openai"><?php esc_html_e('OpenAI', 'polytrans'); ?></option>
                            <option value="claude" disabled><?php esc_html_e('Claude (Coming Soon)', 'polytrans'); ?></option>
                            <option value="gemini" disabled><?php esc_html_e('Gemini (Coming Soon)', 'polytrans'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="assistant-status"><?php esc_html_e('Status', 'polytrans'); ?></label></th>
                    <td>
                        <select id="assistant-status" name="status">
                            <option value="active"><?php esc_html_e('Active', 'polytrans'); ?></option>
                            <option value="inactive"><?php esc_html_e('Inactive', 'polytrans'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Prompts Configuration -->
        <div class="assistant-section">
            <h2><?php esc_html_e('Prompts Configuration', 'polytrans'); ?></h2>
            <p class="description">
                <?php esc_html_e('Use {variable_name} syntax to include dynamic content. Available variables depend on context (workflow or translation).', 'polytrans'); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="system-prompt"><?php esc_html_e('System Prompt', 'polytrans'); ?> *</label></th>
                    <td>
                        <textarea id="system-prompt" name="system_prompt" rows="8" required></textarea>
                        <p class="description"><?php esc_html_e('Instructions for the AI assistant.', 'polytrans'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="user-message-template"><?php esc_html_e('User Message Template', 'polytrans'); ?></label></th>
                    <td>
                        <textarea id="user-message-template" name="user_message_template" rows="6"></textarea>
                        <p class="description"><?php esc_html_e('Template for user message. Leave empty to use context directly.', 'polytrans'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Parameters -->
        <div class="assistant-section">
            <h2><?php esc_html_e('API Parameters', 'polytrans'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="api-model"><?php esc_html_e('Model', 'polytrans'); ?></label></th>
                    <td>
                        <select id="api-model" name="api_parameters[model]">
                            <!-- Populated dynamically based on provider -->
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="api-temperature"><?php esc_html_e('Temperature', 'polytrans'); ?></label></th>
                    <td>
                        <input type="range" id="api-temperature" name="api_parameters[temperature]"
                               min="0" max="2" step="0.1" value="0.7" />
                        <output for="api-temperature">0.7</output>
                        <p class="description"><?php esc_html_e('0 = focused, 2 = creative', 'polytrans'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="api-top-p"><?php esc_html_e('Top P', 'polytrans'); ?></label></th>
                    <td>
                        <input type="range" id="api-top-p" name="api_parameters[top_p]"
                               min="0" max="1" step="0.01" value="1" />
                        <output for="api-top-p">1.0</output>
                    </td>
                </tr>
                <tr>
                    <th><label for="api-max-tokens"><?php esc_html_e('Max Tokens', 'polytrans'); ?></label></th>
                    <td>
                        <input type="number" id="api-max-tokens" name="api_parameters[max_tokens]"
                               min="1" max="4000" value="2000" />
                    </td>
                </tr>
                <tr>
                    <th><label for="api-frequency-penalty"><?php esc_html_e('Frequency Penalty', 'polytrans'); ?></label></th>
                    <td>
                        <input type="range" id="api-frequency-penalty" name="api_parameters[frequency_penalty]"
                               min="-2" max="2" step="0.1" value="0" />
                        <output for="api-frequency-penalty">0.0</output>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Response Configuration -->
        <div class="assistant-section">
            <h2><?php esc_html_e('Response Configuration', 'polytrans'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="expected-format"><?php esc_html_e('Expected Format', 'polytrans'); ?></label></th>
                    <td>
                        <select id="expected-format" name="expected_format">
                            <option value="text"><?php esc_html_e('Plain Text', 'polytrans'); ?></option>
                            <option value="json"><?php esc_html_e('JSON Object', 'polytrans'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="output-variables-row" style="display:none;">
                    <th><label for="output-variables"><?php esc_html_e('Output Variables', 'polytrans'); ?></label></th>
                    <td>
                        <input type="text" id="output-variables" name="output_variables"
                               placeholder="var1, var2, var3" />
                        <p class="description"><?php esc_html_e('Comma-separated variable names to extract from JSON response', 'polytrans'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Actions -->
        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Assistant', 'polytrans'); ?></button>
            <button type="button" id="test-assistant" class="button"><?php esc_html_e('Test Assistant', 'polytrans'); ?></button>
            <a href="?page=polytrans-assistants" class="button"><?php esc_html_e('Cancel', 'polytrans'); ?></a>
        </p>
    </form>
</div>
```

#### Step 2.4: JavaScript dla Admin UI
**Plik**: `/assets/js/assistants-admin.js`

**Funkcjonalności**:
- Range slider output synchronization
- Show/hide output variables based on expected_format
- Dynamic model dropdown based on provider
- Test assistant functionality (AJAX)
- Delete confirmation
- Form validation przed submit

---

### FAZA 3: Integracja z Workflow System

#### Step 3.1: Modyfikacja UI Workflow Editora
**Plik**: `/assets/js/postprocessing-admin.js`

**Zmiany w Step Configuration Modal**:

Dla step typu "ai_assistant", dodaj nową sekcję na górze:

```html
<!-- Assistant Selection -->
<div class="step-field">
    <label><?php esc_html_e('Assistant Source', 'polytrans'); ?></label>
    <select id="step-assistant-source" class="assistant-source-selector">
        <option value="custom"><?php esc_html_e('Custom Prompts (Legacy)', 'polytrans'); ?></option>
        <option value="system"><?php esc_html_e('System Assistant', 'polytrans'); ?></option>
    </select>
</div>

<!-- System Assistant Selection (pokazywany gdy source=system) -->
<div class="step-field assistant-field" id="system-assistant-field" style="display:none;">
    <label><?php esc_html_e('Select Assistant', 'polytrans'); ?></label>
    <select id="step-system-assistant" name="assistant_id">
        <option value=""><?php esc_html_e('-- Select Assistant --', 'polytrans'); ?></option>
        <?php foreach ($system_assistants as $assistant): ?>
            <option value="<?php echo $assistant['id']; ?>">
                <?php echo esc_html($assistant['name']); ?> (<?php echo esc_html($assistant['api_parameters']['model']); ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description warning-message">
        ⚠️ <?php esc_html_e('Editing this assistant will affect ALL workflows using it.', 'polytrans'); ?>
        <a href="#" id="edit-assistant-link"><?php esc_html_e('Edit Assistant', 'polytrans'); ?></a>
    </p>
</div>

<!-- Custom Prompts (pokazywane gdy source=custom lub backward compatibility) -->
<div class="custom-prompts-fields">
    <!-- Existing system_prompt, user_message fields -->
</div>
```

**JavaScript Logic**:
```javascript
// Toggle visibility based on assistant source
$('#step-assistant-source').on('change', function() {
    const source = $(this).val();

    if (source === 'system') {
        $('#system-assistant-field').show();
        $('.custom-prompts-fields').hide();
    } else {
        $('#system-assistant-field').hide();
        $('.custom-prompts-fields').show();
    }
});

// Load assistant config into modal (read-only preview)
$('#step-system-assistant').on('change', function() {
    const assistantId = $(this).val();
    if (!assistantId) return;

    // AJAX: Load assistant and show preview
    $.post(ajaxurl, {
        action: 'polytrans_load_assistant_preview',
        assistant_id: assistantId
    }, function(response) {
        // Show preview below selector
        $('#assistant-preview').html(response.data.preview_html);
    });
});
```

#### Step 3.2: Backward Compatibility w `class-ai-assistant-step.php`

**Zmiany**:
```php
public function execute($context, $step_config)
{
    $start_time = microtime(true);

    try {
        // ============================================
        // BACKWARD COMPATIBILITY: Check for legacy mode
        // ============================================
        if (isset($step_config['system_prompt']) && !empty($step_config['system_prompt'])) {
            // LEGACY MODE: Use existing implementation
            return $this->execute_legacy_mode($context, $step_config);
        }

        // ============================================
        // NEW MODE: Use system assistant
        // ============================================
        if (!isset($step_config['assistant_id']) || empty($step_config['assistant_id'])) {
            return [
                'success' => false,
                'error' => 'No assistant configured. Please select a system assistant or configure custom prompts.'
            ];
        }

        $assistant_id = intval($step_config['assistant_id']);
        $result = PolyTrans_Assistant_Executor::execute($assistant_id, $context);

        // Add step-specific overrides if any
        $result['step_id'] = $step_config['id'] ?? null;
        $result['step_name'] = $step_config['name'] ?? null;

        return $result;

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'execution_time' => microtime(true) - $start_time
        ];
    }
}

/**
 * Execute in legacy mode (existing implementation)
 */
private function execute_legacy_mode($context, $step_config)
{
    // Existing implementation from lines 44-124
    // (kopiujemy całą obecną logikę tutaj)
}
```

#### Step 3.3: Aktualizacja Workflow Storage
**Plik**: `/includes/postprocessing/managers/class-workflow-storage-manager.php`

**Zmiany w strukturze workflow step**:

**Legacy format** (nadal wspierany):
```json
{
  "type": "ai_assistant",
  "system_prompt": "...",
  "user_message": "...",
  "model": "gpt-4o-mini",
  "temperature": 0.7
}
```

**New format**:
```json
{
  "type": "ai_assistant",
  "assistant_id": 123
}
```

**Migration function** (opcjonalnie, dla wygody):
```php
public static function migrate_workflow_to_system_assistants($workflow_id)
{
    // Load workflow
    $workflow = self::get_workflow($workflow_id);

    // For each step with type=ai_assistant
    foreach ($workflow['steps'] as &$step) {
        if ($step['type'] !== 'ai_assistant') continue;
        if (isset($step['assistant_id'])) continue; // Already migrated
        if (empty($step['system_prompt'])) continue;

        // Create a system assistant from step config
        $assistant_data = [
            'name' => sprintf('%s - Step: %s', $workflow['name'], $step['name']),
            'provider' => 'openai',
            'system_prompt' => $step['system_prompt'],
            'user_message_template' => $step['user_message'] ?? '',
            'api_parameters' => [
                'model' => $step['model'] ?? 'gpt-4o-mini',
                'temperature' => $step['temperature'] ?? 0.7,
                'max_tokens' => $step['max_tokens'] ?? null
            ],
            'expected_format' => $step['expected_format'] ?? 'text',
            'output_variables' => $step['output_variables'] ?? [],
            'status' => 'active'
        ];

        $assistant_id = PolyTrans_Assistant_Manager::create_assistant($assistant_data);

        // Update step to reference new assistant
        $step['assistant_id'] = $assistant_id;

        // Keep legacy fields for safety (can be removed manually later)
        // unset($step['system_prompt'], $step['user_message']);
    }

    // Save updated workflow
    self::update_workflow($workflow_id, $workflow);
}
```

---

### FAZA 4: Integracja z Translation System

#### Step 4.1: Modyfikacja UI w OpenAI Settings
**Plik**: `/includes/providers/openai/class-openai-settings-ui.php`

**Zmiany w Translation Assistants Section** (linie 105-165):

```php
// Replace existing assistant dropdown with grouped dropdown
foreach ($target_langs as $target_lang):
    $target_name = $lang_names[array_search($target_lang, $languages)] ?? strtoupper($target_lang);
    $pair_key = $source_lang . '_to_' . $target_lang;
    $assistant_ref = $openai_assistants[$pair_key] ?? '';
?>
    <div style="margin-bottom:0.5em;">
        <label style="display:block;font-weight:500;">
            <?php echo esc_html(sprintf(__('To %s', 'polytrans'), $target_name)); ?>
        </label>

        <!-- Hidden input to preserve the value -->
        <input type="hidden"
            name="openai_assistants[<?php echo esc_attr($pair_key); ?>]"
            value="<?php echo esc_attr($assistant_ref); ?>"
            class="openai-assistant-hidden"
            data-pair="<?php echo esc_attr($pair_key); ?>" />

        <!-- Grouped select -->
        <select class="openai-assistant-select"
            data-pair="<?php echo esc_attr($pair_key); ?>"
            style="width:100%;">
            <option value=""><?php esc_html_e('-- Select Assistant --', 'polytrans'); ?></option>

            <!-- OpenAI Assistants Group -->
            <optgroup label="<?php esc_attr_e('OpenAI Assistants', 'polytrans'); ?>">
                <?php
                // Load OpenAI assistants from API (existing logic)
                $openai_assistants_list = $this->get_openai_assistants_from_api();
                foreach ($openai_assistants_list as $asst):
                    $selected = ($assistant_ref === $asst['id']) ? 'selected' : '';
                ?>
                    <option value="<?php echo esc_attr($asst['id']); ?>" <?php echo $selected; ?>>
                        <?php echo esc_html($asst['name']); ?> (<?php echo esc_html($asst['model']); ?>)
                    </option>
                <?php endforeach; ?>
            </optgroup>

            <!-- System Assistants Group -->
            <optgroup label="<?php esc_attr_e('System Assistants', 'polytrans'); ?>">
                <?php
                // Load system assistants from database
                $system_assistants = PolyTrans_Assistant_Manager::get_all_assistants([
                    'status' => 'active',
                    'provider' => 'openai'
                ]);
                foreach ($system_assistants as $asst):
                    $asst_ref = 'system:' . $asst['id'];
                    $selected = ($assistant_ref === $asst_ref) ? 'selected' : '';
                    $model = $asst['api_parameters']['model'] ?? 'N/A';
                ?>
                    <option value="<?php echo esc_attr($asst_ref); ?>" <?php echo $selected; ?>>
                        <?php echo esc_html($asst['name']); ?> (<?php echo esc_html($model); ?>)
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </div>
<?php endforeach; ?>
```

#### Step 4.2: Modyfikacja Translation Provider Logic
**Plik**: `/includes/providers/openai/class-openai-translation-provider.php`

**Zmiana w metodzie `translate()`**:

```php
private function get_assistant_config_for_translation($source_lang, $target_lang)
{
    $pair_key = $source_lang . '_to_' . $target_lang;
    $assistant_ref = $this->settings['openai_assistants'][$pair_key] ?? '';

    if (empty($assistant_ref)) {
        return $this->get_default_translation_config($source_lang, $target_lang);
    }

    // ============================================
    // SYSTEM ASSISTANT (format: "system:123")
    // ============================================
    if (strpos($assistant_ref, 'system:') === 0) {
        $assistant_id = intval(substr($assistant_ref, 7));
        $assistant = PolyTrans_Assistant_Manager::get_assistant($assistant_id);

        if (!$assistant) {
            error_log("[PolyTrans] System assistant #{$assistant_id} not found for {$pair_key}");
            return $this->get_default_translation_config($source_lang, $target_lang);
        }

        // Validate assistant is active
        if ($assistant['status'] !== 'active') {
            error_log("[PolyTrans] System assistant #{$assistant_id} is inactive");
            return $this->get_default_translation_config($source_lang, $target_lang);
        }

        return [
            'type' => 'system_assistant',
            'assistant' => $assistant,
            'use_chat_api' => true,
            'ignore_user_message_field' => true  // Important!
        ];
    }

    // ============================================
    // OPENAI ASSISTANT (format: "asst_xxx")
    // ============================================
    if (strpos($assistant_ref, 'asst_') === 0) {
        return [
            'type' => 'openai_assistant',
            'assistant_id' => $assistant_ref,
            'use_assistants_api' => true
        ];
    }

    // Fallback
    return $this->get_default_translation_config($source_lang, $target_lang);
}

/**
 * Execute translation with system assistant
 */
private function translate_with_system_assistant($post_content, $assistant, $source_lang, $target_lang)
{
    // Prepare context for interpolation
    $context = [
        'post_content' => $post_content,
        'post_content_json' => json_encode($post_content),  // For structured data
        'source_lang' => $source_lang,
        'target_lang' => $target_lang,
        'source_language' => $source_lang,
        'target_language' => $target_lang
    ];

    // Execute assistant
    $result = PolyTrans_Assistant_Executor::execute_with_config($assistant, $context);

    if (!$result['success']) {
        return [
            'success' => false,
            'error' => $result['error']
        ];
    }

    // Extract translation from result
    // Assuming the response is the translated content
    $translated_content = $result['data']['ai_response'] ?? '';

    // If JSON format, might need to parse
    if ($assistant['expected_format'] === 'json') {
        // Expected structure: {"translated": {...}}
        // Or the AI might return the full post_content structure
        // Handle accordingly
        if (isset($result['data']['translated'])) {
            $translated_content = $result['data']['translated'];
        } else {
            // Assume the entire response is the translated structure
            $translated_content = $result['data'];
        }
    }

    return [
        'success' => true,
        'translated_content' => $translated_content,
        'tokens_used' => $result['tokens_used'] ?? 0
    ];
}
```

**Aktualizacja głównej metody `translate()`**:

```php
public function translate($post_content, $source_lang, $target_lang)
{
    $config = $this->get_assistant_config_for_translation($source_lang, $target_lang);

    switch ($config['type']) {
        case 'system_assistant':
            return $this->translate_with_system_assistant(
                $post_content,
                $config['assistant'],
                $source_lang,
                $target_lang
            );

        case 'openai_assistant':
            return $this->translate_with_openai_assistant(
                $post_content,
                $config['assistant_id'],
                $source_lang,
                $target_lang
            );

        default:
            return $this->translate_with_default_config(
                $post_content,
                $source_lang,
                $target_lang
            );
    }
}
```

---

### FAZA 5: Testing & Documentation

#### Step 5.1: Unit Tests
**Pliki**:
- `tests/test-assistant-manager.php`
- `tests/test-assistant-executor.php`

**Test Cases**:
```php
// Assistant Manager Tests
- test_create_assistant()
- test_update_assistant()
- test_delete_assistant()
- test_validate_assistant_data()
- test_check_assistant_usage()
- test_get_assistants_for_dropdown()

// Assistant Executor Tests
- test_execute_with_text_response()
- test_execute_with_json_response()
- test_interpolate_variables()
- test_handle_api_errors()
- test_different_temperature_values()

// Integration Tests
- test_workflow_with_system_assistant()
- test_translation_with_system_assistant()
- test_backward_compatibility()
```

#### Step 5.2: Documentation
**Pliki do utworzenia/zaktualizowania**:

1. **`ASSISTANTS_SYSTEM_GUIDE.md`** - Przewodnik użytkownika:
   - Jak tworzyć asystentów
   - Jak używać w workflow
   - Jak używać w translacjach
   - Best practices dla promptów
   - FAQ

2. **`ASSISTANTS_API_REFERENCE.md`** - Dokumentacja API:
   - Struktura tabeli bazy danych
   - PHP API (klasy i metody)
   - JavaScript API
   - Hooks & Filters

3. **Aktualizacja `README.md`**:
   - Dodać sekcję o systemie asystentów
   - Linki do nowej dokumentacji

---

## 4. Migration Strategy

### 4.1 Migracja Workflow (Opcjonalna)

**WP-CLI Command**:
```php
/**
 * Migrate workflows to use system assistants
 *
 * ## OPTIONS
 *
 * [--workflow-id=<id>]
 * : Specific workflow ID to migrate. If omitted, migrates all.
 *
 * [--dry-run]
 * : Preview changes without saving.
 *
 * ## EXAMPLES
 *
 *     wp polytrans migrate-workflows --dry-run
 *     wp polytrans migrate-workflows --workflow-id=workflow_abc123
 */
WP_CLI::add_command('polytrans migrate-workflows', function($args, $assoc_args) {
    $workflow_id = $assoc_args['workflow-id'] ?? null;
    $dry_run = isset($assoc_args['dry-run']);

    $workflows = $workflow_id
        ? [PolyTrans_Workflow_Storage_Manager::get_workflow($workflow_id)]
        : PolyTrans_Workflow_Storage_Manager::get_all_workflows();

    $migrated = 0;
    $skipped = 0;

    foreach ($workflows as $workflow) {
        foreach ($workflow['steps'] as $step) {
            if ($step['type'] !== 'ai_assistant') continue;
            if (isset($step['assistant_id'])) {
                $skipped++;
                continue;
            }
            if (empty($step['system_prompt'])) {
                $skipped++;
                continue;
            }

            WP_CLI::log(sprintf(
                'Migrating workflow "%s", step "%s"',
                $workflow['name'],
                $step['name']
            ));

            if (!$dry_run) {
                PolyTrans_Workflow_Storage_Manager::migrate_workflow_to_system_assistants($workflow['id']);
                $migrated++;
            }
        }
    }

    WP_CLI::success(sprintf(
        'Migration complete. Migrated: %d, Skipped: %d',
        $migrated,
        $skipped
    ));
});
```

### 4.2 Migracja Translation Assistants

**Nie wymagana** - system wspiera obie opcje równocześnie:
- OpenAI Assistants (`asst_xxx`) → Assistants API
- System Assistants (`system:123`) → Chat API

Użytkownicy mogą stopniowo przechodzić na system assistants według uznania.

---

## 5. Future Enhancements (Phase 2)

### 5.1 Multi-Provider Support
- **Claude Provider**: `class-claude-provider.php`
- **Gemini Provider**: `class-gemini-provider.php`
- Provider abstraction layer: `interface-ai-provider.php`

### 5.2 Assistant Versioning
- Tabela `wp_polytrans_assistant_versions`
- Śledzenie zmian w asystentach
- Rollback do poprzedniej wersji

### 5.3 Assistant Templates/Presets
- Biblioteka gotowych asystentów do importu
- "Content Reviewer", "SEO Optimizer", "Translation Quality Checker", etc.
- JSON export/import

### 5.4 Advanced Testing
- Test console w UI asystenta
- Historie testów
- Porównanie wyników różnych wersji

### 5.5 Analytics
- Tracking użycia asystentów
- Statystyki: execution time, tokens used, success rate
- Dashboard z metrykami

---

## 6. Security Considerations

### 6.1 Permissions
- Tylko `manage_options` capability może zarządzać asystentami
- Nonce verification dla wszystkich form
- Capability checks przed AJAX handlers

### 6.2 Input Validation
- Sanitize all inputs przed zapisem do DB
- Validate JSON structure w `api_parameters`
- SQL injection prevention (używamy `$wpdb->prepare()`)

### 6.3 API Key Security
- API keys przechowywane w `wp_options` (istniejący system)
- Rozważyć encryption at rest (opcjonalnie)

---

## 7. Performance Considerations

### 7.1 Caching
- Cache assistants list (transient, 5 min)
- Cache dropdown options dla UI
- Invalidate cache on assistant create/update/delete

### 7.2 Database Optimization
- Indexes na często używane kolumny (provider, status, name)
- Limit query results (pagination)

### 7.3 API Rate Limiting
- Respect OpenAI rate limits
- Retry logic z exponential backoff
- Queue system dla batch operations (opcjonalnie)

---

## 8. Rollout Plan

### Phase 1: Core Implementation (Weeks 1-2)
- Database schema
- Assistant Manager & Executor
- Admin UI (basic)

### Phase 2: Workflow Integration (Week 3)
- Modify workflow step
- Backward compatibility
- Testing

### Phase 3: Translation Integration (Week 4)
- Modify OpenAI settings UI
- Update translation provider
- Testing

### Phase 4: Polish & Documentation (Week 5)
- JavaScript enhancements
- Error handling improvements
- Complete documentation
- User guide

### Phase 5: Beta Testing (Week 6)
- Internal testing
- Bug fixes
- Performance optimization

---

## 9. Success Criteria

✅ **Feature Complete**:
- [ ] Assistant CRUD operations working
- [ ] Workflow integration functional
- [ ] Translation integration functional
- [ ] Backward compatibility verified
- [ ] All tests passing

✅ **Quality**:
- [ ] No regressions in existing functionality
- [ ] Performance acceptable (< 100ms DB queries)
- [ ] Security review passed
- [ ] Code review completed

✅ **Documentation**:
- [ ] User guide published
- [ ] API reference complete
- [ ] Migration guide available

---

## 10. Open Questions & Decisions Needed

### Q1: Assistant Sharing/Permissions
**Question**: Czy asystenci powinni być globalni (dostępni dla wszystkich użytkowników) czy per-user?

**Recommendation**:
- Faza 1: Globalni (prostsze)
- Faza 2: Dodać `created_by` i opcjonalnie `is_public` flag

### Q2: API Parameter Overrides w Workflow
**Question**: Czy pozwolić na override parametrów API (temperature, max_tokens) per-step w workflow?

**Recommendation**:
- NIE w Fazie 1 (keep it simple)
- Jeśli potrzeba override → stwórz nowego asystenta (reużywalność)

### Q3: Deletion Policy
**Question**: Co się dzieje gdy usuwamy asystenta używanego w workflow?

**Recommendation**:
- **Soft delete**: Ustaw status na "deleted", zachowaj w DB
- Workflow pokazuje warning: "Assistant deleted or unavailable"
- Admin może "restore" lub wybrać nowego asystenta

### Q4: Interpolation Variables Registry
**Question**: Czy mieć centralny rejestr dostępnych zmiennych dla podpowiedzi w UI?

**Recommendation**:
- TAK - stwórz `class-variable-registry.php`
- Grupuj zmienne: Post Data, Meta Data, Context Data, Workflow Data
- Pokaż w UI jako "Available Variables" tooltip/panel

---

## 11. Summary

Ten plan implementuje kompletny system zarządzania asystentami AI, który:

✅ **Spełnia wymagania biznesowe**:
- Centralne zarządzanie asystentami
- Reużycie w workflow i translacjach
- Przyszłościowa architektura (multi-provider ready)

✅ **Zachowuje kompatybilność**:
- Istniejące workflow działają bez zmian
- Stopniowa migracja możliwa
- Brak breaking changes

✅ **Wysoka jakość**:
- Przemyślana struktura bazy danych
- Clean code architecture
- Comprehensive testing strategy

✅ **Gotowy do rozbudowy**:
- Provider abstraction layer
- Extensible API parameters
- Future-proof design

**Szacowany czas implementacji**: 5-6 tygodni
**Szacowana złożoność**: Medium-High
**Ryzyko**: Low (backward compatibility zachowana)

---

**Status**: ✅ Plan gotowy do review i zatwierdzenia
**Next Steps**: Review planu → Rozpoczęcie implementacji Faza 1
