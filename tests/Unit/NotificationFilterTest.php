<?php

use PolyTrans\Core\NotificationFilter;

beforeEach(function () {
    // Clear settings before each test
    delete_option('polytrans_settings');
});

afterEach(function () {
    // Cleanup
    delete_option('polytrans_settings');
});

test('allows all users when no filters are configured', function () {
    $user = (object) [
        'ID' => 1,
        'user_email' => 'external@example.com',
        'roles' => ['contributor']
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeTrue();
});

test('blocks user with disallowed role', function () {
    update_option('polytrans_settings', [
        'notification_allowed_roles' => ['editor', 'administrator']
    ]);
    
    $user = (object) [
        'ID' => 1,
        'user_email' => 'contributor@example.com',
        'roles' => ['contributor']
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeFalse();
});

test('allows user with allowed role', function () {
    update_option('polytrans_settings', [
        'notification_allowed_roles' => ['editor', 'administrator']
    ]);
    
    $user = (object) [
        'ID' => 1,
        'user_email' => 'editor@example.com',
        'roles' => ['editor']
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeTrue();
});

test('blocks user with disallowed email domain', function () {
    update_option('polytrans_settings', [
        'notification_allowed_domains' => ['company.com', 'internal.org']
    ]);
    
    $user = (object) [
        'ID' => 1,
        'user_email' => 'external@gmail.com',
        'roles' => ['editor']
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeFalse();
});

test('allows user with allowed email domain', function () {
    update_option('polytrans_settings', [
        'notification_allowed_domains' => ['company.com', 'internal.org']
    ]);
    
    $user = (object) [
        'ID' => 1,
        'user_email' => 'john@company.com',
        'roles' => ['editor']
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeTrue();
});

test('requires both role AND domain when both filters are set', function () {
    update_option('polytrans_settings', [
        'notification_allowed_roles' => ['editor'],
        'notification_allowed_domains' => ['company.com']
    ]);
    
    // Wrong role, correct domain
    $user1 = (object) [
        'ID' => 1,
        'user_email' => 'contributor@company.com',
        'roles' => ['contributor']
    ];
    expect(NotificationFilter::should_notify_user($user1))->toBeFalse();
    
    // Correct role, wrong domain
    $user2 = (object) [
        'ID' => 2,
        'user_email' => 'editor@gmail.com',
        'roles' => ['editor']
    ];
    expect(NotificationFilter::should_notify_user($user2))->toBeFalse();
    
    // Both correct
    $user3 = (object) [
        'ID' => 3,
        'user_email' => 'editor@company.com',
        'roles' => ['editor']
    ];
    expect(NotificationFilter::should_notify_user($user3))->toBeTrue();
});

test('handles user with multiple roles', function () {
    update_option('polytrans_settings', [
        'notification_allowed_roles' => ['editor']
    ]);
    
    $user = (object) [
        'ID' => 1,
        'user_email' => 'user@example.com',
        'roles' => ['contributor', 'editor', 'author'] // Has editor among roles
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeTrue();
});

test('sanitizes domains correctly', function () {
    $input = 'example.com, https://www.company.org, test.net/path, invalid domain, another.com';
    $sanitized = NotificationFilter::sanitize_domains($input);
    
    expect($sanitized)->toBe(['example.com', 'company.org', 'test.net', 'another.com']);
});

test('sanitizes domains from array', function () {
    $input = ['example.com', 'https://www.company.org', 'test.net'];
    $sanitized = NotificationFilter::sanitize_domains($input);
    
    expect($sanitized)->toBe(['example.com', 'company.org', 'test.net']);
});

test('handles invalid domain input', function () {
    expect(NotificationFilter::sanitize_domains('invalid'))->toBe([]);
    expect(NotificationFilter::sanitize_domains('no spaces allowed'))->toBe([]);
    expect(NotificationFilter::sanitize_domains(123))->toBe([]);
});

test('domain matching is case-insensitive', function () {
    update_option('polytrans_settings', [
        'notification_allowed_domains' => ['Company.COM']
    ]);
    
    $user = (object) [
        'ID' => 1,
        'user_email' => 'user@company.com',
        'roles' => ['editor']
    ];
    
    expect(NotificationFilter::should_notify_user($user))->toBeTrue();
});

