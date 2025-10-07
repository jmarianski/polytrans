# PolyTrans Architecture

## Directory Structure

```
polytrans/
├── polytrans.php              # Main plugin file
├── includes/
│   ├── class-polytrans.php   # Core plugin class
│   ├── core/                  # WordPress integration
│   │   ├── class-background-processor.php  # Async processing
│   │   ├── class-translation-meta-box.php  # Post editor UI
│   │   ├── class-translation-settings.php  # Settings management
│   │   └── class-logs-manager.php          # Logging system
│   ├── providers/             # Translation providers
│   │   ├── interface-translation-provider.php
│   │   ├── google/            # Google Translate
│   │   └── openai/            # OpenAI provider
│   ├── receiver/              # Translation processing
│   │   ├── class-translation-coordinator.php
│   │   └── managers/          # Specialized managers
│   ├── postprocessing/        # Workflow system
│   │   ├── class-workflow-manager.php
│   │   ├── class-workflow-executor.php
│   │   └── steps/             # Workflow step types
│   ├── menu/                  # Admin menus
│   └── scheduler/             # Translation scheduling
├── assets/
│   ├── css/                   # Stylesheets
│   └── js/                    # JavaScript
└── tests/                     # PHPUnit tests
```

## Core Components

### Translation Flow

1. **Scheduler** - User requests translation via meta box
2. **Background Processor** - Queues translation task
3. **Provider** - Sends content to translation service (Google/OpenAI)
4. **Receiver** - Processes translated content
5. **Managers** - Handle post creation, metadata, media, taxonomies
6. **Workflow** - Runs post-processing steps (optional)
7. **Notifications** - Notifies reviewers/users

### Provider System

**Interface:** `PolyTrans_Translation_Provider_Interface`

Providers must implement:
- `translate($content, $source_lang, $target_lang)`
- `get_supported_languages()`

**Current Providers:**
- Google Translate (free, basic)
- OpenAI (paid, high quality, context-aware)

### Workflow System

**Step Types:**
- `ai_assistant` - AI processing (OpenAI)
- `find_replace` - Text replacement
- `regex_replace` - Regex operations

**Workflow Execution:**
1. Load workflow configuration
2. Initialize context with post data
3. Execute steps sequentially
4. Each step can modify context
5. Output actions update post
6. Log results

**Output Actions:**
- `update_post_content`
- `update_post_meta`
- `update_post_excerpt`

### Background Processing

Uses `WP_Background_Process` pattern:
- Queues tasks in wp_options
- Processes asynchronously via cron or loopback
- Handles failures with retry logic
- Prevents timeouts for long operations

### Receiver Architecture

**Coordinator:** Routes translation to appropriate managers

**Managers:**
- **Request Validator** - Validates incoming data
- **Post Creator** - Creates translated posts
- **Metadata Manager** - Copies/translates metadata
- **Media Manager** - Handles featured images, duplicates media
- **Taxonomy Manager** - Maps categories/tags
- **Language Manager** - Sets Polylang relationships
- **Status Manager** - Updates translation status
- **Notification Manager** - Sends emails

Each manager has single responsibility, can be extended independently.

## Key Classes

### `PolyTrans_Background_Processor`
- Handles async translation processing
- Spawns background processes
- Manages task queue
- Retry logic for failures

### `PolyTrans_Translation_Coordinator`
- Receives completed translations
- Orchestrates managers
- Error handling and rollback
- Status tracking

### `PolyTrans_Workflow_Manager`
- CRUD for workflows
- Workflow configuration storage
- Validation

### `PolyTrans_Workflow_Executor`
- Executes workflow steps
- Context management
- Output processing
- Error handling

## Database

### Custom Tables

**polytrans_logs:**
- Translation logs
- Workflow execution logs
- Error tracking

### Post Meta

- `_polytrans_translation_status` - Translation state
- `_polytrans_translation_type` - human/machine/original
- `_polytrans_source_post_id` - Link to original
- `_polytrans_workflow_{id}_status` - Workflow execution state

## Hooks

### Actions
```php
do_action('polytrans_before_translate', $post_id, $lang);
do_action('polytrans_translation_complete', $post_id, $lang);
do_action('polytrans_workflow_complete', $post_id, $workflow_id);
```

### Filters
```php
apply_filters('polytrans_pre_translate', $content, $lang);
apply_filters('polytrans_post_translate', $content, $lang);
apply_filters('polytrans_workflow_context', $context, $workflow);
```

## Extension Points

### Adding a Provider

```php
class Custom_Provider implements PolyTrans_Translation_Provider_Interface {
    public function translate($content, $source, $target) {
        // Implementation
    }
    
    public function get_supported_languages() {
        return ['en', 'es', 'fr'];
    }
}

add_filter('polytrans_register_providers', function($providers) {
    $providers['custom'] = new Custom_Provider();
    return $providers;
});
```

### Adding a Workflow Step

```php
add_filter('polytrans_workflow_steps', function($steps) {
    $steps['custom_step'] = [
        'execute' => function($context, $config) {
            // Process context
            return $context;
        }
    ];
    return $steps;
});
```

### Modifying Translation

```php
add_filter('polytrans_pre_translate', function($content, $lang) {
    // Modify before translation
    return $content;
}, 10, 2);

add_filter('polytrans_post_translate', function($translated, $lang) {
    // Modify after translation
    return $translated;
}, 10, 2);
```

## Performance Considerations

- Background processing prevents timeout
- Concurrent translation limit (default: 3)
- Caching for provider responses
- Batch operations for bulk translations
- Polylang integration for efficient language queries

## Security

- Nonce verification for all forms
- Capability checks (`manage_options`)
- Input sanitization
- Output escaping
- API authentication (bearer tokens)
- Rate limiting on API endpoints
