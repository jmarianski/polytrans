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

// Get token from command line first (needed for error logging)
$token = $argv[1] ?? '';

if (empty($token)) {
    // Before WordPress is loaded, use error_log
    error_log('[polytrans] No token provided');
    exit(1);
}

// Load WordPress first (needed for get_transient and LogsManager)
if (!defined('ABSPATH')) {
    // Find wp-load.php by going up from plugin directory
    $current_file = __FILE__;
    $plugin_dir = dirname(dirname($current_file)); // wp-content/plugins/polytrans
    $wp_content_dir = dirname($plugin_dir); // wp-content/plugins
    $wp_root = dirname(dirname($wp_content_dir)); // wp-content -> wp root
    
    $wp_load_path = $wp_root . '/wp-load.php';
    
    if (!file_exists($wp_load_path)) {
        error_log('[polytrans] Could not find wp-load.php');
        error_log('[polytrans] Tried: ' . $wp_load_path);
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

// Helper function to log errors to transient (for early error detection)
// This allows the main process to check for errors after 1 second
$log_to_transient = function($message, $context = []) use ($token) {
    if (function_exists('get_transient') && !empty($token)) {
        $error_transient_key = 'polytrans_bg_errors_' . $token;
        $existing_errors = get_transient($error_transient_key);
        if (!is_array($existing_errors)) {
            $existing_errors = [];
        }
        $existing_errors[] = [
            'timestamp' => time(),
            'message' => $message,
            'context' => $context
        ];
        set_transient($error_transient_key, $existing_errors, 300); // 5 minutes
    }
    error_log('[polytrans] ' . $message);
};

// Get data from transient
$data = get_transient('polytrans_bg_' . $token);

if (!$data) {
    // Log to transient for early error detection
    $log_to_transient("Could not retrieve data for token: " . $token, ['token' => $token]);
    // Also log to LogsManager if available
    if (class_exists('\PolyTrans\Core\LogsManager')) {
        \PolyTrans\Core\LogsManager::log("Could not retrieve data for token: " . $token, "error", ['token' => $token]);
    }
    exit(1);
}

// Extract arguments
$args = $data['args'] ?? [];
$action = $data['action'] ?? 'process-translation';

// Process task (all error handling is in BackgroundProcessor)
// Errors will be caught and logged by BackgroundProcessor::process_task()
try {
    \PolyTrans\Core\BackgroundProcessor::process_task($args, $action);
} catch (\Throwable $e) {
    // Log to transient for early error detection
    $log_to_transient(
        "BackgroundProcessor::process_task() failed: " . $e->getMessage(),
        [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    );
    // Also log to LogsManager
    if (class_exists('\PolyTrans\Core\LogsManager')) {
        \PolyTrans\Core\LogsManager::log(
            "BackgroundProcessor::process_task() failed: " . $e->getMessage(),
            "error",
            [
                'token' => $token,
                'action' => $action,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        );
    }
    throw $e;
}

// Clean up
delete_transient('polytrans_bg_' . $token);
