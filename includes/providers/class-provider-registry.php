<?php

/**
 * Translation Provider Registry
 * Manages registration and access to translation providers
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Provider_Registry
{
    private static $instance = null;
    private $providers = [];

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_default_providers();
        $this->register_providers();
    }

    /**
     * Load default providers (Google and OpenAI)
     */
    public function load_default_providers()
    {
        // Load interfaces
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/interface-settings-provider.php';

        // Load built-in providers
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/google/class-google-provider.php';
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-client.php';
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-provider.php';
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-settings-provider.php';

        // Register default providers
        $this->register_provider(new PolyTrans_Google_Provider());
        $this->register_provider(new PolyTrans_OpenAI_Provider());
    }

    /**
     * Allow third-party providers to register themselves
     */
    public function register_providers()
    {
        /**
         * Allow third-party plugins to register translation providers
         * 
         * @param PolyTrans_Provider_Registry $registry The provider registry
         */
        do_action('polytrans_register_providers', $this);
    }

    /**
     * Register a translation provider
     * @param PolyTrans_Translation_Provider_Interface $provider
     */
    public function register_provider(PolyTrans_Translation_Provider_Interface $provider)
    {
        $this->providers[$provider->get_id()] = $provider;
    }

    /**
     * Get all registered providers
     * @return array
     */
    public function get_providers()
    {
        return $this->providers;
    }

    /**
     * Initialize all registered providers
     * This should be called during plugin initialization
     */
    public function init_providers()
    {
        foreach ($this->providers as $provider_id => $provider) {
            $settings_provider_class = $provider->get_settings_provider_class();

            if ($settings_provider_class && class_exists($settings_provider_class)) {
                // Register AJAX handlers if the provider supports it
                if (method_exists($settings_provider_class, 'register_ajax_handlers')) {
                    $settings_provider_instance = new $settings_provider_class();
                    $settings_provider_instance->register_ajax_handlers();
                }
            }
        }
    }

    /**
     * Get a specific provider by ID
     * @param string $provider_id
     * @return PolyTrans_Translation_Provider_Interface|null
     */
    public function get_provider($provider_id)
    {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id] : null;
    }

    /**
     * Get provider choices for settings dropdown
     * @return array
     */
    public function get_provider_choices()
    {
        $choices = [];
        foreach ($this->providers as $provider) {
            $choices[$provider->get_id()] = $provider->get_name();
        }
        return $choices;
    }

    /**
     * Get providers that have custom settings UI
     * @return array
     */
    public function get_providers_with_settings()
    {
        return array_filter($this->providers, function ($provider) {
            return $provider->has_settings_ui();
        });
    }
}
