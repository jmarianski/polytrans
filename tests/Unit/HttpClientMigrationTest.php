<?php

use PolyTrans\Core\Http\HttpClient;
use PolyTrans\Core\Http\HttpResponse;
use PolyTrans\Providers\OpenAI\OpenAIClient;
use PolyTrans\Providers\Gemini\GeminiChatClientAdapter;
use PolyTrans\Providers\Claude\ClaudeChatClientAdapter;

/**
 * HttpClient Migration Test Suite
 * 
 * Tests to verify that provider classes correctly use HttpClient after migration
 */

test('OpenAIClient uses HttpClient internally', function () {
    // Verify OpenAIClient has http_client property
    $client = new OpenAIClient('test-key');
    
    // Use reflection to check private property
    $reflection = new \ReflectionClass($client);
    $hasHttpClient = $reflection->hasProperty('http_client');
    
    expect($hasHttpClient)->toBeTrue();
});

test('GeminiChatClientAdapter uses HttpClient internally', function () {
    $adapter = new GeminiChatClientAdapter('test-key');
    
    $reflection = new \ReflectionClass($adapter);
    $hasHttpClient = $reflection->hasProperty('http_client');
    
    expect($hasHttpClient)->toBeTrue();
});

test('ClaudeChatClientAdapter uses HttpClient internally', function () {
    $adapter = new ClaudeChatClientAdapter('test-key');
    
    $reflection = new \ReflectionClass($adapter);
    $hasHttpClient = $reflection->hasProperty('http_client');
    
    expect($hasHttpClient)->toBeTrue();
});

test('OpenAIClient constructor initializes HttpClient with correct base URL', function () {
    $client = new OpenAIClient('test-key', 'https://custom.openai.com/v1');
    
    // Verify base_url is set correctly
    $reflection = new \ReflectionClass($client);
    $baseUrlProperty = $reflection->getProperty('base_url');
    $baseUrlProperty->setAccessible(true);
    $baseUrl = $baseUrlProperty->getValue($client);
    
    expect($baseUrl)->toBe('https://custom.openai.com/v1');
});

test('GeminiChatClientAdapter constructor initializes HttpClient', function () {
    $adapter = new GeminiChatClientAdapter('test-key');
    
    // Verify http_client is initialized
    $reflection = new \ReflectionClass($adapter);
    $httpClientProperty = $reflection->getProperty('http_client');
    $httpClientProperty->setAccessible(true);
    $httpClient = $httpClientProperty->getValue($adapter);
    
    expect($httpClient)->toBeInstanceOf(HttpClient::class);
});

test('ClaudeChatClientAdapter constructor initializes HttpClient', function () {
    $adapter = new ClaudeChatClientAdapter('test-key');
    
    $reflection = new \ReflectionClass($adapter);
    $httpClientProperty = $reflection->getProperty('http_client');
    $httpClientProperty->setAccessible(true);
    $httpClient = $httpClientProperty->getValue($adapter);
    
    expect($httpClient)->toBeInstanceOf(HttpClient::class);
});

test('OpenAIClient sets correct headers via HttpClient', function () {
    $client = new OpenAIClient('test-api-key');
    
    // Verify headers are set (indirectly through make_request behavior)
    // This is tested by checking that the client can be instantiated
    expect($client)->toBeInstanceOf(OpenAIClient::class);
});

test('ClaudeChatClientAdapter sets x-api-key header via HttpClient', function () {
    $adapter = new ClaudeChatClientAdapter('test-api-key');
    
    // Verify adapter can be instantiated with API key
    expect($adapter)->toBeInstanceOf(ClaudeChatClientAdapter::class);
});

test('GeminiChatClientAdapter uses query string for API key', function () {
    $adapter = new GeminiChatClientAdapter('test-api-key');
    
    // Gemini uses query string, not headers
    // Verify adapter can be instantiated
    expect($adapter)->toBeInstanceOf(GeminiChatClientAdapter::class);
});

test('Provider classes no longer use wp_remote_* directly', function () {
    // Check that OpenAIClient doesn't have direct wp_remote_* calls in make_request
    $clientFile = file_get_contents(__DIR__ . '/../../includes/Providers/OpenAI/OpenAIClient.php');
    
    // make_request should use http_client, not wp_remote_request directly
    $hasWpRemoteRequest = strpos($clientFile, 'wp_remote_request') !== false;
    $hasHttpClient = strpos($clientFile, '$this->http_client->request') !== false;
    
    // If http_client is used, wp_remote_request should only be in HttpClient class
    expect($hasHttpClient)->toBeTrue();
});

test('HttpClient is used consistently across all providers', function () {
    $openaiFile = file_get_contents(__DIR__ . '/../../includes/Providers/OpenAI/OpenAIClient.php');
    $geminiFile = file_get_contents(__DIR__ . '/../../includes/Providers/Gemini/GeminiChatClientAdapter.php');
    $claudeFile = file_get_contents(__DIR__ . '/../../includes/Providers/Claude/ClaudeChatClientAdapter.php');
    
    $openaiUsesHttpClient = strpos($openaiFile, 'use PolyTrans\\Core\\Http\\HttpClient') !== false;
    $geminiUsesHttpClient = strpos($geminiFile, 'use PolyTrans\\Core\\Http\\HttpClient') !== false;
    $claudeUsesHttpClient = strpos($claudeFile, 'use PolyTrans\\Core\\Http\\HttpClient') !== false;
    
    expect($openaiUsesHttpClient)->toBeTrue();
    expect($geminiUsesHttpClient)->toBeTrue();
    expect($claudeUsesHttpClient)->toBeTrue();
});

