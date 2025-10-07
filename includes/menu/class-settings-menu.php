<?php

/**
 * Settings Menu Class
 * Handles PolyTrans main menu and settings page
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
        // Main menu - accessible to editors
        add_menu_page(
            __('PolyTrans', 'polytrans'),
            __('PolyTrans', 'polytrans'),
            'edit_posts',
            'polytrans',
            [$this, 'render_overview'],
            'dashicons-translation',
            80
        );
        
        // Rename first submenu item from "PolyTrans" to "Overview"
        add_submenu_page(
            'polytrans',
            __('Overview', 'polytrans'),
            __('Overview', 'polytrans'),
            'edit_posts',
            'polytrans',
            [$this, 'render_overview']
        );
        
        // Settings submenu - admin only
        add_submenu_page(
            'polytrans',
            __('Settings', 'polytrans'),
            __('Settings', 'polytrans'),
            'manage_options',
            'polytrans-settings',
            [$this, 'render_settings']
        );
    }

    public function add_scripts($hook)
    {
        // Load scripts for both overview and settings pages
        if ($hook === 'toplevel_page_polytrans' || $hook === 'polytrans_page_polytrans-settings') {
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
     * Render overview page
     */
    public function render_overview()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PolyTrans Overview', 'polytrans'); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e('Welcome to PolyTrans', 'polytrans'); ?></h2>
                <p><?php esc_html_e('PolyTrans is a powerful translation automation plugin that helps you manage multilingual content.', 'polytrans'); ?></p>
                
                <h3><?php esc_html_e('Quick Links', 'polytrans'); ?></h3>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=polytrans-execute-workflow'); ?>"><?php esc_html_e('Execute Workflow', 'polytrans'); ?></a> - <?php esc_html_e('Run translation workflows on your posts', 'polytrans'); ?></li>
                    <li><a href="<?php echo admin_url('admin.php?page=polytrans-workflows'); ?>"><?php esc_html_e('Manage Workflows', 'polytrans'); ?></a> - <?php esc_html_e('Create and edit post-processing workflows', 'polytrans'); ?></li>
                    <li><a href="<?php echo admin_url('admin.php?page=polytrans-tag-translation'); ?>"><?php esc_html_e('Tag Translations', 'polytrans'); ?></a> - <?php esc_html_e('Manage tag translations', 'polytrans'); ?></li>
                    <?php if (current_user_can('manage_options')): ?>
                    <li><a href="<?php echo admin_url('admin.php?page=polytrans-settings'); ?>"><?php esc_html_e('Settings', 'polytrans'); ?></a> - <?php esc_html_e('Configure PolyTrans (Admin only)', 'polytrans'); ?></li>
                    <li><a href="<?php echo admin_url('admin.php?page=polytrans-logs'); ?>"><?php esc_html_e('Logs', 'polytrans'); ?></a> - <?php esc_html_e('View system logs (Admin only)', 'polytrans'); ?></li>
                    <?php endif; ?>
                </ul>
                
                <h3><?php esc_html_e('How to Use', 'polytrans'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Edit any post in WordPress', 'polytrans'); ?></li>
                    <li><?php esc_html_e('Look for the "PolyTrans Workflows" meta box in the sidebar', 'polytrans'); ?></li>
                    <li><?php esc_html_e('Click "Execute" on any workflow to process your post', 'polytrans'); ?></li>
                </ol>
            </div>
        </div>
        <?php
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
