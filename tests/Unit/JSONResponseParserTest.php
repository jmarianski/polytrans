<?php

/**
 * Tests for PolyTrans_JSON_Response_Parser
 * 
 * Tests robust JSON extraction and schema-based validation/coercion
 */

// Load the class if not already loaded
if (!class_exists('PolyTrans_JSON_Response_Parser')) {
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__, 2) . '/');
    }
    require_once dirname(__DIR__, 2) . '/includes/postprocessing/class-json-response-parser.php';
}

beforeEach(function () {
    $this->parser = new PolyTrans_JSON_Response_Parser();
});

// ============================================================================
// JSON Extraction Tests
// ============================================================================

test('extracts clean JSON', function () {
    $response = '{"analysis": "Good post", "score": 8}';
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['analysis'])->toBe('Good post')
        ->and($result['score'])->toBe(8);
});

test('extracts JSON from ```json``` code block', function () {
    $response = "Here's your result:\n```json\n{\"analysis\": \"Good post\", \"score\": 8}\n```\nHope this helps!";
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['analysis'])->toBe('Good post')
        ->and($result['score'])->toBe(8);
});

test('extracts JSON from generic ``` code block', function () {
    $response = "```\n{\"analysis\": \"Good post\", \"score\": 8}\n```";
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['analysis'])->toBe('Good post');
});

test('extracts JSON embedded in text', function () {
    $response = "Sure! Here's the analysis: {\"analysis\": \"Good post\", \"score\": 8} Let me know!";
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['analysis'])->toBe('Good post');
});

test('extracts nested JSON objects', function () {
    $response = '{"meta": {"seo": {"title": "SEO Title", "description": "SEO Desc"}}, "score": 9}';
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['meta'])->toBeArray()
        ->and($result['meta']['seo']['title'])->toBe('SEO Title');
});

test('extracts JSON with arrays', function () {
    $response = '{"suggestions": ["Add images", "Fix typo", "Improve SEO"], "score": 7}';
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['suggestions'])->toBeArray()
        ->and($result['suggestions'])->toHaveCount(3)
        ->and($result['suggestions'][0])->toBe('Add images');
});

test('returns null for invalid JSON', function () {
    $response = "This is just plain text, no JSON here!";
    $result = $this->parser->extract_json($response);

    expect($result)->toBeNull();
});

test('extracts first valid JSON from multiple objects', function () {
    $response = '{"first": "object"} and {"second": "object"}';
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('first');
});

test('extracts JSON with escaped quotes', function () {
    $response = '{"analysis": "Post says \"hello world\"", "score": 8}';
    $result = $this->parser->extract_json($response);

    expect($result)->toBeArray()
        ->and($result['analysis'])->toContain('hello world');
});

// ============================================================================
// Schema Validation Tests
// ============================================================================

test('validates schema with perfect match', function () {
    $response = '{"analysis": "Good post", "score": 8, "suggestions": ["tip1", "tip2"]}';
    $schema = [
        'analysis' => 'string',
        'score' => 'number',
        'suggestions' => 'array'
    ];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['analysis'])->toBe('Good post')
        ->and($result['data']['score'])->toBe(8)
        ->and($result['data']['suggestions'])->toBeArray()
        ->and($result['warnings'])->toBeEmpty();
});

test('sets missing fields to null', function () {
    $response = '{"analysis": "Good post"}';
    $schema = [
        'analysis' => 'string',
        'score' => 'number',
        'suggestions' => 'array'
    ];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['analysis'])->toBe('Good post')
        ->and($result['data']['score'])->toBeNull()
        ->and($result['data']['suggestions'])->toBeNull()
        ->and($result['warnings'])->toHaveCount(2);
});

test('coerces string to number', function () {
    $response = '{"score": "8"}';
    $schema = ['score' => 'number'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['score'])->toBeInt()
        ->and($result['data']['score'])->toBe(8)
        ->and($result['warnings'])->toHaveCount(1);
});

test('coerces float string to number', function () {
    $response = '{"score": "8.5"}';
    $schema = ['score' => 'number'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['score'])->toBeFloat()
        ->and($result['data']['score'])->toBe(8.5);
});

test('wraps single value in array', function () {
    $response = '{"suggestions": "Add images"}';
    $schema = ['suggestions' => 'array'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['suggestions'])->toBeArray()
        ->and($result['data']['suggestions'])->toBe(['Add images'])
        ->and($result['warnings'])->toHaveCount(1);
});

test('coerces string to boolean', function () {
    $response = '{"approved": "true", "rejected": "false", "pending": "yes"}';
    $schema = [
        'approved' => 'boolean',
        'rejected' => 'boolean',
        'pending' => 'boolean'
    ];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['approved'])->toBeTrue()
        ->and($result['data']['rejected'])->toBeFalse()
        ->and($result['data']['pending'])->toBeTrue();
});

test('preserves extra fields not in schema', function () {
    $response = '{"analysis": "Good", "extra_field": "Bonus", "another": 123}';
    $schema = ['analysis' => 'string'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['analysis'])->toBe('Good')
        ->and($result['data']['extra_field'])->toBe('Bonus')
        ->and($result['data']['another'])->toBe(123);
});

test('validates nested objects', function () {
    $response = '{"meta": {"title": "Title", "score": "9"}}';
    $schema = ['meta' => 'object'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['meta'])->toBeArray()
        ->and($result['data']['meta']['title'])->toBe('Title');
});

test('fails gracefully with invalid JSON', function () {
    $response = 'Not JSON at all!';
    $schema = ['analysis' => 'string'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeFalse()
        ->and($result)->toHaveKey('error')
        ->and($result['error'])->toContain('Failed to extract JSON');
});

test('returns all data with empty schema', function () {
    $response = '{"any": "field", "works": true, "count": 42}';
    $schema = [];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['any'])->toBe('field')
        ->and($result['data']['works'])->toBeTrue()
        ->and($result['data']['count'])->toBe(42)
        ->and($result['warnings'])->toBeEmpty();
});

test('handles real-world AI commentary', function () {
    $response = "Certainly! I've analyzed the post:\n\n```json\n{\n  \"analysis\": \"Well-structured\",\n  \"score\": 8.5,\n  \"suggestions\": [\n    \"Add more images\",\n    \"Improve meta\"\n  ],\n  \"seo_ready\": true\n}\n```\n\nLet me know if you need clarifications!";
    
    $schema = [
        'analysis' => 'string',
        'score' => 'number',
        'suggestions' => 'array',
        'seo_ready' => 'boolean'
    ];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['analysis'])->toBe('Well-structured')
        ->and($result['data']['score'])->toBe(8.5)
        ->and($result['data']['suggestions'])->toHaveCount(2)
        ->and($result['data']['seo_ready'])->toBeTrue();
});

test('sets impossible type coercion to null', function () {
    $response = '{"score": "not a number"}';
    $schema = ['score' => 'number'];

    $result = $this->parser->parse_with_schema($response, $schema);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['score'])->toBeNull()
        ->and($result['warnings'])->toHaveCount(1);
});

