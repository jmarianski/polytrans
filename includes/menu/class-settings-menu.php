<?php

/**
 * Tag Translation Class
 * Handles tag translation management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Settings_Menu
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


    public function add_admin_menu()
    {
        add_menu_page(
            __('PolyTrans', 'polytrans'),
            __('PolyTrans', 'polytrans'),
            'manage_options',
            'polytrans',
            [$this, 'render_settings'],
            'dashicons-translation',
            80
        );
    }

    public function add_scripts($hook)
    {
        if ($hook === 'toplevel_page_polytrans') {
            $plugin_url = POLYTRANS_PLUGIN_URL;
            wp_enqueue_script('polytrans-settings', $plugin_url . 'assets/js/settings/translation-settings-admin.js', ['jquery'], POLYTRANS_VERSION, true);
            wp_enqueue_script('polytrans-user-autocomplete', $plugin_url . 'assets/js/core/user-autocomplete.js', ['jquery-ui-autocomplete'], POLYTRANS_VERSION, true);

            wp_enqueue_style('polytrans-settings', $plugin_url . 'assets/css/settings/translation-settings-admin.css', [], POLYTRANS_VERSION);
            wp_enqueue_style('jquery-ui-autocomplete');

            // Localize script for main settings
            $settings = get_option('polytrans_settings', []);
            wp_localize_script('polytrans-settings', 'PolyTransAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_nonce'),
                'settings' => $settings,
                'translation_receiver_endpoint' => $settings['translation_receiver_endpoint'] ?? '',
            ]);
        }
    }

    /**
     * Render settings page
     */
    public function render_settings()
    {
        require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-translation-settings.php';
        $settings = new polytrans_settings();
        $settings->render();
    }
}
