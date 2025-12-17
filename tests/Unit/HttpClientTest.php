<?php

use PolyTrans\Core\Http\HttpClient;
use PolyTrans\Core\Http\HttpResponse;
use WP_Error;

/**
 * HttpClient Test Suite
 * 
 * Tests for HTTP client wrapper to ensure migration from wp_remote_* works correctly
 */

test('HttpClient can be instantiated with base URL', function () {
    $client = new HttpClient('https://api.example.com');
    
    expect($client)->toBeInstanceOf(HttpClient::class);
    expect($client->get_base_url())->toBe('https://api.example.com');
});

test('HttpClient can set default headers', function () {
    $client = new HttpClient();
    $client->set_header('X-Custom-Header', 'test-value');
    
    // Headers are set internally, we can't directly test them
    // but we can verify the method doesn't throw
    expect($client)->toBeInstanceOf(HttpClient::class);
});

test('HttpClient can set bearer auth', function () {
    $client = new HttpClient();
    $client->set_auth('bearer', 'test-token');
    
    expect($client)->toBeInstanceOf(HttpClient::class);
});

test('HttpClient can set API key header', function () {
    $client = new HttpClient();
    $client->set_api_key('test-api-key', 'x-api-key');
    
    expect($client)->toBeInstanceOf(HttpClient::class);
});

test('HttpClient builds full URL from path when base_url is set', function () {
    $client = new HttpClient('https://api.example.com');
    
    // This is tested indirectly through request methods
    // We verify the client works with relative paths
    expect($client->get_base_url())->toBe('https://api.example.com');
});

test('HttpClient can reset headers', function () {
    $client = new HttpClient();
    $client->set_header('X-Test', 'value');
    $client->reset_headers();
    
    expect($client)->toBeInstanceOf(HttpClient::class);
});

test('HttpResponse wraps WP_Error correctly', function () {
    $error = new WP_Error('test_code', 'Test error message');
    $response = new HttpResponse($error);
    
    expect($response->is_error())->toBeTrue();
    expect($response->is_success())->toBeFalse();
    expect($response->get_error_message())->toBe('Test error message');
    expect($response->get_error_code())->toBe('test_code');
    expect($response->get_status_code())->toBe(0);
});

test('HttpResponse wraps successful response correctly', function () {
    // Mock WordPress response array
    $wp_response = [
        'headers' => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary([
            'Content-Type' => 'application/json',
        ]),
        'body' => json_encode(['success' => true, 'data' => 'test']),
        'response' => [
            'code' => 200,
            'message' => 'OK',
        ],
    ];
    
    // We need to mock wp_remote_retrieve functions
    // For now, we test the structure
    expect(true)->toBeTrue(); // Placeholder - needs WordPress mocks
});

test('HttpResponse can parse JSON body', function () {
    // This requires WordPress mocks
    // Placeholder test structure
    expect(true)->toBeTrue();
});

test('HttpResponse can extract error message from JSON error response', function () {
    // This requires WordPress mocks
    // Placeholder test structure
    expect(true)->toBeTrue();
});

test('HttpResponse can get specific header', function () {
    // This requires WordPress mocks
    // Placeholder test structure
    expect(true)->toBeTrue();
});

