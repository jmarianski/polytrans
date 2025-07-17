<?php

/**
 * Test script to verify the improved provider system
 */

// Include WordPress environment
require_once('wp-load.php');

// Mock the plugin constants if not defined
if (!defined('POLYTRANS_PLUGIN_DIR')) {
    define('POLYTRANS_PLUGIN_DIR', __DIR__ . '/');
}
if (!defined('POLYTRANS_PLUGIN_URL')) {
    define('POLYTRANS_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('POLYTRANS_VERSION')) {
    define('POLYTRANS_VERSION', '1.0.0');
}

// Include the main plugin file
require_once POLYTRANS_PLUGIN_DIR . 'includes/class-polytrans.php';

echo "=== PolyTrans Provider System Test ===\n";

// Test the provider registry
$registry = PolyTrans_Provider_Registry::get_instance();

echo "1. Testing provider registry initialization...\n";
$providers = $registry->get_providers();
echo "   Found " . count($providers) . " providers:\n";
foreach ($providers as $id => $provider) {
    echo "   - {$id}: " . $provider->get_name() . "\n";

    $settings_provider_class = $provider->get_settings_provider_class();
    if ($settings_provider_class) {
        echo "     Settings provider: {$settings_provider_class}\n";

        if (class_exists($settings_provider_class)) {
            $settings_instance = new $settings_provider_class();

            if (method_exists($settings_instance, 'get_ajax_handlers')) {
                $ajax_handlers = $settings_instance->get_ajax_handlers();
                echo "     AJAX handlers: " . count($ajax_handlers) . "\n";
                foreach ($ajax_handlers as $action => $handler) {
                    echo "       - {$action}\n";
                }
            }
        }
    } else {
        echo "     Settings provider: None (basic provider)\n";
    }
}

echo "\n2. Testing provider initialization...\n";
$registry->init_providers();
echo "   Provider initialization completed.\n";

echo "\n3. Testing individual provider retrieval...\n";
$openai_provider = $registry->get_provider('openai');
if ($openai_provider) {
    echo "   OpenAI provider found: " . $openai_provider->get_name() . "\n";
}

$google_provider = $registry->get_provider('google');
if ($google_provider) {
    echo "   Google provider found: " . $google_provider->get_name() . "\n";
}

echo "\n=== Test Complete ===\n";
