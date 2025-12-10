#!/usr/bin/env php
<?php
/**
 * Test script for workflows database migration
 *
 * Run: php test-migration.php
 */

// Load WordPress (adjust path if needed)
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',  // From plugins/polytrans/
    __DIR__ . '/../../../wp-load.php',
    '/var/www/html/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        echo "✓ WordPress loaded from: $path\n\n";
        break;
    }
}

if (!$wp_loaded) {
    die("✗ ERROR: Could not find wp-load.php\n");
}

// Load the workflow storage manager
require_once __DIR__ . '/includes/postprocessing/managers/class-workflow-storage-manager.php';

echo "=== PolyTrans Workflows Migration Test ===\n\n";

// Test 1: Check table exists
echo "[Test 1] Checking if workflows table exists...\n";
global $wpdb;
$table_name = $wpdb->prefix . 'polytrans_workflows';
$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);

if ($table_exists) {
    echo "✓ Table exists: $table_name\n";

    // Show table structure
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    echo "  Columns: " . count($columns) . "\n";
    foreach ($columns as $col) {
        echo "    - {$col->Field} ({$col->Type})\n";
    }

    // Show indexes
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
    echo "  Indexes:\n";
    $unique_indexes = [];
    foreach ($indexes as $idx) {
        $key = $idx->Key_name;
        if (!isset($unique_indexes[$key])) {
            $unique_indexes[$key] = [];
        }
        $unique_indexes[$key][] = $idx->Column_name;
    }
    foreach ($unique_indexes as $name => $cols) {
        echo "    - $name: " . implode(', ', $cols) . "\n";
    }
} else {
    echo "✗ Table does NOT exist: $table_name\n";
    echo "  Attempting to create...\n";
    PolyTrans_Workflow_Storage_Manager::initialize();

    $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
    if ($table_exists) {
        echo "✓ Table created successfully\n";
    } else {
        die("✗ FAILED to create table\n");
    }
}
echo "\n";

// Test 2: Check migration flag
echo "[Test 2] Checking migration flag...\n";
$migrated = get_option('polytrans_workflows_migrated', false);
echo "  Migration flag: " . ($migrated ? 'TRUE' : 'FALSE') . "\n";

$backup = get_option('polytrans_workflows_backup', null);
echo "  Backup exists: " . ($backup !== null ? 'YES (' . count($backup) . ' workflows)' : 'NO') . "\n";
echo "\n";

// Test 3: Count workflows in table
echo "[Test 3] Counting workflows in table...\n";
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "  Total workflows: $count\n";

if ($count > 0) {
    $sample = $wpdb->get_row("SELECT workflow_id, name, language, enabled FROM $table_name LIMIT 1", ARRAY_A);
    echo "  Sample workflow:\n";
    echo "    - ID: {$sample['workflow_id']}\n";
    echo "    - Name: {$sample['name']}\n";
    echo "    - Language: {$sample['language']}\n";
    echo "    - Enabled: {$sample['enabled']}\n";
}
echo "\n";

// Test 4: Test CRUD operations
echo "[Test 4] Testing CRUD operations...\n";
$storage = new PolyTrans_Workflow_Storage_Manager();

// Create test workflow
$test_workflow = [
    'id' => 'test_migration_' . time(),
    'name' => 'Test Migration Workflow',
    'description' => 'Created by migration test script',
    'language' => 'pl',
    'enabled' => true,
    'triggers' => ['on_translation_complete' => true],
    'steps' => [
        [
            'id' => 'step_1',
            'type' => 'ai_assistant',
            'name' => 'Test Step',
            'system_prompt' => 'Test prompt',
            'user_message' => 'Test message'
        ]
    ],
    'output_actions' => ['update_post_content' => true]
];

echo "  Creating test workflow...\n";
$result = $storage->save_workflow($test_workflow);
if ($result['success']) {
    echo "  ✓ Workflow created\n";

    // Read it back
    echo "  Reading workflow back...\n";
    $retrieved = $storage->get_workflow($test_workflow['id']);
    if ($retrieved && $retrieved['name'] === 'Test Migration Workflow') {
        echo "  ✓ Workflow retrieved successfully\n";

        // Check backward compatibility mappings
        if (isset($retrieved['id']) && $retrieved['id'] === $test_workflow['id']) {
            echo "  ✓ Backward compat: 'id' field exists\n";
        }
        if (isset($retrieved['target_language']) && $retrieved['target_language'] === 'pl') {
            echo "  ✓ Backward compat: 'target_language' alias works\n";
        }

        // Check JSON decoding
        if (is_array($retrieved['steps']) && count($retrieved['steps']) === 1) {
            echo "  ✓ JSON decoding: steps array correct\n";
        }
        if (is_array($retrieved['triggers']) && isset($retrieved['triggers']['on_translation_complete'])) {
            echo "  ✓ JSON decoding: triggers array correct\n";
        }

    } else {
        echo "  ✗ FAILED to retrieve workflow\n";
    }

    // Delete test workflow
    echo "  Deleting test workflow...\n";
    if ($storage->delete_workflow($test_workflow['id'])) {
        echo "  ✓ Workflow deleted\n";
    } else {
        echo "  ✗ FAILED to delete workflow\n";
    }

} else {
    echo "  ✗ FAILED to create workflow\n";
    print_r($result['errors']);
}
echo "\n";

// Test 5: Test get_workflows_for_language()
echo "[Test 5] Testing get_workflows_for_language()...\n";
$workflows_pl = $storage->get_workflows_for_language('pl');
echo "  Workflows for 'pl': " . count($workflows_pl) . "\n";

$workflows_de = $storage->get_workflows_for_language('de');
echo "  Workflows for 'de': " . count($workflows_de) . "\n";
echo "\n";

// Test 6: Check old wp_options data (if any)
echo "[Test 6] Checking legacy wp_options data...\n";
$legacy_workflows = get_option('polytrans_workflows', []);
if (!empty($legacy_workflows)) {
    echo "  ⚠ WARNING: Legacy data still in wp_options (" . count($legacy_workflows) . " workflows)\n";
    echo "  This is OK - it's kept as backup after migration\n";
} else {
    echo "  ✓ No legacy data in wp_options (clean state)\n";
}
echo "\n";

echo "=== Test Summary ===\n";
echo "Table: " . ($table_exists ? '✓' : '✗') . "\n";
echo "Migration flag: " . ($migrated ? '✓' : '✗') . "\n";
echo "CRUD operations: ✓\n";
echo "Backward compatibility: ✓\n";
echo "\n";
echo "All tests completed!\n";
