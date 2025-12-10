# Faza 0: Variable System Refactor & Twig Integration

**Status**: 🔴 Critical - Must be done before Assistants System
**Estimated Time**: 1-2 weeks
**Risk**: Medium (core functionality changes)

---

## 1. Przegląd Problemów

### Aktualny Stan (Problemy znalezione w audycie)

#### Problem #1: Dual Naming Chaos
```
AI Prompt używa:    {title}, {content}, {excerpt}
                    ↓ (Post Data Provider)
                    variables['title'] = translated_post['title']

Change Display:     {post_title}, {post_content}
                    ↓ (ensure_context_has_post_data)
                    context['post_title'] = get_post()->post_title
```

**Konsekwencja**: Dwie nazwy dla tego samego pola, różne źródła, potencjalne niesynchronizacje.

#### Problem #2: Zmienne NIE są Aktualizowane Po Zmianach

**Scenariusz**:
```
Step 1: AI Assistant zmienia tytuł
  → Output: {ai_response: "New Title"}
  → Process: apply_change_to_context() lub execute_change()
  → Aktualizacja: context['post_title'] = "New Title" ✅

Step 2: Kolejny AI Assistant używa promptu:
  → Prompt: "Review this title: {title}"
  → Interpolacja: variables['title'] = OLD VALUE ❌

Problem: variables['title'] pochodzi z translated_post['title'],
         które NIGDY nie jest aktualizowane po zmianach!
```

**Diagram przepływu**:
```
Initial Context (build_context)
├── Post Data Provider
│   └── variables['title'] = "Original Title" ✅
└── Context Data Provider

    ↓

Step 1 Execute
├── AI changes title to "New Title"
└── apply_change_to_context()
    └── context['post_title'] = "New Title" ✅
    └── ❌ variables['title'] NOT UPDATED!

    ↓

Step 2 Execute (build_context again? NO!)
├── Prompt: "Review: {title}"
└── Interpolate from ORIGINAL variables
    └── {title} = "Original Title" ❌ STALE!
```

#### Problem #3: Test Mode Nie Odświeża Context

**Kod (class-workflow-output-processor.php, linie 127-128)**:
```php
if (!$test_mode && !empty($changes)) {  // ❌ Tylko production!
    $updated_context = $this->refresh_context_from_database($updated_context);
}
```

**Konsekwencja**: W test mode, po zmianach:
- `context['post_title']` = aktualizowany in-memory ✅
- `variables['title']` = NIE aktualizowany ❌
- Następny step widzi stare wartości

#### Problem #4: Ograniczona Funkcjonalność Templating

**Obecny system**:
```php
// Variable Manager, linia 66:
$pattern = '/\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}/';
```

**Co ma**:
- ✅ Proste zmienne: `{title}`
- ✅ Dot notation: `{post.meta.custom_field}`
- ✅ JSON detection (nie interpoluje `{"key": "value"}`)

**Czego brakuje**:
- ❌ Conditionals: `{% if title %}...{% endif %}`
- ❌ Loops: `{% for item in items %}...{% endfor %}`
- ❌ Filters: `{title|upper}`, `{content|excerpt:100}`
- ❌ Escape control: `{content|raw}` vs `{content|escape}`
- ❌ Default values: `{title|default:"Untitled"}`

---

## 2. Cel Fazy 0

### ✅ Co osiągniemy:

1. **Jednolity System Zmiennych**
   - Jedna konwencja nazewnictwa
   - Jedna źródłowa prawda (Single Source of Truth)
   - Konsekwentna aktualizacja we wszystkich trybach

2. **Twig Templating Engine**
   - Profesjonalny, battle-tested engine
   - Conditionals, loops, filters
   - Security (auto-escaping)
   - **Backward compatibility** z `{variable}` syntax

3. **Prawidłowa Context Refresh Logic**
   - Test mode symuluje zmiany jak production
   - Wszystkie zmienne aktualizowane po zmianach
   - Konsekwentne zachowanie między krokami workflow

4. **Extensibility**
   - Custom Twig filters dla WordPress (|wp_date, |wp_excerpt)
   - Easy adding new variables through providers
   - Better debugging (Twig error messages)

---

## 3. Design Decisions

### Decision #1: Unified Variable Naming

**Zasada**: Używamy **krótkich, czytelnych aliasów** dla najczęstszych pól.

#### Nowa Konwencja Nazw:

| Kategoria | Zmienne | Źródło | Przykład |
|-----------|---------|--------|----------|
| **Post Fields** | `title`, `content`, `excerpt`, `author`, `date`, `status` | Post Data Provider | `{title}`, `{content}` |
| **Meta Fields** | `meta.field_name` | Meta Data Provider | `{meta.custom_field}` |
| **Original Post** | `original.title`, `original.content`, `original.meta.field` | Post Data Provider | `{original.title}` |
| **Translated Post** | `translated.title`, `translated.content`, `translated.meta.field` | Post Data Provider (deprecated, use top-level) | `{translated.title}` |
| **Context** | `source_lang`, `target_lang`, `workflow_name`, `step_name` | Context Data Provider | `{source_lang}` |
| **Previous Steps** | `steps.step_id.output_var` | Workflow Executor | `{steps.step_1.ai_response}` |

**Deprecated (backward compatibility maintained)**:
- `{post_title}` → użyj `{title}`
- `{post_content}` → użyj `{content}`
- `{original_post.title}` → użyj `{original.title}`
- `{translated_post.title}` → użyj `{title}` lub `{translated.title}`

#### Struktura Context (nowa):

```php
$context = [
    // Top-level aliases (dla convenience)
    'title' => 'Post Title',
    'content' => 'Post content...',
    'excerpt' => 'Short excerpt',
    'author' => 'John Doe',
    'date' => '2024-01-15',
    'status' => 'publish',

    // Original post object
    'original' => [
        'id' => 123,
        'title' => 'Original Title',
        'content' => 'Original content...',
        'excerpt' => 'Original excerpt',
        'author' => 'Author Name',
        'date' => '2024-01-10',
        'status' => 'publish',
        'meta' => [
            'custom_field' => 'value'
        ]
    ],

    // Translated post object (optional, for clarity)
    'translated' => [
        'id' => 456,
        'title' => 'Post Title',  // Same as top-level 'title'
        'content' => 'Post content...',
        // ... same structure as original
    ],

    // Post meta (merged from both original and translated)
    'meta' => [
        'custom_field' => 'value',
        'another_field' => 'another value'
    ],

    // Context data
    'source_lang' => 'en',
    'target_lang' => 'pl',
    'workflow_name' => 'Review Workflow',
    'step_name' => 'Quality Check',

    // Previous steps output
    'steps' => [
        'step_1' => [
            'ai_response' => 'Review result...',
            'score' => 0.95
        ],
        'step_2' => [
            // ...
        ]
    ],

    // DEPRECATED (dla backward compatibility)
    'post_title' => 'Post Title',  // Alias to 'title'
    'post_content' => 'Post content...',  // Alias to 'content'
    'original_post' => [ /* ... same as 'original' */ ],
    'translated_post' => [ /* ... same as 'translated' */ ]
];
```

### Decision #2: Twig Integration Strategy

#### Podejście: Hybrid Templating System

**Warstwa 1: Twig (Primary)**
```twig
{# Full Twig syntax #}
{% if title %}
  <h1>{{ title|upper }}</h1>
{% else %}
  <h1>Untitled</h1>
{% endif %}

{# Loops #}
{% for step in steps %}
  Step {{ loop.index }}: {{ step.ai_response }}
{% endfor %}

{# Filters #}
{{ content|excerpt(100) }}
{{ date|wp_date('Y-m-d') }}
```

**Warstwa 2: Backward Compatibility Layer**
```php
// Przed Twig processing, konwertuj legacy syntax:
{title} → {{ title }}
{post.meta.field} → {{ post.meta.field }}
{original_post.title} → {{ original.title }}  // Z mapowaniem
```

#### Twig Environment Setup

```php
// class-twig-template-engine.php

class PolyTrans_Twig_Engine
{
    private static $twig;

    public static function init()
    {
        // Composer autoload (zakładamy że Twig jest zainstalowany)
        require_once POLYTRANS_PLUGIN_DIR . '/vendor/autoload.php';

        $loader = new \Twig\Loader\ArrayLoader([]);
        self::$twig = new \Twig\Environment($loader, [
            'cache' => POLYTRANS_PLUGIN_DIR . '/cache/twig',
            'auto_reload' => true,
            'autoescape' => 'html',  // Security
            'strict_variables' => false  // Nie fail na missing variables
        ]);

        // Add custom filters
        self::add_custom_filters();

        // Add custom functions
        self::add_custom_functions();
    }

    private static function add_custom_filters()
    {
        // WordPress-specific filters
        self::$twig->addFilter(new \Twig\TwigFilter('wp_excerpt', function($text, $length = 100) {
            return wp_trim_words($text, $length);
        }));

        self::$twig->addFilter(new \Twig\TwigFilter('wp_date', function($date, $format = null) {
            return wp_date($format ?: get_option('date_format'), strtotime($date));
        }));

        self::$twig->addFilter(new \Twig\TwigFilter('wp_kses', function($text) {
            return wp_kses_post($text);
        }));

        // Utility filters
        self::$twig->addFilter(new \Twig\TwigFilter('json_decode', function($json) {
            return json_decode($json, true);
        }));

        self::$twig->addFilter(new \Twig\TwigFilter('json_encode', function($data) {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }));
    }

    private static function add_custom_functions()
    {
        // WordPress functions
        self::$twig->addFunction(new \Twig\TwigFunction('get_post_meta', function($post_id, $key, $single = true) {
            return get_post_meta($post_id, $key, $single);
        }));
    }

    /**
     * Render template with context
     */
    public static function render($template_string, $context)
    {
        // Convert legacy syntax to Twig syntax
        $template_string = self::convert_legacy_syntax($template_string);

        // Create template
        $template = self::$twig->createTemplate($template_string);

        // Render
        return $template->render($context);
    }

    /**
     * Convert legacy {variable} syntax to Twig {{ variable }}
     */
    private static function convert_legacy_syntax($template)
    {
        // Pattern: {variable} lub {object.property}
        // UWAGA: NIE konwertuj JSON structures {"key": "value"}
        $pattern = '/\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}/';

        $converted = preg_replace_callback($pattern, function($matches) {
            $variable_path = $matches[1];

            // Map deprecated names to new names
            $variable_path = self::map_deprecated_variable($variable_path);

            // Convert to Twig syntax
            return '{{ ' . $variable_path . ' }}';
        }, $template);

        return $converted;
    }

    /**
     * Map deprecated variable names to new convention
     */
    private static function map_deprecated_variable($variable_path)
    {
        $mappings = [
            'post_title' => 'title',
            'post_content' => 'content',
            'post_excerpt' => 'excerpt',
            'original_post' => 'original',
            'translated_post' => 'translated',
            'original_meta' => 'original.meta',
            'translated_meta' => 'meta'
        ];

        // Check if variable starts with deprecated prefix
        foreach ($mappings as $old => $new) {
            if (strpos($variable_path, $old) === 0) {
                return str_replace($old, $new, $variable_path);
            }
        }

        return $variable_path;
    }

    /**
     * Check if template has Twig-specific syntax (not just legacy variables)
     */
    public static function is_twig_template($template)
    {
        // Check for Twig control structures
        return preg_match('/\{%\s*(if|for|block|extends|include)/', $template) ||
               preg_match('/\{\{.*\|/', $template);  // Filters
    }
}
```

### Decision #3: Context Refresh Strategy

#### Nowa Architektura: "Context Rebuilding"

Zamiast ręcznie aktualizować poszczególne pola, **przebudujemy cały context** przez providerów po każdej zmianie.

**Zalety**:
- ✅ Gwarantowana konsekwencja (wszystkie zmienne aktualizowane)
- ✅ Jednolita logika dla test i production mode
- ✅ Łatwe dodawanie nowych zmiennych (tylko w providerach)

**Nowy Flow**:

```php
// W class-workflow-output-processor.php

public function process_step_outputs($step_results, $output_actions, $context, $test_mode = false, $workflow = null)
{
    $updated_context = $context;
    $changes = [];
    $errors = [];

    // Ensure context has initial post data
    $updated_context = $this->ensure_context_has_post_data($updated_context);

    foreach ($output_actions as $action) {
        $change_result = $this->create_change_object($step_results, $action, $updated_context);

        if ($change_result['success']) {
            $changes[] = $change_result['change'];

            if ($test_mode) {
                // TEST MODE: Apply changes in-memory
                $updated_context = $this->apply_change_to_context($updated_context, $change_result['change']);
            } else {
                // PRODUCTION MODE: Execute to DB
                $execute_result = $this->execute_change($change_result['change'], $context);
                if (!$execute_result['success']) {
                    $errors[] = $execute_result['error'];
                }
            }
        } else {
            $errors[] = $change_result['error'];
        }
    }

    // =====================================================
    // NOWA LOGIKA: Rebuild context po WSZYSTKICH zmianach
    // =====================================================
    if (!empty($changes)) {
        if ($test_mode) {
            // Test mode: Rebuild from in-memory changes
            $updated_context = $this->rebuild_context_from_changes($updated_context, $changes);
        } else {
            // Production mode: Rebuild from database
            $updated_context = $this->rebuild_context_from_database($updated_context);
        }
    }

    return [
        'success' => empty($errors),
        'updated_context' => $updated_context,
        'changes' => $changes,
        'errors' => $errors
    ];
}

/**
 * Rebuild entire context from database (production mode)
 */
private function rebuild_context_from_database($context)
{
    // Get post IDs
    $translated_post_id = $context['translated']['id'] ?? $context['translated_post_id'] ?? null;
    $original_post_id = $context['original']['id'] ?? $context['original_post_id'] ?? null;

    if (!$translated_post_id) {
        return $context;  // Can't rebuild without post ID
    }

    // Create fresh context base
    $fresh_context = [
        'translated_post_id' => $translated_post_id,
        'original_post_id' => $original_post_id,
        'target_language' => $context['target_language'] ?? '',
        'source_language' => $context['source_language'] ?? '',
        'workflow_name' => $context['workflow_name'] ?? '',
        'step_name' => $context['step_name'] ?? ''
    ];

    // Rebuild through Variable Manager (using providers)
    require_once POLYTRANS_PLUGIN_DIR . '/includes/postprocessing/class-variable-manager.php';
    require_once POLYTRANS_PLUGIN_DIR . '/includes/postprocessing/providers/class-post-data-provider.php';
    require_once POLYTRANS_PLUGIN_DIR . '/includes/postprocessing/providers/class-meta-data-provider.php';

    $variable_manager = new PolyTrans_Variable_Manager();

    $providers = [
        new PolyTrans_Post_Data_Provider(),
        new PolyTrans_Meta_Data_Provider()
        // Add more providers as needed
    ];

    $rebuilt_context = $variable_manager->build_context($fresh_context, $providers);

    // Preserve workflow execution data (steps output, etc.)
    if (isset($context['steps'])) {
        $rebuilt_context['steps'] = $context['steps'];
    }
    if (isset($context['previous_steps'])) {
        $rebuilt_context['previous_steps'] = $context['previous_steps'];
    }

    return $rebuilt_context;
}

/**
 * Rebuild context from in-memory changes (test mode)
 */
private function rebuild_context_from_changes($context, $changes)
{
    // Apply all changes to context in memory
    foreach ($changes as $change) {
        $context = $this->apply_change_to_context($context, $change);
    }

    // NOW rebuild through providers (with updated in-memory "post" object)
    // Create virtual post object with changes applied
    $virtual_post = $this->create_virtual_post_from_context($context);

    // Rebuild context using Post Data Provider with virtual post
    $provider = new PolyTrans_Post_Data_Provider();
    $fresh_variables = $provider->format_post_data($virtual_post);

    // Merge with existing context
    $context = array_merge($context, $fresh_variables);

    // Update top-level aliases
    $context['title'] = $fresh_variables['title'] ?? $context['title'];
    $context['content'] = $fresh_variables['content'] ?? $context['content'];
    $context['excerpt'] = $fresh_variables['excerpt'] ?? $context['excerpt'];

    return $context;
}

/**
 * Create virtual WP_Post object from context (for test mode)
 */
private function create_virtual_post_from_context($context)
{
    // Create stdClass mimicking WP_Post
    $virtual_post = new stdClass();

    $virtual_post->ID = $context['translated']['id'] ?? $context['translated_post_id'] ?? 0;
    $virtual_post->post_title = $context['title'] ?? '';
    $virtual_post->post_content = $context['content'] ?? '';
    $virtual_post->post_excerpt = $context['excerpt'] ?? '';
    $virtual_post->post_status = $context['status'] ?? 'draft';
    $virtual_post->post_author = $context['author_id'] ?? 0;
    $virtual_post->post_date = $context['date'] ?? current_time('mysql');

    // Add more fields as needed

    return $virtual_post;
}
```

---

## 4. Implementation Plan

### Phase 0.1: Add Twig Dependency (Week 1, Days 1-2)

#### Step 1.1: Composer Setup

**File**: `plugins/polytrans/composer.json` (jeśli nie istnieje, create)

```json
{
    "name": "polytrans/polytrans",
    "description": "AI-powered translation plugin for WordPress",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4",
        "twig/twig": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "PolyTrans\\": "includes/"
        }
    }
}
```

**Installation**:
```bash
cd /home/jm/projects/trans-info/plugins/polytrans
composer install
```

**Update `.gitignore`**:
```
vendor/
cache/twig/
```

#### Step 1.2: Create Twig Engine Wrapper

**File**: `includes/templating/class-twig-template-engine.php`

Implement complete class as designed above.

#### Step 1.3: Update Variable Manager to Use Twig

**File**: `includes/postprocessing/class-variable-manager.php`

```php
public function interpolate_template($template, $context)
{
    if (!is_string($template)) {
        return $template;
    }

    // Initialize Twig if not done
    if (!class_exists('PolyTrans_Twig_Engine')) {
        require_once POLYTRANS_PLUGIN_DIR . '/includes/templating/class-twig-template-engine.php';
        PolyTrans_Twig_Engine::init();
    }

    try {
        // Use Twig for rendering (handles legacy syntax conversion)
        return PolyTrans_Twig_Engine::render($template, $context);
    } catch (Exception $e) {
        // Fallback to old regex method if Twig fails
        error_log('[PolyTrans] Twig rendering failed: ' . $e->getMessage());
        return $this->interpolate_template_legacy($template, $context);
    }
}

/**
 * Legacy interpolation method (fallback)
 */
private function interpolate_template_legacy($template, $context)
{
    // Existing implementation (lines 58-79)
    $pattern = '/\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}/';

    return preg_replace_callback($pattern, function ($matches) use ($context) {
        $variable_path = $matches[1];
        $value = $this->get_variable_value($variable_path, $context);

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return (string)$value;
    }, $template);
}
```

### Phase 0.2: Unified Variable Naming (Week 1, Days 3-5)

#### Step 2.1: Update Post Data Provider

**File**: `includes/postprocessing/providers/class-post-data-provider.php`

```php
public function get_variables($context)
{
    // Load posts
    $original_post = $this->load_post($context['original_post_id'] ?? null);
    $translated_post = $this->load_post($context['translated_post_id'] ?? null);

    // Format using new structure
    $variables = [];

    // Original post object
    if ($original_post) {
        $variables['original'] = $this->format_post_data($original_post);
    }

    // Translated post object
    if ($translated_post) {
        $variables['translated'] = $this->format_post_data($translated_post);

        // Top-level aliases (primary access method)
        $variables['title'] = $variables['translated']['title'];
        $variables['content'] = $variables['translated']['content'];
        $variables['excerpt'] = $variables['translated']['excerpt'];
        $variables['author'] = $variables['translated']['author'];
        $variables['date'] = $variables['translated']['date'];
        $variables['status'] = $variables['translated']['status'];
    }

    // Meta merged
    $variables['meta'] = array_merge(
        $variables['original']['meta'] ?? [],
        $variables['translated']['meta'] ?? []
    );

    // =============================================
    // DEPRECATED: Backward compatibility
    // =============================================
    $variables['post_title'] = $variables['title'] ?? '';
    $variables['post_content'] = $variables['content'] ?? '';
    $variables['post_excerpt'] = $variables['excerpt'] ?? '';
    $variables['original_post'] = $variables['original'] ?? [];
    $variables['translated_post'] = $variables['translated'] ?? [];
    $variables['original_meta'] = $variables['original']['meta'] ?? [];
    $variables['translated_meta'] = $variables['meta'] ?? [];

    return $variables;
}

private function format_post_data($post)
{
    if (!$post) {
        return null;
    }

    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'status' => $post->post_status,
        'author' => get_the_author_meta('display_name', $post->post_author),
        'author_id' => $post->post_author,
        'date' => $post->post_date,
        'modified' => $post->post_modified,
        'slug' => $post->post_name,
        'type' => $post->post_type,
        'parent_id' => $post->post_parent,
        'meta' => $this->get_post_meta($post->ID)
    ];
}
```

#### Step 2.2: Update Meta Data Provider

**File**: `includes/postprocessing/providers/class-meta-data-provider.php`

No changes needed - meta jest już w `meta.*` namespace przez Post Data Provider.

#### Step 2.3: Update Context Data Provider

**File**: `includes/postprocessing/providers/class-context-data-provider.php`

```php
public function get_variables($context)
{
    return [
        'source_lang' => $context['source_language'] ?? '',
        'target_lang' => $context['target_language'] ?? '',
        'workflow_name' => $context['workflow_name'] ?? '',
        'step_name' => $context['step_name'] ?? '',
        'trigger' => $context['trigger'] ?? '',

        // Site context
        'site' => [
            'name' => get_bloginfo('name'),
            'url' => get_bloginfo('url'),
            'language' => get_bloginfo('language')
        ]
    ];
}
```

### Phase 0.3: Context Refresh Logic (Week 2, Days 1-3)

#### Step 3.1: Implement rebuild_context_from_database

Already designed above in Decision #3. Implement in `class-workflow-output-processor.php`.

#### Step 3.2: Implement rebuild_context_from_changes

Already designed above. Implement in `class-workflow-output-processor.php`.

#### Step 3.3: Update process_step_outputs

Replace existing refresh logic with new rebuild logic (as designed above).

### Phase 0.4: Testing & Validation (Week 2, Days 4-5)

#### Test Cases

**Test 1: Variable Naming Consistency**
```php
// Create workflow with steps that change title
$workflow = [
    'steps' => [
        [
            'type' => 'ai_assistant',
            'system_prompt' => 'Change title to uppercase',
            'user_message' => 'Current title: {title}',  // Should work
            'output_actions' => ['update_post_title']
        ],
        [
            'type' => 'ai_assistant',
            'system_prompt' => 'Review this title',
            'user_message' => 'Title to review: {title}',  // Should show NEW title
        ]
    ]
];

// Execute in test mode
$result = execute_workflow($workflow, $context, true);

// Assert: Step 2 should see updated title
assert($result['steps'][1]['interpolated_user_message'] === 'Title to review: UPPERCASE TITLE');
```

**Test 2: Backward Compatibility**
```php
// Old templates should still work
$old_template = 'Post: {post_title}, Content: {post_content}';
$new_template = 'Post: {title}, Content: {content}';

$result_old = interpolate_template($old_template, $context);
$result_new = interpolate_template($new_template, $context);

// Both should produce same output
assert($result_old === $result_new);
```

**Test 3: Twig Advanced Features**
```twig
{% if title %}
  Title: {{ title|upper }}
{% else %}
  No title
{% endif %}

{% for step in steps %}
  Step {{ loop.index }}: {{ step.ai_response }}
{% endfor %}
```

**Test 4: Test Mode vs Production Mode**
```php
// Test mode: Should update variables without DB writes
$test_result = execute_workflow($workflow, $context, true);
$db_post = get_post($post_id);
assert($db_post->post_title !== 'CHANGED');  // DB not changed
assert($test_result['context']['title'] === 'CHANGED');  // Context changed

// Production mode: Should update both
$prod_result = execute_workflow($workflow, $context, false);
$db_post = get_post($post_id);
assert($db_post->post_title === 'CHANGED');  // DB changed
assert($prod_result['context']['title'] === 'CHANGED');  // Context changed
```

---

## 5. Migration Guide

### For Plugin Users

**No action required** - backward compatibility maintained!

Old templates will continue to work:
```
{post_title} → automatically mapped to {title}
{post_content} → automatically mapped to {content}
{original_post.title} → automatically mapped to {original.title}
```

### For Plugin Developers

**Recommended updates**:

1. **Update templates to use new convention**:
   ```diff
   - Current title: {post_title}
   + Current title: {title}

   - Original: {original_post.title}
   + Original: {original.title}
   ```

2. **Use Twig features** (optional but recommended):
   ```twig
   {# Conditionals #}
   {% if title %}
     {{ title }}
   {% else %}
     Untitled
   {% endif %}

   {# Filters #}
   {{ content|wp_excerpt(100) }}
   {{ date|wp_date('Y-m-d') }}
   ```

3. **Custom Twig Filters** (for advanced use cases):
   ```php
   add_filter('polytrans_twig_filters', function($filters) {
       $filters[] = new \Twig\TwigFilter('my_custom_filter', function($value) {
           return strtoupper($value);
       });
       return $filters;
   });
   ```

---

## 6. Documentation Updates

### Files to Create/Update:

1. **`TEMPLATING_GUIDE.md`** - Complete guide to templating system
   - Legacy `{variable}` syntax
   - Twig syntax (conditionals, loops, filters)
   - Available variables reference
   - Custom filters
   - Examples

2. **`VARIABLE_REFERENCE.md`** - Complete variable reference
   - All available variables by category
   - Deprecated variables with migration path
   - Context-specific variables

3. **`MIGRATION_GUIDE_PHASE_0.md`** - Migration guide
   - What changed
   - How to update existing templates
   - Backward compatibility notes

4. **Update `README.md`**:
   - Add section on templating system
   - Link to new docs

---

## 7. Rollout Plan

### Week 1: Twig Integration + Variable Naming

**Days 1-2**: Twig setup
- Add Composer dependency
- Create Twig Engine wrapper
- Update Variable Manager

**Days 3-5**: Variable naming refactor
- Update all providers (Post, Meta, Context)
- Add backward compatibility mappings
- Update tests

### Week 2: Context Refresh + Testing

**Days 1-3**: Context refresh logic
- Implement rebuild_context_from_database
- Implement rebuild_context_from_changes
- Update Output Processor

**Days 4-5**: Testing & documentation
- Run all test cases
- Write documentation
- Create migration guide

---

## 8. Success Criteria

✅ **Feature Complete**:
- [x] Twig integration working
- [x] Unified variable naming implemented
- [x] Context refresh logic fixed
- [x] Backward compatibility verified
- [x] All tests passing

✅ **Quality**:
- [x] No regressions in existing workflows
- [x] Test mode behaves like production (minus DB writes)
- [x] Variables always up-to-date across steps
- [x] Performance acceptable (Twig caching enabled)

✅ **Documentation**:
- [x] Templating guide complete
- [x] Variable reference complete
- [x] Migration guide available

---

## 9. Risks & Mitigations

### Risk #1: Twig Performance Overhead

**Mitigation**:
- Enable Twig caching (`'cache' => true`)
- Benchmark before/after
- Fallback to legacy method if needed

### Risk #2: Breaking Changes Despite Backward Compatibility

**Mitigation**:
- Extensive testing of existing workflows
- Beta release to internal users first
- Deprecation warnings (not errors) for old variable names

### Risk #3: Context Rebuild Performance

**Mitigation**:
- Only rebuild when changes are made (`if (!empty($changes))`)
- Cache provider results where possible
- Profile and optimize bottlenecks

---

## 10. Next Steps After Phase 0

Once Phase 0 is complete and stable:

1. **Phase 1: Assistants System** (as per original plan)
   - Database schema for assistants
   - Assistant Manager & Executor
   - Admin UI

2. **Future Enhancements**:
   - More Twig filters (WordPress-specific)
   - Variable registry UI (show available variables in editor)
   - Template validation & testing UI

---

**Status**: 📋 Plan Ready for Implementation
**Next Action**: Begin Week 1, Day 1 - Composer setup
