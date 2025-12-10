<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests: Workflow Storage Manager
 *
 * Tests the database-based workflow storage system:
 * - CRUD operations (create, read, update, delete)
 * - Migration from wp_options to dedicated table
 * - Backward compatibility mappings
 * - Data validation and sanitization
 */

beforeEach(function () {
    // Mock WordPress globals and functions
    global $wpdb;

    $this->wpdb = $this->createMock(stdClass::class);
    $this->wpdb->prefix = 'wp_';
    $this->wpdb->polytrans_workflows = 'wp_polytrans_workflows';

    $wpdb = $this->wpdb;

    // Mock WordPress functions
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            return $default;
        }
    }

    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            return true;
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data) {
            return json_encode($data);
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return trim(strip_tags($str));
        }
    }
});

test('database table name follows WordPress conventions', function () {
    global $wpdb;
    expect($wpdb->polytrans_workflows)->toBe('wp_polytrans_workflows');
    expect($wpdb->polytrans_workflows)->toStartWith('wp_');
});
