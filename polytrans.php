<?php

/**
 * Plugin Name: PolyTrans
 * Plugin URI: https://github.com/your-username/polytrans
 * Description: Advanced multilingual translation management system with AI-powered translation, scheduling, and review workflow
 * Version: 1.0.0
 * Author: PolyTrans Team
 * Author URI: https://github.com/your-username/polytrans
 * Text Domain: polytrans
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('POLYTRANS_VERSION', '1.0.0');
define('POLYTRANS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POLYTRANS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POLYTRANS_PLUGIN_FILE', __FILE__);

// Include the main plugin class
require_once POLYTRANS_PLUGIN_DIR . 'includes/class-polytrans.php';

// Include background processor
require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-background-processor.php';

/**
 * Handle background process requests
 */
function polytrans_handle_background_request()
{
    if (isset($_GET['polytrans_bg']) && isset($_GET['token']) && isset($_GET['nonce'])) {
        if (wp_verify_nonce($_GET['nonce'], 'polytrans_bg_process')) {
            // Set headers to prevent caching and handle long-running process
            header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
            header('X-Robots-Tag: noindex, nofollow');
            header('Connection: close');

            // Disable browser buffering
            if (function_exists('fastcgi_finish_request')) {
                ignore_user_abort(true);
                set_time_limit(0);
                ob_end_flush();
                flush();
                fastcgi_finish_request();
            } else {
                // Fallback for non-FastCGI servers
                ignore_user_abort(true);
                set_time_limit(0);
                ob_end_flush();
                flush();
            }

            $token = sanitize_key($_GET['token']);
            $data = get_transient('polytrans_bg_' . $token);

            if ($data) {
                $args = $data['args'] ?? [];
                $action = $data['action'] ?? '';

                if (class_exists('PolyTrans_Background_Processor')) {
                    PolyTrans_Background_Processor::process_task($args, $action);
                }

                delete_transient('polytrans_bg_' . $token);
            }

            exit;
        }
    }
}
add_action('init', 'polytrans_handle_background_request', 5);

/**
 * Initialize the plugin
 */
function polytrans_init()
{
    PolyTrans::get_instance();
}
add_action('plugins_loaded', 'polytrans_init');

/**
 * Plugin activation hook
 */
function polytrans_activate()
{
    // Set default options
    $default_settings = [
        'translation_provider' => 'google',
        'translation_transport_mode' => 'external',
        'translation_endpoint' => '',
        'translation_receiver_endpoint' => '',
        'translation_receiver_secret' => '',
        'translation_receiver_secret_method' => 'header_bearer',
        'allowed_sources' => [],
        'allowed_targets' => [],
        'reviewer_email' => '',
        'reviewer_email_title' => 'Translation ready for review: {title}',
        'author_email' => '',
        'author_email_title' => 'Your translation has been published: {title}',
        'enable_db_logging' => '1', // Enable DB logging by default
    ];

    add_option('polytrans_settings', $default_settings);

    // Load the logs manager class for table creation
    require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-logs-manager.php';
    
    // Create database tables if needed and enabled in settings
    if (PolyTrans_Logs_Manager::is_db_logging_enabled()) {
        PolyTrans_Logs_Manager::create_logs_table();
        
        // Try to log an activation message
        PolyTrans_Logs_Manager::log(
            "PolyTrans plugin activated", 
            "info", 
            [
                'version' => POLYTRANS_VERSION,
                'source' => 'activation'
            ]
        );
    } else {
        // Just log to error_log
        error_log("[polytrans] Plugin activated, database logging disabled in settings");
    }
    
    // Check the logs table structure for debugging
    if (class_exists('PolyTrans_Background_Processor')) {
        PolyTrans_Background_Processor::check_on_activation();
    }
    
    // Schedule cron job to check for stuck translations (daily)
    if (!wp_next_scheduled('polytrans_check_stuck_translations')) {
        wp_schedule_event(time(), 'daily', 'polytrans_check_stuck_translations');
    }
}
register_activation_hook(__FILE__, 'polytrans_activate');

/**
 * Plugin deactivation hook
 */
function polytrans_deactivate()
{
    // Clean up any scheduled tasks
    wp_clear_scheduled_hook('polytrans_cleanup');
    wp_clear_scheduled_hook('polytrans_check_stuck_translations');
}
register_deactivation_hook(__FILE__, 'polytrans_deactivate');

/**
 * Create plugin database tables
 */
function polytrans_create_tables()
{
    // Load the settings to check if database logging is enabled
    $settings = get_option('polytrans_settings', []);
    $db_logging_enabled = isset($settings['enable_db_logging']) ? (bool)$settings['enable_db_logging'] : true;
    
    // Create logs table using the Logs Manager if database logging is enabled
    if ($db_logging_enabled) {
        require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-logs-manager.php';
        
        // This will handle creation and structure adaptation
        PolyTrans_Logs_Manager::create_logs_table();
    } else {
        error_log("[polytrans] Database logging is disabled, skipping logs table creation");
    }
    
    // Any additional tables can be created here
}

/**
 * Load plugin textdomain
 */
function polytrans_load_textdomain()
{
    load_plugin_textdomain('polytrans', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'polytrans_load_textdomain');

/**
 * Schedule cleanup tasks
 */
function polytrans_schedule_cleanup()
{
    if (!wp_next_scheduled('polytrans_cleanup')) {
        wp_schedule_event(time(), 'daily', 'polytrans_cleanup');
    }
}
add_action('wp', 'polytrans_schedule_cleanup');

/**
 * Run cleanup tasks
 */
function polytrans_run_cleanup()
{
    // Fix stuck translations
    $handler = PolyTrans_Translation_Handler::get_instance();
    $fixed = $handler->fix_stuck_translations(24); // 24 hours timeout
    
    if ($fixed > 0) {
        error_log("[polytrans] Fixed $fixed stuck translations");
    }
}
add_action('polytrans_cleanup', 'polytrans_run_cleanup');

/**
 * Check for stuck translations and mark them as failed
 */
function polytrans_check_stuck_translations()
{
    // Load the status manager class if not already loaded
    if (!class_exists('PolyTrans_Translation_Status_Manager')) {
        require_once POLYTRANS_PLUGIN_DIR . 'includes/receiver/managers/class-translation-status-manager.php';
    }
    
    $status_manager = new PolyTrans_Translation_Status_Manager();
    $results = $status_manager->check_stuck_translations(24); // Check translations stuck for > 24 hours
    
    if ($results['fixed'] > 0) {
        error_log("[polytrans] Fixed {$results['fixed']} stuck translations out of {$results['checked']} checked");
    }
}
add_action('polytrans_check_stuck_translations', 'polytrans_check_stuck_translations');
