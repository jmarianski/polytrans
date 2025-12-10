<?php
// Test migration status
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

require_once('includes/postprocessing/managers/class-workflow-storage-manager.php');
require_once('includes/assistants/class-assistant-manager.php');
require_once('includes/assistants/class-assistant-executor.php');
require_once('includes/assistants/class-assistant-migration-manager.php');

echo "=== Migration Status ===\n\n";

$status = PolyTrans_Assistant_Migration_Manager::get_migration_status();
print_r($status);

echo "\n\n=== Workflows ===\n\n";

$storage = new PolyTrans_Workflow_Storage_Manager();
$workflows = $storage->get_all_workflows();

foreach ($workflows as $workflow) {
    echo "Workflow: {$workflow['name']} (ID: {$workflow['id']})\n";
    if (!empty($workflow['steps'])) {
        foreach ($workflow['steps'] as $i => $step) {
            echo "  Step {$i}: {$step['name']} (type: {$step['type']})\n";
        }
    }
    echo "\n";
}

echo "\n=== Managed Assistants ===\n\n";
$assistants = PolyTrans_Assistant_Manager::get_all_assistants();
echo "Total: " . count($assistants) . "\n";
foreach ($assistants as $assistant) {
    echo "- {$assistant['name']} (ID: {$assistant['id']}, Provider: {$assistant['provider']})\n";
}
