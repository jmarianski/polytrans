# Phase 0 Extended: Workflows Migration to Database Table

**Addition to Phase 0**: Before starting Twig integration, migrate workflows from wp_options to dedicated table.

---

## Rationale

**Current Problems**:
- Workflows stored in `wp_options` as serialized array
- Poor performance with many workflows
- No indexing, no efficient queries
- Difficult to track relationships (which assistants used where)
- Options table bloat

**Solution**:
- Dedicated `wp_polytrans_workflows` table
- Each workflow = one row
- Steps stored as JSON in `steps` column
- Easy querying, indexing, relationships

---

## Database Schema

### Table: `wp_polytrans_workflows`

```sql
CREATE TABLE wp_polytrans_workflows (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  workflow_id varchar(255) NOT NULL UNIQUE,  -- e.g., "workflow_abc123"
  name varchar(255) NOT NULL,
  description text,
  language varchar(10) NOT NULL,
  enabled tinyint(1) DEFAULT 1,

  -- Triggers
  triggers text NOT NULL,  -- JSON: {on_translation_complete: true, manual_only: false, conditions: {...}}

  -- Steps (core workflow logic)
  steps longtext NOT NULL,  -- JSON: [{id, type, name, system_prompt, user_message, ...}, ...]

  -- Output Actions
  output_actions text NOT NULL,  -- JSON: {update_post_content: true, update_post_title: false, ...}

  -- Attribution
  attribution_user_id bigint(20) unsigned,

  -- Metadata
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  created_by bigint(20) unsigned,

  PRIMARY KEY (id),
  UNIQUE KEY workflow_id (workflow_id),
  KEY language (language),
  KEY enabled (enabled),
  KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Key Design Decisions:

1. **`workflow_id`**: String identifier (e.g., "workflow_abc123") for backward compatibility with existing code
2. **`id`**: Numeric auto-increment for database efficiency (foreign keys, joins)
3. **JSON columns**: `triggers`, `steps`, `output_actions` stored as JSON for flexibility
4. **Indexes**: On `workflow_id` (unique), `language`, `enabled`, `name` for common queries

---

## Migration Strategy

### Migration Scenarios

#### Scenario 1: Fresh Install (No wp_options data, no table)
- Create empty `wp_polytrans_workflows` table
- Nothing to migrate

#### Scenario 2: Existing Plugin (wp_options data exists, no table)
- Create `wp_polytrans_workflows` table
- Load workflows from `get_option('polytrans_workflows')`
- Insert each workflow into table
- Keep wp_options data for safety (can be cleaned manually later)
- Set flag: `update_option('polytrans_workflows_migrated', true)`

#### Scenario 3: Table Exists, wp_options Data Exists
- Check flag: `get_option('polytrans_workflows_migrated')`
- If flag = false: Migrate from wp_options to table (Scenario 2)
- If flag = true: Table is source of truth, ignore wp_options

#### Scenario 4: Table Exists, No wp_options Data
- Table is source of truth
- Normal operation

### Migration Code

**File**: `includes/postprocessing/managers/class-workflow-storage-manager.php`

```php
<?php

class PolyTrans_Workflow_Storage_Manager
{
    const TABLE_NAME = 'wp_polytrans_workflows';
    const LEGACY_OPTION_NAME = 'polytrans_workflows';
    const MIGRATION_FLAG = 'polytrans_workflows_migrated';

    /**
     * Initialize - Create table and migrate if needed
     * Called on plugin activation and admin_init
     */
    public static function initialize()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Check if table exists
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);

        if (!$table_exists) {
            // Create table
            self::create_workflows_table();

            // Migrate from wp_options if data exists
            $legacy_data = get_option(self::LEGACY_OPTION_NAME, []);
            if (!empty($legacy_data)) {
                self::migrate_from_options($legacy_data);
            }

            return;
        }

        // Table exists - check if we need to migrate
        $migrated = get_option(self::MIGRATION_FLAG, false);

        if (!$migrated) {
            $legacy_data = get_option(self::LEGACY_OPTION_NAME, []);
            if (!empty($legacy_data)) {
                self::migrate_from_options($legacy_data);
            } else {
                // No data to migrate, just set flag
                update_option(self::MIGRATION_FLAG, true);
            }
        }
    }

    /**
     * Create workflows table
     */
    private static function create_workflows_table()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workflow_id varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            language varchar(10) NOT NULL,
            enabled tinyint(1) DEFAULT 1,

            triggers text NOT NULL,
            steps longtext NOT NULL,
            output_actions text NOT NULL,

            attribution_user_id bigint(20) unsigned,

            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by bigint(20) unsigned,

            PRIMARY KEY (id),
            UNIQUE KEY workflow_id (workflow_id),
            KEY language (language),
            KEY enabled (enabled),
            KEY name (name)
        ) $charset_collate;";

        dbDelta($sql);

        error_log("[PolyTrans] Created workflows table: $table_name");
    }

    /**
     * Migrate workflows from wp_options to database table
     */
    private static function migrate_from_options($legacy_workflows)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $migrated_count = 0;
        $errors = [];

        foreach ($legacy_workflows as $workflow) {
            try {
                // Prepare data for insertion
                $data = [
                    'workflow_id' => $workflow['id'] ?? 'workflow_' . uniqid(),
                    'name' => $workflow['name'] ?? 'Unnamed Workflow',
                    'description' => $workflow['description'] ?? '',
                    'language' => $workflow['language'] ?? $workflow['target_language'] ?? '',
                    'enabled' => isset($workflow['enabled']) ? (int)$workflow['enabled'] : 1,

                    // JSON encode complex fields
                    'triggers' => wp_json_encode($workflow['triggers'] ?? []),
                    'steps' => wp_json_encode($workflow['steps'] ?? []),
                    'output_actions' => wp_json_encode($workflow['output_actions'] ?? []),

                    'attribution_user_id' => $workflow['attribution_user'] ?? null,

                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'created_by' => get_current_user_id()
                ];

                // Insert into table
                $result = $wpdb->insert($table_name, $data);

                if ($result) {
                    $migrated_count++;
                } else {
                    $errors[] = "Failed to migrate workflow: {$data['workflow_id']} - " . $wpdb->last_error;
                }

            } catch (\Exception $e) {
                $errors[] = "Exception migrating workflow: " . $e->getMessage();
            }
        }

        // Set migration flag
        update_option(self::MIGRATION_FLAG, true);

        // Log results
        error_log("[PolyTrans] Workflow migration complete: $migrated_count workflows migrated");
        if (!empty($errors)) {
            error_log("[PolyTrans] Migration errors: " . implode("; ", $errors));
        }

        // Optionally: Backup old data before clearing (safety)
        update_option(self::LEGACY_OPTION_NAME . '_backup', $legacy_workflows);

        return [
            'success' => empty($errors),
            'migrated' => $migrated_count,
            'errors' => $errors
        ];
    }

    /**
     * Get all workflows
     */
    public function get_all_workflows()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC", ARRAY_A);

        if (!$results) {
            return [];
        }

        // Decode JSON fields
        $workflows = [];
        foreach ($results as $row) {
            $workflows[] = self::hydrate_workflow($row);
        }

        return $workflows;
    }

    /**
     * Get workflows for specific language
     */
    public function get_workflows_for_language($language)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE language = %s ORDER BY name ASC", $language),
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        $workflows = [];
        foreach ($results as $row) {
            $workflows[] = self::hydrate_workflow($row);
        }

        return $workflows;
    }

    /**
     * Get single workflow by workflow_id
     */
    public function get_workflow($workflow_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE workflow_id = %s", $workflow_id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return self::hydrate_workflow($row);
    }

    /**
     * Save workflow (insert or update)
     */
    public function save_workflow($workflow)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Validate
        $validation = $this->validate_workflow($workflow);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        $workflow_id = $workflow['id'];

        // Check if exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table_name WHERE workflow_id = %s", $workflow_id)
        );

        // Prepare data
        $data = [
            'workflow_id' => $workflow_id,
            'name' => $workflow['name'] ?? 'Unnamed Workflow',
            'description' => $workflow['description'] ?? '',
            'language' => $workflow['language'] ?? $workflow['target_language'] ?? '',
            'enabled' => isset($workflow['enabled']) ? (int)$workflow['enabled'] : 1,

            'triggers' => wp_json_encode($workflow['triggers'] ?? []),
            'steps' => wp_json_encode($workflow['steps'] ?? []),
            'output_actions' => wp_json_encode($workflow['output_actions'] ?? []),

            'attribution_user_id' => $workflow['attribution_user'] ?? null,
            'updated_at' => current_time('mysql')
        ];

        if ($exists) {
            // Update
            $result = $wpdb->update(
                $table_name,
                $data,
                ['workflow_id' => $workflow_id]
            );

            return ['success' => $result !== false, 'workflow_id' => $workflow_id];
        } else {
            // Insert
            $data['created_at'] = current_time('mysql');
            $data['created_by'] = get_current_user_id();

            $result = $wpdb->insert($table_name, $data);

            return ['success' => $result !== false, 'workflow_id' => $workflow_id];
        }
    }

    /**
     * Delete workflow
     */
    public function delete_workflow($workflow_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->delete($table_name, ['workflow_id' => $workflow_id]);

        return $result !== false;
    }

    /**
     * Hydrate workflow from database row (decode JSON)
     */
    private static function hydrate_workflow($row)
    {
        return [
            'id' => $row['workflow_id'],  // Map workflow_id to 'id' for backward compatibility
            'name' => $row['name'],
            'description' => $row['description'],
            'language' => $row['language'],
            'target_language' => $row['language'],  // Alias for backward compatibility
            'enabled' => (bool)$row['enabled'],

            'triggers' => json_decode($row['triggers'], true) ?: [],
            'steps' => json_decode($row['steps'], true) ?: [],
            'output_actions' => json_decode($row['output_actions'], true) ?: [],

            'attribution_user' => $row['attribution_user_id'],

            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'created_by' => $row['created_by']
        ];
    }

    /**
     * Validate workflow structure
     */
    private function validate_workflow($workflow)
    {
        $errors = [];

        if (empty($workflow['id'])) {
            $errors[] = 'Workflow ID is required';
        }

        if (empty($workflow['name'])) {
            $errors[] = 'Workflow name is required';
        }

        if (empty($workflow['language']) && empty($workflow['target_language'])) {
            $errors[] = 'Workflow language is required';
        }

        if (!isset($workflow['steps']) || !is_array($workflow['steps'])) {
            $errors[] = 'Workflow must have steps array';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get workflows using a specific assistant
     * (for Phase 1 - assistants system)
     */
    public function get_workflows_using_assistant($assistant_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Search for assistant_id in steps JSON
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE steps LIKE %s",
                '%"assistant_id":' . $assistant_id . '%'
            ),
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        $workflows = [];
        foreach ($results as $row) {
            $workflow = self::hydrate_workflow($row);

            // Verify assistant is actually used (not just substring match)
            foreach ($workflow['steps'] as $step) {
                if (isset($step['assistant_id']) && $step['assistant_id'] == $assistant_id) {
                    $workflows[] = $workflow;
                    break;
                }
            }
        }

        return $workflows;
    }
}
```

---

## Plugin Activation Hook

**File**: `polytrans.php` (main plugin file)

```php
/**
 * Plugin activation hook
 */
function polytrans_activate() {
    // Initialize workflows table (will migrate if needed)
    require_once plugin_dir_path(__FILE__) . 'includes/postprocessing/managers/class-workflow-storage-manager.php';
    PolyTrans_Workflow_Storage_Manager::initialize();

    // Initialize assistants table (Phase 1)
    require_once plugin_dir_path(__FILE__) . 'includes/assistants/class-assistant-manager.php';
    PolyTrans_Assistant_Manager::create_assistants_table();

    // Initialize logs table (existing)
    require_once plugin_dir_path(__FILE__) . 'includes/core/class-logs-manager.php';
    PolyTrans_Logs_Manager::create_logs_table();
}
register_activation_hook(__FILE__, 'polytrans_activate');

/**
 * On admin_init, check tables (in case activation hook didn't run)
 */
add_action('admin_init', function() {
    require_once plugin_dir_path(__FILE__) . 'includes/postprocessing/managers/class-workflow-storage-manager.php';
    PolyTrans_Workflow_Storage_Manager::initialize();
});
```

---

## Migration from Workflow Steps to System Assistants (Phase 1)

When we implement assistants in Phase 1, we'll add a migration tool:

**Tool**: Admin page "Migrate Workflows to System Assistants"

**Logic**:
```php
foreach ($workflows as $workflow) {
    foreach ($workflow['steps'] as $step_index => $step) {
        if ($step['type'] !== 'ai_assistant') continue;

        // Skip if already using system assistant
        if (isset($step['assistant_id'])) continue;

        // Skip if no prompts (invalid)
        if (empty($step['system_prompt'])) continue;

        // Create assistant from step config
        $assistant_data = [
            'name' => sprintf('%s - Step %d: %s', $workflow['name'], $step_index + 1, $step['name']),
            'description' => sprintf('Migrated from workflow "%s"', $workflow['name']),
            'provider' => 'openai',
            'status' => 'active',

            'system_prompt' => $step['system_prompt'],
            'user_message_template' => $step['user_message'] ?? '',

            'api_parameters' => wp_json_encode([
                'model' => $step['model'] ?? 'gpt-4o-mini',
                'temperature' => $step['temperature'] ?? 0.7,
                'max_tokens' => $step['max_tokens'] ?? null,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0
            ]),

            'expected_format' => $step['expected_format'] ?? 'text',
            'output_variables' => wp_json_encode($step['output_variables'] ?? [])
        ];

        $assistant_id = PolyTrans_Assistant_Manager::create_assistant($assistant_data);

        // Update workflow step to reference new assistant
        $step['assistant_id'] = $assistant_id;

        // Optionally: Keep legacy fields for rollback
        // Or remove them: unset($step['system_prompt'], $step['user_message']);
    }

    // Save updated workflow
    $workflow_storage->save_workflow($workflow);
}
```

---

## Testing the Migration

### Test Case 1: Fresh Install
```php
// Deactivate/reactivate plugin
// Expected: Empty workflows table created, no errors
```

### Test Case 2: Existing Plugin with wp_options Data
```php
// Setup: Add workflows to wp_options
$legacy_workflows = [
    [
        'id' => 'workflow_test_1',
        'name' => 'Test Workflow',
        'language' => 'pl',
        'steps' => [
            ['type' => 'ai_assistant', 'system_prompt' => 'Test', 'user_message' => 'Test']
        ],
        'triggers' => ['on_translation_complete' => true],
        'output_actions' => ['update_post_content' => true]
    ]
];
update_option('polytrans_workflows', $legacy_workflows);

// Deactivate/reactivate plugin
// Expected: Workflows migrated to table, flag set, backup created
```

### Test Case 3: Verify Data Integrity After Migration
```php
$storage = new PolyTrans_Workflow_Storage_Manager();
$workflows = $storage->get_all_workflows();

// Assert: Count matches, structure correct, JSON decoded properly
assert(count($workflows) === count($legacy_workflows));
assert($workflows[0]['steps'][0]['system_prompt'] === 'Test');
```

### Test Case 4: Table Exists, wp_options Exists, Not Migrated
```php
// Setup: Create table, add wp_options data, set flag = false
// Trigger: admin_init hook
// Expected: Migration runs, data inserted
```

---

## Rollback Plan

If migration fails:

1. **Table remains** but flag not set (`polytrans_workflows_migrated = false`)
2. **Backup exists** in `polytrans_workflows_backup` option
3. **Manual rollback**:
   ```php
   // In WP Admin > Tools > Site Health > Info > PolyTrans
   $backup = get_option('polytrans_workflows_backup');
   update_option('polytrans_workflows', $backup);
   delete_option('polytrans_workflows_migrated');

   // Drop table (optional)
   global $wpdb;
   $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}polytrans_workflows");
   ```

---

## Benefits of This Approach

### Performance
- ✅ Indexed queries (by language, enabled status, name)
- ✅ No serialization/deserialization overhead
- ✅ Efficient filtering and searching

### Scalability
- ✅ Can handle hundreds of workflows
- ✅ No wp_options table bloat
- ✅ Better for large sites

### Relationships (Phase 1)
- ✅ Easy to find workflows using specific assistant:
  ```sql
  SELECT * FROM wp_polytrans_workflows
  WHERE steps LIKE '%"assistant_id":123%'
  ```
- ✅ Easy to implement workflow versioning (future)
- ✅ Easy to add workflow execution history (future)

### Developer Experience
- ✅ SQL queries instead of array filtering
- ✅ Better error handling
- ✅ Easier debugging (direct DB access)

---

## Updated Timeline

### Phase 0.0: Workflows Migration (NEW - 2 days)
- **Day 1**: Create table schema, write migration logic
- **Day 2**: Testing migration scenarios, update activation hook

### Phase 0.1: Twig Integration (3 days)
- Same as before

### Phase 0.2: Variable Naming (3 days)
- Same as before

### Phase 0.3: Context Refresh (3 days)
- Same as before

### Phase 0.4: Testing (2 days)
- Same as before

**Total Phase 0**: 13 days (~2.5 weeks)

---

## Next Steps

1. ✅ Implement `create_workflows_table()`
2. ✅ Implement `migrate_from_options()`
3. ✅ Update all CRUD methods to use database
4. ✅ Update plugin activation hook
5. ✅ Test migration scenarios
6. ✅ Proceed with Twig integration (Phase 0.1)

---

**Status**: 📋 Extension Ready for Implementation
**Priority**: High - Do this BEFORE Twig integration
**Risk**: Low - Migration has backup, table creation is standard WordPress pattern
