<?php
/**
 * Background Process Task Handler
 * 
 * This file is executed as a separate PHP process to handle background tasks.
 * It loads WordPress and calls BackgroundProcessor::process_task() with the
 * provided arguments.
 * 
 * @package PolyTrans
 */

/**
 * Log to file and LogsManager (since stderr doesn't work in background processes)
 */
function polytrans_bg_log($message) {
    $token = $GLOBALS['polytrans_bg_token'] ?? '';
    
    // Try to log via LogsManager first (if Bootstrap is loaded)
    if (class_exists('\PolyTrans\Core\LogsManager')) {
        try {
            \PolyTrans\Core\LogsManager::log($message, 'info', ['source' => 'background_process', 'token' => $token]);
        } catch (\Exception $e) {
            // Fallback to file if LogsManager fails
        }
    }
    
    // Also write to file for direct access
    if (!empty($token)) {
        // Try to get uploads directory
        $log_file = null;
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/polytrans-bg-' . $token . '.log';
        } else {
            // Fallback: try to find uploads directory
            $current_file = __FILE__;
            $plugin_dir = dirname(dirname($current_file));
            $wp_content_dir = dirname($plugin_dir);
            $log_file = $wp_content_dir . '/uploads/polytrans-bg-' . $token . '.log';
        }
        
        if ($log_file && is_writable(dirname($log_file))) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    // Also try error_log as final fallback
    error_log('[polytrans] ' . $message);
}

// Prevent direct access
if (!defined('ABSPATH')) {
    // Try to load WordPress if ABSPATH is not defined
    // This file can be called directly from command line
    // Find wp-load.php by going up from plugin directory
    
    // Current file: wp-content/plugins/polytrans/includes/process-task.php
    // We need: wp-load.php (in WordPress root)
    
    $current_file = __FILE__;
    $plugin_dir = dirname(dirname($current_file)); // wp-content/plugins/polytrans
    $wp_content_dir = dirname($plugin_dir); // wp-content
    $wp_root = dirname($wp_content_dir); // WordPress root
    
    // Try multiple common paths
    $possible_paths = [
        $wp_root . '/wp-load.php', // WordPress root
        dirname($wp_content_dir) . '/wp-load.php', // Alternative root
        $wp_content_dir . '/../wp-load.php', // Relative from wp-content
    ];
    
    // Also try to find it by searching up directories
    $search_dir = $plugin_dir;
    for ($i = 0; $i < 5; $i++) {
        $test_path = $search_dir . '/wp-load.php';
        if (file_exists($test_path)) {
            $possible_paths[] = $test_path;
            break;
        }
        $search_dir = dirname($search_dir);
        if ($search_dir === '/' || $search_dir === $search_dir . '/..') {
            break; // Reached filesystem root
        }
    }
    
    $wp_loaded = false;
    $wp_load_path = null;
    foreach ($possible_paths as $wp_load_path) {
        if (file_exists($wp_load_path)) {
            require_once $wp_load_path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        // Can't use polytrans_bg_log() yet, use error_log
        error_log('[polytrans] Background process failed: Could not find wp-load.php');
        error_log('[polytrans] Current file: ' . $current_file);
        error_log('[polytrans] Plugin dir: ' . $plugin_dir);
        error_log('[polytrans] WP content dir: ' . $wp_content_dir);
        error_log('[polytrans] WP root: ' . $wp_root);
        error_log('[polytrans] Tried paths:');
        foreach ($possible_paths as $path) {
            error_log('[polytrans]   - ' . $path . ' ' . (file_exists($path) ? '(EXISTS)' : '(NOT FOUND)'));
        }
        exit(1);
    }
}

// Initialize PolyTrans Bootstrap to ensure all classes are available
if (!defined('POLYTRANS_PLUGIN_DIR')) {
    // Try to find plugin directory
    $current_file = __FILE__;
    $plugin_dir = dirname(dirname($current_file));
    define('POLYTRANS_PLUGIN_DIR', $plugin_dir . '/');
}

// Load Bootstrap if not already loaded
if (!class_exists('\PolyTrans\Bootstrap')) {
    $bootstrap_file = POLYTRANS_PLUGIN_DIR . 'includes/Bootstrap.php';
    if (file_exists($bootstrap_file)) {
        require_once $bootstrap_file;
        \PolyTrans\Bootstrap::init();
    } else {
        error_log('[polytrans] Bootstrap file not found at: ' . $bootstrap_file);
        exit(1);
    }
} elseif (!\PolyTrans\Bootstrap::isInitialized()) {
    // Bootstrap class exists but not initialized
    \PolyTrans\Bootstrap::init();
}

// Get token from command line argument or query string
$token = $argv[1] ?? $_GET['token'] ?? '';

// Set global token for logging function
$GLOBALS['polytrans_bg_token'] = $token;

if (empty($token)) {
    error_log('[polytrans] Background process failed: No token provided');
    error_log('[polytrans] Usage: php process-task.php <token>');
    error_log('[polytrans] argv: ' . print_r($argv ?? [], true));
    exit(1);
}

polytrans_bg_log('Starting background process with token: ' . $token);

try {
    // Get the data from transient
    $data = get_transient('polytrans_bg_' . $token);
    
    if (!$data) {
        polytrans_bg_log('Background process failed: Could not retrieve data for token ' . $token);
        exit(1);
    }
    
    // Extract arguments
    $args = $data['args'] ?? [];
    $action = $data['action'] ?? 'process-translation';
    
    // Log start
    polytrans_bg_log('Background process started: ' . $action . ' (token: ' . $token . ')');
    
    // Call the processing function (Bootstrap ensures class is available)
    \PolyTrans\Core\BackgroundProcessor::process_task($args, $action);
    
    // Clean up
    delete_transient('polytrans_bg_' . $token);
    
    polytrans_bg_log('Background process completed: ' . $action . ' (token: ' . $token . ')');
    
} catch (\Throwable $e) {
    polytrans_bg_log('Background process exception: ' . $e->getMessage());
    polytrans_bg_log('File: ' . $e->getFile() . ':' . $e->getLine());
    polytrans_bg_log('Trace: ' . $e->getTraceAsString());
    
    // Log via LogsManager (Bootstrap ensures it's available)
    try {
        \PolyTrans\Core\LogsManager::log(
            'Background process failed: ' . $e->getMessage(),
            'error',
            [
                'action' => $action ?? 'unknown',
                'args' => $args ?? [],
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'token' => $token
            ]
        );
    } catch (\Exception $log_error) {
        polytrans_bg_log('Failed to log via LogsManager: ' . $log_error->getMessage());
    }
    
    // Clean up even on error
    delete_transient('polytrans_bg_' . $token);
    exit(1);
}

