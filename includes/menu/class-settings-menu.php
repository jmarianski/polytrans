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

            // Localize user autocomplete script
            wp_localize_script('polytrans-user-autocomplete', 'PolyTransUserAutocomplete', [
                'i18n' => [
                    'no_results' => esc_html__('No users found.', 'polytrans'),
                    'searching' => esc_html__('Searching users...', 'polytrans'),
                    'clear_selection' => esc_html__('Clear selection', 'polytrans'),
                    'type_to_search' => esc_html__('Type to search users...', 'polytrans'),
                    'min_chars' => esc_html__('Type at least 2 characters to search.', 'polytrans'),
                ]
            ]);

            // Localize script for main settings
            $settings = get_option('polytrans_settings', []);
            wp_localize_script('polytrans-settings', 'PolyTransAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_nonce'),
                'settings' => $settings,
                'translation_receiver_endpoint' => $settings['translation_receiver_endpoint'] ?? '',
                'i18n' => [
                    'loading' => esc_html__('Loading...', 'polytrans'),
                    'saving' => esc_html__('Saving...', 'polytrans'),
                    'saved' => esc_html__('Settings saved successfully!', 'polytrans'),
                    'error' => esc_html__('An error occurred. Please try again.', 'polytrans'),
                    'confirm_delete' => esc_html__('Are you sure you want to delete this item?', 'polytrans'),
                    'test_connection' => esc_html__('Testing connection...', 'polytrans'),
                    'connection_success' => esc_html__('Connection successful!', 'polytrans'),
                    'connection_failed' => esc_html__('Connection failed. Please check your settings.', 'polytrans'),
                    'invalid_url' => esc_html__('Please enter a valid URL.', 'polytrans'),
                    'required_field' => esc_html__('This field is required.', 'polytrans'),
                ]
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
