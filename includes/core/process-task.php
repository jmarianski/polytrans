<?php

/**
 * Background Process Task Runner
 * This file is executed directly by the background processor
 */

// Check if this is a direct call with a token
if (!defined('ABSPATH') && isset($argv) && count($argv) > 1) {
    // Get the token from command line
    $token = $argv[1] ?? '';

    if (empty($token)) {
        exit("Error: No token provided\n");
    }

    // Bootstrap WordPress
    $wp_load_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        // Try to find WordPress installation
        $max_depth = 10;
        $current_dir = dirname(__FILE__);

        for ($i = 0; $i < $max_depth; $i++) {
            $wp_load_path = $current_dir . '/wp-load.php';

            if (file_exists($wp_load_path)) {
                require_once($wp_load_path);
                break;
            }

            $current_dir = dirname($current_dir);

            // Stop if we reach the root directory
            if ($current_dir == '/' || empty($current_dir)) {
                break;
            }
        }
    }

    if (!defined('ABSPATH')) {
        exit("Error: Could not load WordPress\n");
    }

    // Ensure PolyTrans plugin is loaded (WordPress loads plugins automatically)
    if (!defined('POLYTRANS_VERSION')) {
        exit("Error: PolyTrans plugin not loaded\n");
    }

    // Get the stored process data
    $process_data = get_transient('polytrans_bg_' . $token);

    if (!$process_data) {
        error_log("[polytrans] Background process failed: Could not retrieve data for token $token");
        exit("Error: Could not retrieve process data\n");
    }

    // Extract arguments and action
    $args = $process_data['args'] ?? [];
    $action = $process_data['action'] ?? '';

    // Background processor is now autoloaded via PSR-4
    // Execute the task
    if (class_exists('PolyTrans_Background_Processor')) {
        error_log("[polytrans] Background process running task: $action");
        PolyTrans_Background_Processor::process_task($args, $action);
    } else {
        error_log("[polytrans] Background process failed: Background processor class not found");
    }

    // Clean up
    delete_transient('polytrans_bg_' . $token);

    exit(0);
}
