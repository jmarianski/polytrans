<?php

/**
 * Translation Provider Interface
 * Defines the contract that all translation providers must implement
 * This interface focuses purely on translation functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PolyTrans_Translation_Provider_Interface
{
    /**
     * Get the provider identifier (unique string)
     * @return string
     */
    public function get_id();

    /**
     * Get the provider display name
     * @return string
     */
    public function get_name();

    /**
     * Get the provider description
     * @return string
     */
    public function get_description();

    /**
     * Check if the provider is properly configured
     * @param array $settings Translation settings
     * @return bool
     */
    public function is_configured(array $settings);

    /**
     * Translate content
     * @param array $content Content to translate (title, content, excerpt, meta)
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @param array $settings Translation settings
     * @return array ['success' => bool, 'translated_content' => array, 'error' => string]
     */
    public function translate(array $content, string $source_lang, string $target_lang, array $settings);

    /**
     * Get supported languages (optional - return empty array if all languages supported)
     * @return array Language codes array
     */
    public function get_supported_languages();

    /**
     * Get the settings provider class name for this translation provider (if any)
     * @return string|null Class name implementing PolyTrans_Settings_Provider_Interface, or null if no settings needed
     */
    public function get_settings_provider_class();
}
