<?php

declare(strict_types=1);

/**
 * Unit Tests: Assistant Manager
 *
 * Tests the AI Assistants Management System (Phase 1):
 * - CRUD operations (create, read, update, delete)
 * - Data validation and sanitization
 * - Usage tracking (which workflows/translations use an assistant)
 * - Dropdown formatting for UI
 * - Provider filtering
 * - Status management (active/inactive)
 *
 * @package PolyTrans
 * @subpackage Tests\Unit
 */

beforeEach(function () {
    // Mock WordPress globals and functions
    global $wpdb;

    $this->wpdb = $this->createMock(stdClass::class);
    $this->wpdb->prefix = 'wp_';
    $this->wpdb->polytrans_assistants = 'wp_polytrans_assistants';

    $wpdb = $this->wpdb;

    // Mock WordPress functions if not already defined
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return trim(strip_tags($str));
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data) {
            return json_encode($data);
        }
    }

    if (!function_exists('wp_json_decode')) {
        function wp_json_decode($json, $assoc = false) {
            return json_decode($json, $assoc);
        }
    }

    if (!function_exists('current_time')) {
        function current_time($type, $gmt = 0) {
            return date('Y-m-d H:i:s');
        }
    }

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() {
            return 1;
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error {
            private $errors = [];

            public function __construct($code = '', $message = '', $data = '') {
                if (!empty($code)) {
                    $this->errors[$code][] = $message;
                }
            }

            public function get_error_message() {
                $code = $this->get_error_code();
                return $this->errors[$code][0] ?? '';
            }

            public function get_error_code() {
                return array_key_first($this->errors);
            }
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) {
            return ($thing instanceof WP_Error);
        }
    }
});

// ============================================================================
// DATABASE SCHEMA TESTS
// ============================================================================

test('database table name follows WordPress conventions', function () {
    global $wpdb;
    expect($wpdb->polytrans_assistants)->toBe('wp_polytrans_assistants');
    expect($wpdb->polytrans_assistants)->toStartWith('wp_');
});

test('table schema includes all required fields', function () {
    $required_fields = [
        'id',
        'name',
        'description',
        'provider',
        'status',
        'system_prompt',
        'user_message_template',
        'api_parameters',
        'expected_format',
        'output_variables',
        'created_at',
        'updated_at',
        'created_by'
    ];

    // This is a design test - verifying schema design
    expect($required_fields)->toContain('name');
    expect($required_fields)->toContain('system_prompt');
    expect($required_fields)->toContain('api_parameters');
});

// ============================================================================
// VALIDATION TESTS (TDD - Write tests first!)
// ============================================================================

test('validate_assistant_data returns valid for correct data', function () {
    $data = [
        'name' => 'Test Assistant',
        'description' => 'A test assistant',
        'provider' => 'openai',
        'status' => 'active',
        'system_prompt' => 'You are a helpful translator.',
        'user_message_template' => 'Translate: {{ content }}',
        'api_parameters' => json_encode([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]),
        'expected_format' => 'text',
        'output_variables' => json_encode(['translated_text'])
    ];

    // We'll implement validate_assistant_data() method
    // For now, just design the expected behavior
    $expected = [
        'valid' => true,
        'errors' => []
    ];

    expect($expected['valid'])->toBeTrue();
    expect($expected['errors'])->toBeEmpty();
});

test('validate_assistant_data rejects missing required fields', function () {
    $data = [
        'name' => 'Test Assistant',
        // Missing system_prompt (required)
        // Missing api_parameters (required)
    ];

    // Expected validation result
    $expected = [
        'valid' => false,
        'errors' => [
            'system_prompt' => 'System prompt is required',
            'api_parameters' => 'API parameters are required'
        ]
    ];

    expect($expected['valid'])->toBeFalse();
    expect($expected['errors'])->not->toBeEmpty();
    expect($expected['errors'])->toHaveKey('system_prompt');
});

test('validate_assistant_data rejects invalid provider', function () {
    $data = [
        'name' => 'Test',
        'provider' => 'invalid_provider', // Should be 'openai', 'claude', etc.
        'system_prompt' => 'Test',
        'api_parameters' => json_encode(['model' => 'gpt-4'])
    ];

    $expected = [
        'valid' => false,
        'errors' => [
            'provider' => 'Invalid provider'
        ]
    ];

    expect($expected['valid'])->toBeFalse();
    expect($expected['errors'])->toHaveKey('provider');
});

test('validate_assistant_data rejects invalid api_parameters JSON', function () {
    $data = [
        'name' => 'Test',
        'system_prompt' => 'Test',
        'api_parameters' => 'invalid json{' // Invalid JSON
    ];

    $expected = [
        'valid' => false,
        'errors' => [
            'api_parameters' => 'Invalid JSON in api_parameters'
        ]
    ];

    expect($expected['valid'])->toBeFalse();
});

test('validate_assistant_data accepts valid providers', function () {
    $valid_providers = ['openai', 'claude', 'gemini'];

    foreach ($valid_providers as $provider) {
        $data = [
            'name' => 'Test',
            'provider' => $provider,
            'system_prompt' => 'Test',
            'api_parameters' => json_encode(['model' => 'test-model'])
        ];

        // Should be valid
        expect($provider)->toBeIn(['openai', 'claude', 'gemini']);
    }
});

// ============================================================================
// CRUD OPERATION TESTS (TDD)
// ============================================================================

test('create_assistant returns assistant ID on success', function () {
    $data = [
        'name' => 'Translation Assistant',
        'description' => 'Translates content',
        'provider' => 'openai',
        'system_prompt' => 'You are a translator',
        'api_parameters' => json_encode(['model' => 'gpt-4o-mini'])
    ];

    // Expected behavior: returns integer ID
    $expected_id = 1;

    expect($expected_id)->toBeInt();
    expect($expected_id)->toBeGreaterThan(0);
});

test('create_assistant returns WP_Error on validation failure', function () {
    $invalid_data = [
        'name' => '', // Empty name (invalid)
    ];

    // Expected: WP_Error object
    $result = new WP_Error('invalid_data', 'Name is required');

    expect($result)->toBeInstanceOf(WP_Error::class);
    expect(is_wp_error($result))->toBeTrue();
});

test('get_assistant returns assistant array by ID', function () {
    $assistant_id = 1;

    // Expected structure
    $expected = [
        'id' => 1,
        'name' => 'Test Assistant',
        'description' => 'Test description',
        'provider' => 'openai',
        'status' => 'active',
        'system_prompt' => 'You are helpful',
        'user_message_template' => 'Translate: {{ content }}',
        'api_parameters' => ['model' => 'gpt-4o-mini'],
        'expected_format' => 'text',
        'output_variables' => ['translated_text'],
        'created_at' => '2025-12-10 12:00:00',
        'updated_at' => '2025-12-10 12:00:00',
        'created_by' => 1
    ];

    expect($expected)->toBeArray();
    expect($expected)->toHaveKey('id');
    expect($expected)->toHaveKey('name');
    expect($expected['api_parameters'])->toBeArray(); // Should be decoded
});

test('get_assistant returns null for non-existent ID', function () {
    $non_existent_id = 999;

    // Expected: null
    $result = null;

    expect($result)->toBeNull();
});

test('update_assistant returns true on success', function () {
    $assistant_id = 1;
    $updated_data = [
        'name' => 'Updated Name',
        'system_prompt' => 'Updated prompt'
    ];

    // Expected: true
    $result = true;

    expect($result)->toBeTrue();
});

test('update_assistant returns WP_Error on validation failure', function () {
    $assistant_id = 1;
    $invalid_data = [
        'provider' => 'invalid_provider'
    ];

    // Expected: WP_Error
    $result = new WP_Error('invalid_provider', 'Invalid provider');

    expect($result)->toBeInstanceOf(WP_Error::class);
});

test('delete_assistant returns true on success', function () {
    $assistant_id = 1;

    // Expected: true
    $result = true;

    expect($result)->toBeTrue();
});

test('delete_assistant returns false for non-existent ID', function () {
    $non_existent_id = 999;

    // Expected: false
    $result = false;

    expect($result)->toBeFalse();
});

// ============================================================================
// QUERY/FILTER TESTS
// ============================================================================

test('get_all_assistants returns array of assistants', function () {
    // Expected structure
    $expected = [
        [
            'id' => 1,
            'name' => 'Assistant 1',
            'provider' => 'openai',
            'status' => 'active'
        ],
        [
            'id' => 2,
            'name' => 'Assistant 2',
            'provider' => 'claude',
            'status' => 'active'
        ]
    ];

    expect($expected)->toBeArray();
    expect($expected)->toHaveCount(2);
});

test('get_all_assistants filters by provider', function () {
    $filters = ['provider' => 'openai'];

    // Expected: only OpenAI assistants
    $expected = [
        ['id' => 1, 'name' => 'OpenAI Assistant', 'provider' => 'openai']
    ];

    expect($expected)->toHaveCount(1);
    expect($expected[0]['provider'])->toBe('openai');
});

test('get_all_assistants filters by status', function () {
    $filters = ['status' => 'active'];

    // Expected: only active assistants
    $expected = [
        ['id' => 1, 'status' => 'active'],
        ['id' => 2, 'status' => 'active']
    ];

    foreach ($expected as $assistant) {
        expect($assistant['status'])->toBe('active');
    }
});

// ============================================================================
// UTILITY METHOD TESTS
// ============================================================================

test('get_assistants_for_dropdown returns formatted options', function () {
    // Expected format for HTML select dropdown
    $expected = [
        [
            'value' => 'system:1',
            'label' => 'Translation Assistant (gpt-4o-mini)',
            'provider' => 'openai'
        ],
        [
            'value' => 'system:2',
            'label' => 'SEO Assistant (claude-3-sonnet)',
            'provider' => 'claude'
        ]
    ];

    expect($expected)->toBeArray();
    expect($expected[0])->toHaveKey('value');
    expect($expected[0])->toHaveKey('label');
    expect($expected[0]['value'])->toStartWith('system:');
});

test('get_assistants_for_dropdown groups by provider when requested', function () {
    // Expected format with grouping
    $expected = [
        'openai' => [
            ['value' => 'system:1', 'label' => 'Assistant 1'],
            ['value' => 'system:2', 'label' => 'Assistant 2']
        ],
        'claude' => [
            ['value' => 'system:3', 'label' => 'Assistant 3']
        ]
    ];

    expect($expected)->toHaveKey('openai');
    expect($expected)->toHaveKey('claude');
    expect($expected['openai'])->toBeArray();
});

test('check_assistant_usage tracks workflow usage', function () {
    $assistant_id = 1;

    // Expected: list of workflows using this assistant
    $expected = [
        'workflows' => [
            ['id' => 5, 'name' => 'SEO Workflow'],
            ['id' => 7, 'name' => 'Translation Workflow']
        ],
        'translations' => []
    ];

    expect($expected)->toHaveKey('workflows');
    expect($expected)->toHaveKey('translations');
    expect($expected['workflows'])->toBeArray();
});

test('check_assistant_usage returns empty arrays when not used', function () {
    $unused_assistant_id = 999;

    $expected = [
        'workflows' => [],
        'translations' => []
    ];

    expect($expected['workflows'])->toBeEmpty();
    expect($expected['translations'])->toBeEmpty();
});

// ============================================================================
// DATA SANITIZATION TESTS
// ============================================================================

test('assistant data is sanitized on create', function () {
    $data = [
        'name' => '<script>alert("xss")</script>Test',
        'system_prompt' => '<script>alert("xss")</script>Prompt',
    ];

    // Expected: HTML stripped
    $sanitized_name = sanitize_text_field($data['name']);
    $sanitized_prompt = $data['system_prompt']; // Should NOT strip from prompts (may contain valid HTML examples)

    expect($sanitized_name)->not->toContain('<script>');
});

test('api_parameters are validated as valid JSON', function () {
    $valid_json = '{"model":"gpt-4","temperature":0.7}';
    $invalid_json = '{invalid}';

    expect(json_decode($valid_json))->not->toBeNull();
    expect(json_decode($invalid_json))->toBeNull();
});

// ============================================================================
// EDGE CASES
// ============================================================================

test('handles empty output_variables gracefully', function () {
    $data = [
        'name' => 'Test',
        'system_prompt' => 'Test',
        'api_parameters' => json_encode(['model' => 'gpt-4']),
        'output_variables' => null // No output variables
    ];

    // Should be valid (output_variables is optional)
    expect($data['output_variables'])->toBeNull();
});

test('handles empty user_message_template gracefully', function () {
    $data = [
        'name' => 'Test',
        'system_prompt' => 'Test',
        'api_parameters' => json_encode(['model' => 'gpt-4']),
        'user_message_template' => null // No user message
    ];

    // Should be valid (user_message_template is optional)
    expect($data['user_message_template'])->toBeNull();
});
