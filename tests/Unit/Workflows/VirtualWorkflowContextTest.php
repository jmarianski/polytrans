<?php

declare(strict_types=1);

namespace PolyTrans\Tests\Unit\Workflows;

use PolyTrans\Workflows\Context\VirtualWorkflowContext;

describe('VirtualWorkflowContext', function () {

    it('creates from payload', function () {
        $payload = [
            'post' => [
                'title' => 'Test Title',
                'content' => 'Test content',
            ],
            'source_language' => 'en',
            'target_language' => 'de',
        ];

        $context = VirtualWorkflowContext::from_payload($payload);

        expect($context->is_virtual())->toBeTrue();
        expect($context->get_source_language())->toBe('en');
        expect($context->get_target_language())->toBe('de');
        expect($context->get_post_id())->toBeNull();
    });

    it('gets values using dot notation', function () {
        $context = VirtualWorkflowContext::create([
            'post' => [
                'title' => 'Hello World',
                'content' => '<p>Content here</p>',
            ],
            'meta' => [
                'custom_field' => 'value123',
            ],
        ], 'en', 'pl');

        expect($context->get('post.title'))->toBe('Hello World');
        expect($context->get('post.content'))->toBe('<p>Content here</p>');
        expect($context->get('meta.custom_field'))->toBe('value123');
        expect($context->get('nonexistent.path'))->toBeNull();
    });

    it('sets values using dot notation', function () {
        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'Original'],
        ], 'en', 'de');

        $context->set('post.title', 'Modified');
        $context->set('post.excerpt', 'New excerpt');
        $context->set('meta.new_field', 'new value');

        expect($context->get('post.title'))->toBe('Modified');
        expect($context->get('post.excerpt'))->toBe('New excerpt');
        expect($context->get('meta.new_field'))->toBe('new value');
    });

    it('checks path existence', function () {
        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'Test'],
        ], 'en', 'de');

        expect($context->has('post.title'))->toBeTrue();
        expect($context->has('post.content'))->toBeFalse();
        expect($context->has('nonexistent'))->toBeFalse();
    });

    it('deletes values', function () {
        $context = VirtualWorkflowContext::create([
            'post' => [
                'title' => 'Test',
                'content' => 'Content',
            ],
        ], 'en', 'de');

        $context->delete('post.content');

        expect($context->has('post.content'))->toBeFalse();
        expect($context->has('post.title'))->toBeTrue();
    });

    it('exports all data', function () {
        $data = [
            'post' => ['title' => 'Test'],
            'meta' => ['key' => 'value'],
        ];

        $context = VirtualWorkflowContext::create($data, 'en', 'de');
        $context->set('post.title', 'Modified');

        $exported = $context->export();

        expect($exported['post']['title'])->toBe('Modified');
        expect($exported['meta']['key'])->toBe('value');
    });

    it('creates immutable copy with merged data', function () {
        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'Original'],
        ], 'en', 'de');

        $newContext = $context->with_data([
            'post' => ['content' => 'New content'],
        ]);

        // Original unchanged
        expect($context->get('post.title'))->toBe('Original');
        expect($context->has('post.content'))->toBeFalse();

        // New context has both
        expect($newContext->get('post.title'))->toBe('Original');
        expect($newContext->get('post.content'))->toBe('New content');
    });

    it('detects changes from original', function () {
        $original = [
            'post' => [
                'title' => 'Original Title',
                'content' => 'Original Content',
            ],
        ];

        $context = VirtualWorkflowContext::create($original, 'en', 'de');
        $context->set('post.title', 'Modified Title');
        $context->set('post.excerpt', 'New Excerpt');

        $changes = $context->get_changes($original);

        expect($changes)->toHaveKey('post');
        expect($changes['post']['title'])->toBe('Modified Title');
        expect($changes['post']['excerpt'])->toBe('New Excerpt');
        expect($changes['post'])->not->toHaveKey('content'); // Unchanged
    });

    it('manages services', function () {
        $context = VirtualWorkflowContext::create([], 'en', 'de');

        expect($context->has_service('TestService'))->toBeFalse();
        expect($context->get_service('TestService'))->toBeNull();

        $mockService = new \stdClass();
        $mockService->name = 'test';

        $context->register_service('TestService', $mockService);

        expect($context->has_service('TestService'))->toBeTrue();
        expect($context->get_service('TestService'))->toBe($mockService);
    });

    it('handles nested array access', function () {
        $context = VirtualWorkflowContext::create([
            'taxonomy' => [
                'categories' => [
                    ['slug' => 'news', 'name' => 'News'],
                    ['slug' => 'tech', 'name' => 'Technology'],
                ],
            ],
        ], 'en', 'de');

        $categories = $context->get('taxonomy.categories');

        expect($categories)->toBeArray();
        expect($categories)->toHaveCount(2);
        expect($categories[0]['slug'])->toBe('news');

        // Access specific item
        expect($context->get('taxonomy.categories.0.slug'))->toBe('news');
        expect($context->get('taxonomy.categories.1.name'))->toBe('Technology');
    });

});
