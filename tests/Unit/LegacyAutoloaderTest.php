<?php

/**
 * Tests for LegacyAutoloader
 * 
 * @package PolyTrans\Tests\Unit
 */

use PolyTrans\LegacyAutoloader;

describe('LegacyAutoloader', function () {
    
    test('is registered and available', function () {
        expect(class_exists('PolyTrans\LegacyAutoloader'))->toBeTrue();
    });

    test('can get pending migrations list', function () {
        $pending = LegacyAutoloader::getPendingMigrations();
        
        expect($pending)->toBeArray();
        expect($pending)->not->toBeEmpty();
    });

    test('pending migrations include expected classes', function () {
        $pending = LegacyAutoloader::getPendingMigrations();
        
        // Check some key classes are in the list
        expect($pending)->toContain('PolyTrans_Assistant_Manager');
        expect($pending)->toContain('PolyTrans_Workflow_Manager');
        expect($pending)->toContain('PolyTrans_Translation_Handler');
    });

    test('can autoload a legacy class', function () {
        // This should trigger autoloading
        expect(class_exists('PolyTrans_Assistant_Manager'))->toBeTrue();
        expect(class_exists('PolyTrans_Workflow_Manager'))->toBeTrue();
    });

    test('autoloader ignores non-PolyTrans classes', function () {
        // Should not interfere with other classes
        $result = LegacyAutoloader::autoload('SomeOtherClass');
        
        expect($result)->toBeNull();
    });

    test('autoloader ignores unknown PolyTrans classes', function () {
        // Should not throw error for unknown classes
        $result = LegacyAutoloader::autoload('PolyTrans_NonExistent_Class');
        
        expect($result)->toBeNull();
    });

    test('all mapped classes have valid file paths', function () {
        $pending = LegacyAutoloader::getPendingMigrations();
        
        foreach ($pending as $className) {
            // Try to load the class
            expect(class_exists($className))->toBeTrue(
                "Class {$className} should be loadable via autoloader"
            );
        }
    });

    test('has reasonable number of pending migrations', function () {
        $pending = LegacyAutoloader::getPendingMigrations();
        
        // Should have 30-50 classes (we mapped ~40)
        expect(count($pending))->toBeGreaterThan(30);
        expect(count($pending))->toBeLessThan(50);
    });
});

