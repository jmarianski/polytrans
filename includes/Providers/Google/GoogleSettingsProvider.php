<?php

namespace PolyTrans\Providers\Google;

use PolyTrans\Providers\SettingsProviderInterface;

/**
 * Google Translate Settings Provider
 * Simple provider that only handles translation (no assistants)
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoogleSettingsProvider implements SettingsProviderInterface
{
    public function get_provider_id()
    {
        return 'google';
    }

    public function get_tab_label()
    {
        return __('Google Translate', 'polytrans');
    }

    public function get_tab_description()
    {
        return __('Simple, fast translation using Google Translate public API. No API key required.', 'polytrans');
    }

    public function get_required_js_files()
    {
        return [];
    }

    public function get_required_css_files()
    {
        return [];
    }

    public function get_settings_keys()
    {
        return []; // Google has no settings
    }

    public function render_settings_ui(array $settings, array $languages, array $language_names)
    {
        echo '<p>' . esc_html__('Google Translate uses the public API and requires no configuration.', 'polytrans') . '</p>';
    }

    public function validate_settings(array $posted_data)
    {
        return []; // No settings to validate
    }

    public function get_default_settings()
    {
        return [];
    }

    public function is_configured(array $settings)
    {
        return true; // Always configured (public API)
    }

    public function get_configuration_status(array $settings)
    {
        return ''; // Always configured
    }

    public function enqueue_assets()
    {
        // No assets needed
    }

    public function get_ajax_handlers()
    {
        return [];
    }

    public function register_ajax_handlers()
    {
        // No AJAX handlers needed
    }

    /**
     * Get provider manifest with capabilities and configuration
     */
    public function get_provider_manifest(array $settings)
    {
        return [
            'provider_id' => 'google',
            'capabilities' => ['translation'], // Google provides translation, not assistants
            'auth_type' => 'none', // Public API, no auth needed
            'api_key_setting' => null,
            'api_key_configured' => true, // Always available
            'base_url' => 'https://translate.googleapis.com',
        ];
    }
}

