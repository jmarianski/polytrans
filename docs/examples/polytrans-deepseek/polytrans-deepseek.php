<?php
/**
 * Plugin Name: PolyTrans DeepSeek Provider
 * Plugin URI: https://github.com/your-username/polytrans-deepseek
 * Description: Adds DeepSeek as a translation provider for PolyTrans. This is an example plugin demonstrating how to add custom providers to PolyTrans.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Requires at least: 5.0
 * Requires PHP: 8.1
 * Requires Plugins: polytrans
 * Text Domain: polytrans-deepseek
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if PolyTrans is active
if (!function_exists('PolyTrans')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>PolyTrans DeepSeek Provider</strong> requires <strong>PolyTrans</strong> plugin to be installed and activated.</p></div>';
    });
    return;
}

// Check minimum PolyTrans version (1.5.8+ for extensibility features)
if (defined('POLYTRANS_VERSION')) {
    $required_version = '1.5.8';
    if (version_compare(POLYTRANS_VERSION, $required_version, '<')) {
        add_action('admin_notices', function() use ($required_version) {
            echo '<div class="error"><p><strong>PolyTrans DeepSeek Provider</strong> requires PolyTrans version ' . esc_html($required_version) . ' or higher. Current version: ' . esc_html(POLYTRANS_VERSION) . '</p></div>';
        });
        return;
    }
}

/**
 * Register DeepSeek provider with PolyTrans
 */
add_action('polytrans_register_providers', function($registry) {
    require_once __DIR__ . '/includes/DeepSeekProvider.php';
    $registry->register_provider(new DeepSeekProvider());
});

/**
 * Register DeepSeek assistant client adapter
 * Factory will automatically detect it based on assistant ID format (deepseek_xxx)
 */
add_filter('polytrans_assistant_client_factory_create', function($client, $assistant_id, $settings) {
    if (strpos($assistant_id, 'deepseek_') === 0) {
        require_once __DIR__ . '/includes/DeepSeekAssistantClientAdapter.php';
        $api_key = $settings['deepseek_api_key'] ?? '';
        if (empty($api_key)) {
            return null;
        }
        return new DeepSeekAssistantClientAdapter($api_key);
    }
    return $client;
}, 10, 3);

/**
 * Register provider ID detection for DeepSeek
 */
add_filter('polytrans_assistant_client_factory_get_provider_id', function($provider_id, $assistant_id) {
    if (strpos($assistant_id, 'deepseek_') === 0) {
        return 'deepseek';
    }
    return $provider_id;
}, 10, 2);

