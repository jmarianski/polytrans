<?php
/**
 * Background Process Task Handler
 * 
 * Simple wrapper that loads WordPress and Bootstrap, then calls
 * BackgroundProcessor::process_task() with data from transient.
 * 
 * All error handling and logging is done in BackgroundProcessor.
 * 
 * @package PolyTrans
 */

// Load WordPress
if (!defined('ABSPATH')) {
    // Find wp-load.php by going up from plugin directory
    // process-task.php is in: wp-content/plugins/polytrans/includes/process-task.php
    // wp-load.php is in: wp-load.php (WordPress root)
    $current_file = __FILE__;
    $plugin_dir = dirname(dirname($current_file)); // wp-content/plugins/polytrans
    $wp_content_dir = dirname($plugin_dir); // wp-content/plugins
    $wp_root = dirname(dirname($wp_content_dir)); // wp-content -> wp root
    
    $wp_load_path = $wp_root . '/wp-load.php';
    
    if (!file_exists($wp_load_path)) {
        error_log('[polytrans] Could not find wp-load.php');
        error_log('[polytrans] Tried: ' . $wp_load_path);
        error_log('[polytrans] Current file: ' . $current_file);
        error_log('[polytrans] Plugin dir: ' . $plugin_dir);
        error_log('[polytrans] WP root: ' . $wp_root);
        exit(1);
    }
    
    require_once $wp_load_path;
}

// Define plugin directory if not already defined
if (!defined('POLYTRANS_PLUGIN_DIR')) {
    define('POLYTRANS_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
}

// Load Bootstrap
if (!class_exists('\PolyTrans\Bootstrap')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/Bootstrap.php';
}
\PolyTrans\Bootstrap::init();

// Get token from command line
$token = $argv[1] ?? '';

if (empty($token)) {
    error_log('[polytrans] No token provided');
    exit(1);
}

// Get data from transient
$data = get_transient('polytrans_bg_' . $token);

if (!$data) {
    error_log('[polytrans] Could not retrieve data for token: ' . $token);
    exit(1);
}

// Extract arguments
$args = $data['args'] ?? [];
$action = $data['action'] ?? 'process-translation';

// Process task (all error handling is in BackgroundProcessor)
\PolyTrans\Core\BackgroundProcessor::process_task($args, $action);

// Clean up
delete_transient('polytrans_bg_' . $token);
