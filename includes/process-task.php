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

// Get token from command line argument or query string
$token = $argv[1] ?? $_GET['token'] ?? '';

if (empty($token)) {
    error_log('[polytrans] Background process failed: No token provided');
    error_log('[polytrans] Usage: php process-task.php <token>');
    error_log('[polytrans] argv: ' . print_r($argv ?? [], true));
    exit(1);
}

error_log('[polytrans] Starting background process with token: ' . $token);

try {
    // Get the data from transient
    $data = get_transient('polytrans_bg_' . $token);
    
    if (!$data) {
        error_log('[polytrans] Background process failed: Could not retrieve data for token ' . $token);
        exit(1);
    }
    
    // Extract arguments
    $args = $data['args'] ?? [];
    $action = $data['action'] ?? 'process-translation';
    
    // Log start
    error_log('[polytrans] Background process started: ' . $action . ' (token: ' . $token . ')');
    
    // Call the processing function - try namespaced class first, then legacy
    if (class_exists('\PolyTrans\Core\BackgroundProcessor')) {
        error_log('[polytrans] Using namespaced BackgroundProcessor class');
        \PolyTrans\Core\BackgroundProcessor::process_task($args, $action);
    } elseif (class_exists('PolyTrans_Background_Processor')) {
        error_log('[polytrans] Using legacy BackgroundProcessor class');
        PolyTrans_Background_Processor::process_task($args, $action);
    } else {
        error_log('[polytrans] Background process failed: BackgroundProcessor class not found');
        error_log('[polytrans] Available classes check:');
        error_log('[polytrans] - PolyTrans\\Core\\BackgroundProcessor: ' . (class_exists('\PolyTrans\Core\BackgroundProcessor') ? 'YES' : 'NO'));
        error_log('[polytrans] - PolyTrans_Background_Processor: ' . (class_exists('PolyTrans_Background_Processor') ? 'YES' : 'NO'));
        exit(1);
    }
    
    // Clean up
    delete_transient('polytrans_bg_' . $token);
    
    error_log('[polytrans] Background process completed: ' . $action . ' (token: ' . $token . ')');
    
} catch (\Throwable $e) {
    error_log('[polytrans] Background process exception: ' . $e->getMessage());
    error_log('[polytrans] File: ' . $e->getFile() . ':' . $e->getLine());
    error_log('[polytrans] Trace: ' . $e->getTraceAsString());
    
    // Try to log via LogsManager if available
    if (class_exists('\PolyTrans\Core\LogsManager')) {
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
            error_log('[polytrans] Failed to log via LogsManager: ' . $log_error->getMessage());
        }
    } elseif (class_exists('PolyTrans_Logs_Manager')) {
        try {
            PolyTrans_Logs_Manager::log(
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
            error_log('[polytrans] Failed to log via LogsManager (legacy): ' . $log_error->getMessage());
        }
    }
    
    // Clean up even on error
    delete_transient('polytrans_bg_' . $token);
    exit(1);
}

