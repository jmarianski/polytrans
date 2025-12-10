<?php

/**
 * Tests for Bootstrap class
 * 
 * @package PolyTrans\Tests\Unit
 */

use PolyTrans\Bootstrap;

describe('Bootstrap', function () {
    
    test('can be initialized', function () {
        expect(class_exists('PolyTrans\Bootstrap'))->toBeTrue();
    });

    test('getVersion returns version string', function () {
        $version = Bootstrap::getVersion();
        
        expect($version)->toBeString();
        expect($version)->not->toBeEmpty();
    });

    test('isInitialized checks for Twig', function () {
        $initialized = Bootstrap::isInitialized();
        
        expect($initialized)->toBeTrue('Twig should be loaded via Composer autoloader');
        expect(class_exists('Twig\Environment'))->toBeTrue();
    });

    test('Composer autoloader is registered', function () {
        // Twig should be available if autoloader worked
        expect(class_exists('Twig\Environment'))->toBeTrue();
        expect(class_exists('Twig\Loader\FilesystemLoader'))->toBeTrue();
    });

    test('can call init multiple times safely', function () {
        // Should not throw errors when called multiple times
        Bootstrap::init();
        Bootstrap::init();
        
        expect(true)->toBeTrue();
    });
});

