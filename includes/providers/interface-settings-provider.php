<?php

/**
 * Settings Provider Interface
 * Defines the contract for provider settings UI and management
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PolyTrans_Settings_Provider_Interface
{
    /**
     * Get the settings provider identifier (should match the translation provider ID)
     * @return string
     */
    public function get_provider_id();

    /**
     * Get the tab label for this provider's settings
     * @return string
     */
    public function get_tab_label();

    /**
     * Get the tab description (optional)
     * @return string
     */
    public function get_tab_description();

    /**
     * Get required JavaScript files for this settings provider
     * @return array Array of JS file paths (relative to plugin directory or URLs)
     */
    public function get_required_js_files();

    /**
     * Get required CSS files for this settings provider
     * @return array Array of CSS file paths (relative to plugin directory or URLs)
     */
    public function get_required_css_files();

    /**
     * Get provider-specific settings keys that this provider manages
     * @return array Array of setting keys that belong to this provider
     */
    public function get_settings_keys();

    /**
     * Render the provider settings UI
     * @param array $settings Current settings
     * @param array $languages Available languages
     * @param array $language_names Language display names
     * @return void
     */
    public function render_settings_ui(array $settings, array $languages, array $language_names);

    /**
     * Validate and sanitize provider-specific settings from POST data
     * @param array $posted_data Raw POST data
     * @return array Sanitized settings array with keys from get_settings_keys()
     */
    public function validate_settings(array $posted_data);

    /**
     * Get default settings for this provider
     * @return array Default settings array
     */
    public function get_default_settings();

    /**
     * Check if the provider settings are properly configured
     * @param array $settings Current settings
     * @return bool True if properly configured, false otherwise
     */
    public function is_configured(array $settings);

    /**
     * Get configuration status message
     * @param array $settings Current settings
     * @return string Status message (empty if configured properly)
     */
    public function get_configuration_status(array $settings);

    /**
     * Enqueue additional scripts and styles (called when settings page is loaded)
     * @return void
     */
    public function enqueue_assets();

    /**
     * Get AJAX handlers that this provider needs to register
     * @return array Array of AJAX handler definitions [action => [callback, is_static]]
     */
    public function get_ajax_handlers();

    /**
     * Register provider-specific AJAX handlers
     * This method is called during plugin initialization
     * @return void
     */
    public function register_ajax_handlers();
}
