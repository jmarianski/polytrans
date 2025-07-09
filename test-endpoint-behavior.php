<?php
/**
 * Test to verify that translations create new posts instead of updating existing ones
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

/**
 * Test endpoint behavior
 */
function polytrans_test_endpoint_behavior() {
    echo "<h2>Testing PolyTrans Translation Endpoint Behavior</h2>\n";
    
    // Test the active REST routes
    $routes = rest_get_server()->get_routes();
    $translation_routes = array_filter($routes, function($route) {
        return strpos($route, '/polytrans/v1/translation/') === 0;
    }, ARRAY_FILTER_USE_KEY);
    
    echo "<h3>Active Translation Routes:</h3>\n";
    foreach ($translation_routes as $route => $handlers) {
        echo "<strong>$route</strong><br>\n";
        foreach ($handlers as $handler) {
            $callback = $handler['callback'];
            if (is_array($callback)) {
                $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                $method = $callback[1];
                echo "&nbsp;&nbsp;→ $class::$method()<br>\n";
            }
        }
        echo "<br>\n";
    }
    
    // Show which classes are handling the receive-post endpoint
    echo "<h3>Expected Behavior:</h3>\n";
    echo "<strong>/polytrans/v1/translation/receive-post</strong> should be handled by:<br>\n";
    echo "✅ <code>PolyTrans_Translation_Receiver_Extension::handle_receive_post()</code> - Creates NEW posts<br>\n";
    echo "❌ <code>PolyTrans_Translation_API::receive_translated_post()</code> - Updates existing posts (DISABLED)<br>\n";
    
    echo "<h3>Debug Steps:</h3>\n";
    echo "<ol>\n";
    echo "<li>Check WordPress error logs for messages starting with <code>[polytrans]</code></li>\n";
    echo "<li>Look for: <code>Created NEW translated post [ID] from original [ID]</code></li>\n";
    echo "<li>If you see the same post ID repeatedly, the old API might still be active</li>\n";
    echo "</ol>\n";
    
    echo "<h3>Test Translation Flow:</h3>\n";
    echo "<ol>\n";
    echo "<li>Trigger a translation from the admin interface</li>\n";
    echo "<li>Check the error log for: <code>Starting translation processing for post [ID]</code></li>\n";
    echo "<li>Should see: <code>Created NEW translated post [NEW_ID] from original [ORIGINAL_ID]</code></li>\n";
    echo "<li>Each translation should have a different NEW_ID</li>\n";
    echo "</ol>\n";
}

// Only run if accessed via admin
if (is_admin() && isset($_GET['polytrans_test_endpoint'])) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-info">';
        polytrans_test_endpoint_behavior();
        echo '</div>';
    });
}
