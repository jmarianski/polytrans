<?php

/**
 * Test script to verify the improved provider system structure
 */

// Mock WordPress functions for testing
function __($text, $domain = 'default')
{
    return $text;
}
function add_action($hook, $callback)
{
    echo "AJAX action registered: {$hook}\n";
}

// Mock plugin constants
define('POLYTRANS_PLUGIN_DIR', __DIR__ . '/');
define('POLYTRANS_PLUGIN_URL', 'http://example.com/plugins/polytrans/');
define('POLYTRANS_VERSION', '1.0.0');

// Mock WordPress constants
define('ABSPATH', '/');

echo "=== PolyTrans Provider System Structure Test ===\n";

// Test interface loading
echo "1. Testing interface loading...\n";
if (file_exists(POLYTRANS_PLUGIN_DIR . 'includes/providers/interface-settings-provider.php')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/interface-settings-provider.php';
    echo "   ✓ Settings provider interface loaded\n";
} else {
    echo "   ✗ Settings provider interface not found\n";
}

if (file_exists(POLYTRANS_PLUGIN_DIR . 'includes/providers/interface-translation-provider.php')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/interface-translation-provider.php';
    echo "   ✓ Translation provider interface loaded\n";
} else {
    echo "   ✗ Translation provider interface not found\n";
}

// Test provider registry
echo "\n2. Testing provider registry...\n";
if (file_exists(POLYTRANS_PLUGIN_DIR . 'includes/providers/class-provider-registry.php')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/class-provider-registry.php';
    echo "   ✓ Provider registry class loaded\n";
} else {
    echo "   ✗ Provider registry class not found\n";
}

// Test OpenAI provider
echo "\n3. Testing OpenAI provider...\n";
if (file_exists(POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-provider.php')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-provider.php';
    echo "   ✓ OpenAI provider class loaded\n";
} else {
    echo "   ✗ OpenAI provider class not found\n";
}

if (file_exists(POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-settings-provider.php')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/openai/class-openai-settings-provider.php';
    echo "   ✓ OpenAI settings provider class loaded\n";

    // Test the new interface methods
    if (method_exists('PolyTrans_OpenAI_Settings_Provider', 'get_ajax_handlers')) {
        echo "   ✓ get_ajax_handlers method exists\n";
    } else {
        echo "   ✗ get_ajax_handlers method not found\n";
    }

    if (method_exists('PolyTrans_OpenAI_Settings_Provider', 'register_ajax_handlers')) {
        echo "   ✓ register_ajax_handlers method exists\n";
    } else {
        echo "   ✗ register_ajax_handlers method not found\n";
    }

    // Test instance creation and AJAX handler registration
    try {
        $openai_settings = new PolyTrans_OpenAI_Settings_Provider();
        echo "   ✓ OpenAI settings provider instance created\n";

        $ajax_handlers = $openai_settings->get_ajax_handlers();
        echo "   ✓ AJAX handlers retrieved: " . count($ajax_handlers) . " handlers\n";

        echo "   Registering AJAX handlers...\n";
        $openai_settings->register_ajax_handlers();
        echo "   ✓ AJAX handlers registered\n";
    } catch (\Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ OpenAI settings provider class not found\n";
}

// Test Google provider
echo "\n4. Testing Google provider...\n";
if (file_exists(POLYTRANS_PLUGIN_DIR . 'includes/providers/google/class-google-provider.php')) {
    require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/google/class-google-provider.php';
    echo "   ✓ Google provider class loaded\n";

    try {
        $google_provider = new PolyTrans_Google_Provider();
        $settings_provider_class = $google_provider->get_settings_provider_class();

        if ($settings_provider_class === null) {
            echo "   ✓ Google provider correctly returns null for settings provider (no UI needed)\n";
        } else {
            echo "   ✗ Google provider should return null for settings provider\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Google provider class not found\n";
}

echo "\n=== Test Complete ===\n";
