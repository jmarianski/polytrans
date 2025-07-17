<?php

/**
 * Check WordPress logging configuration
 */

if (!defined('ABSPATH')) {
    require_once('/home/jm/projects/trans-info/transinfo-wp-docker/public/wp-config.php');
}

echo "=== WordPress Logging Configuration Check ===\n\n";

echo "1. WordPress Debug Settings:\n";
echo "   WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . "\n";
echo "   WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . "\n";
echo "   WP_DEBUG_DISPLAY: " . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'Enabled' : 'Disabled') . "\n\n";

echo "2. Error Log Location:\n";
$log_file = ini_get('error_log');
if ($log_file) {
    echo "   PHP error_log: " . $log_file . "\n";
    echo "   File exists: " . (file_exists($log_file) ? 'Yes' : 'No') . "\n";
    if (file_exists($log_file)) {
        echo "   File writable: " . (is_writable($log_file) ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "   PHP error_log: Not configured\n";
}

$wp_log_file = WP_CONTENT_DIR . '/debug.log';
echo "   WordPress debug.log: " . $wp_log_file . "\n";
echo "   File exists: " . (file_exists($wp_log_file) ? 'Yes' : 'No') . "\n";
if (file_exists($wp_log_file)) {
    echo "   File writable: " . (is_writable($wp_log_file) ? 'Yes' : 'No') . "\n";
}

echo "\n3. Testing error_log function:\n";
$test_message = "PolyTrans Test Log Message - " . date('Y-m-d H:i:s');
if (error_log($test_message)) {
    echo "   ✅ error_log() function works\n";
    echo "   Test message logged: " . $test_message . "\n";

    // Check where it was logged
    if (file_exists($wp_log_file)) {
        $recent_content = tail($wp_log_file, 5);
        if (strpos($recent_content, $test_message) !== false) {
            echo "   ✅ Message found in WordPress debug.log\n";
        }
    }

    if ($log_file && file_exists($log_file)) {
        $recent_content = tail($log_file, 5);
        if (strpos($recent_content, $test_message) !== false) {
            echo "   ✅ Message found in PHP error log\n";
        }
    }
} else {
    echo "   ❌ error_log() function failed\n";
}

echo "\n4. Recommendations:\n";
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    echo "   - Enable WP_DEBUG in wp-config.php: define('WP_DEBUG', true);\n";
}
if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
    echo "   - Enable WP_DEBUG_LOG in wp-config.php: define('WP_DEBUG_LOG', true);\n";
}
if (!file_exists($wp_log_file)) {
    echo "   - Create debug.log file: touch " . $wp_log_file . "\n";
    echo "   - Make it writable: chmod 666 " . $wp_log_file . "\n";
}

function tail($filename, $lines = 10)
{
    if (!file_exists($filename)) return '';
    $handle = fopen($filename, "r");
    if (!$handle) return '';

    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = array();

    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }
        $linecounter--;
        if ($beginning) {
            rewind($handle);
        }
        $text[$lines - $linecounter - 1] = fgets($handle);
        if ($beginning) break;
    }
    fclose($handle);
    return implode("", array_reverse($text));
}

echo "\n=== Configuration Check Complete ===\n";
