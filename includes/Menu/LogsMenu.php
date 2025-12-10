<?php

/**
 * Logs Menu Class
 * Handles logs menu management
 */

namespace PolyTrans\Menu;

if (!defined('ABSPATH')) {
    exit;
}

class LogsMenu
{

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add logs submenu
     */
    public function add_logs_submenu()
    {
        add_submenu_page(
            'polytrans',
            __('Logs', 'polytrans'),
            __('Logs', 'polytrans'),
            'manage_options',
            'polytrans-logs',
            [$this, 'render_logs'],
            50
        );
    }

    /**
     * Add scripts for logs page
     */
    public function add_scripts($hook)
    {
        // Load on logs page
        if ($hook === 'polytrans_page_polytrans-logs') {
            wp_enqueue_script('jquery');
            // Add inline script to make ajaxurl available
            wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";');
        }
    }

    /**
     * Render logs page
     */
    public function render_logs()
    {
        \PolyTrans_Logs_Manager::admin_logs_page();
    }
}
