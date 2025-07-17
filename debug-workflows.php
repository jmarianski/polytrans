<?php

/**
 * Debug script to check how manual_only is stored in workflows
 */

// Include WordPress
require_once(dirname(__FILE__) . '/../../../../wp-config.php');

// Include our workflow manager
require_once(dirname(__FILE__) . '/includes/postprocessing/managers/class-workflow-storage-manager.php');

$storage_manager = new PolyTrans_Workflow_Storage_Manager();
$workflows = $storage_manager->get_all_workflows();

echo "<h1>Debug: Workflow Storage</h1>";
echo "<h2>All Workflows:</h2>";

foreach ($workflows as $workflow) {
    echo "<h3>Workflow: " . htmlspecialchars($workflow['name'] ?? 'Unknown') . "</h3>";
    echo "<strong>ID:</strong> " . htmlspecialchars($workflow['id'] ?? 'No ID') . "<br>";
    echo "<strong>Enabled:</strong> " . ($workflow['enabled'] ? 'Yes' : 'No') . "<br>";

    if (isset($workflow['triggers'])) {
        echo "<strong>Triggers:</strong><br>";
        echo "<ul>";
        echo "<li>on_translation_complete: " . ($workflow['triggers']['on_translation_complete'] ?? 'not set') . "</li>";
        echo "<li>manual_only: " . ($workflow['triggers']['manual_only'] ?? 'not set') . "</li>";
        echo "</ul>";
        echo "<strong>Raw triggers data:</strong><br>";
        echo "<pre>" . htmlspecialchars(print_r($workflow['triggers'], true)) . "</pre>";
    } else {
        echo "<strong>No triggers data found</strong><br>";
    }

    echo "<hr>";
}

// Also check the raw option value
echo "<h2>Raw Option Value:</h2>";
$raw_option = get_option('polytrans_workflows', []);
echo "<pre>" . htmlspecialchars(print_r($raw_option, true)) . "</pre>";
