<?php
/**
 * PolyTrans Bootstrap
 * 
 * Handles autoloading and initialization of the plugin.
 * This class sets up PSR-4 autoloading and backward compatibility.
 * 
 * @package PolyTrans
 * @since 1.4.0
 */

namespace PolyTrans;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap
{
    /**
     * Initialize the plugin
     * 
     * Sets up autoloading and backward compatibility aliases
     */
    public static function init()
    {
        // Register Composer autoloader (includes Twig and our PSR-4 classes)
        self::registerComposerAutoloader();
        
        // Register backward compatibility aliases
        self::registerCompatibilityAliases();
    }

    /**
     * Register Composer autoloader
     */
    private static function registerComposerAutoloader()
    {
        $autoloadFile = POLYTRANS_PLUGIN_DIR . 'vendor/autoload.php';
        
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
        } else {
            // Log error if autoloader is missing
            if (function_exists('error_log')) {
                error_log('PolyTrans: Composer autoloader not found. Run "composer install".');
            }
        }
    }

    /**
     * Register backward compatibility class aliases
     * 
     * Maps old WordPress-style class names to new PSR-4 namespaced classes.
     * This ensures existing code continues to work during the migration.
     */
    private static function registerCompatibilityAliases()
    {
        $compatibilityFile = __DIR__ . '/Compatibility.php';
        
        if (file_exists($compatibilityFile)) {
            require_once $compatibilityFile;
        }
    }

    /**
     * Get the plugin version
     * 
     * @return string
     */
    public static function getVersion()
    {
        return defined('POLYTRANS_VERSION') ? POLYTRANS_VERSION : '1.4.0';
    }

    /**
     * Check if plugin is properly initialized
     * 
     * @return bool
     */
    public static function isInitialized()
    {
        return class_exists('Twig\Environment');
    }
}

