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
    ];

    add_option('polytrans_settings', $default_settings);

    // Create database tables if needed (for future use)
    polytrans_create_tables();
}
register_activation_hook(__FILE__, 'polytrans_activate');

/**
 * Plugin deactivation hook
 */
function polytrans_deactivate()
{
    // Clean up any scheduled tasks
    wp_clear_scheduled_hook('polytrans_cleanup');
}
register_deactivation_hook(__FILE__, 'polytrans_deactivate');

/**
 * Create plugin database tables
 */
function polytrans_create_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Translation log table (for future use)
    $table_name = $wpdb->prefix . 'polytrans_logs';

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        source_language varchar(10) NOT NULL,
        target_language varchar(10) NOT NULL,
        status varchar(20) NOT NULL,
        message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY languages (source_language, target_language),
        KEY status (status)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Load plugin textdomain
 */
function polytrans_load_textdomain()
{
    load_plugin_textdomain('polytrans', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'polytrans_load_textdomain');
