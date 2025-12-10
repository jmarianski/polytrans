<?php
/**
 * Install Translation Assistant EN→PL
 * 
 * Run this file once to install the translation assistant.
 * Access via: http://localhost:8080/wp-content/plugins/polytrans-main/install-translation-assistant.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

global $wpdb;

// Read SQL file
$sql_file = __DIR__ . '/examples/translation-assistant-en-pl.sql';
if (!file_exists($sql_file)) {
    die('SQL file not found: ' . $sql_file);
}

$sql = file_get_contents($sql_file);

// Execute SQL
$result = $wpdb->query($sql);

if ($result === false) {
    echo '<h1>Error!</h1>';
    echo '<p>Failed to create assistant.</p>';
    echo '<p><strong>Error:</strong> ' . $wpdb->last_error . '</p>';
} else {
    $assistant_id = $wpdb->insert_id;
    echo '<h1>Success! ✓</h1>';
    echo '<p>Translation Assistant EN→PL has been created.</p>';
    echo '<p><strong>Assistant ID:</strong> ' . $assistant_id . '</p>';
    echo '<p><a href="/wp-admin/admin.php?page=polytrans-assistants">View Assistants</a></p>';
}

// Show all assistants
echo '<hr>';
echo '<h2>All Assistants:</h2>';
$assistants = $wpdb->get_results("SELECT id, name, provider, status FROM {$wpdb->prefix}polytrans_assistants ORDER BY id DESC");
if ($assistants) {
    echo '<ul>';
    foreach ($assistants as $assistant) {
        echo '<li><strong>' . esc_html($assistant->name) . '</strong> (ID: ' . $assistant->id . ', Provider: ' . $assistant->provider . ', Status: ' . $assistant->status . ')</li>';
    }
    echo '</ul>';
} else {
    echo '<p>No assistants found.</p>';
}

echo '<hr>';
echo '<p><em>You can delete this file after installation.</em></p>';

