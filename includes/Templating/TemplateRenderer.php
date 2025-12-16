<?php

/**
 * Template Renderer for PolyTrans
 * 
 * Renders HTML templates using Twig FilesystemLoader
 * Separates presentation logic from PHP code
 * 
 * @package PolyTrans
 * @subpackage Templating
 * @since 1.7.0
 */

namespace PolyTrans\Templating;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Error\Error as TwigError;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateRenderer
{
    /**
     * Twig environment instance for file templates
     *
     * @var Environment|null
     */
    private static ?Environment $twig = null;

    /**
     * Templates directory path
     *
     * @var string
     */
    private static string $templates_dir = '';

    /**
     * Cache directory path
     *
     * @var string
     */
    private static string $cache_dir = '';

    /**
     * Whether to enable debug mode
     *
     * @var bool
     */
    private static bool $debug = false;

    /**
     * Initialize Twig environment for file templates
     *
     * @param array<string, mixed> $options Configuration options
     * @return void
     */
    public static function init(array $options = []): void
    {
        if (null !== self::$twig) {
            return; // Already initialized
        }

        // Configure directories
        $plugin_dir = dirname(__DIR__, 2);
        self::$templates_dir = $options['templates_dir'] ?? $plugin_dir . '/templates';
        self::$cache_dir = $options['cache_dir'] ?? $plugin_dir . '/cache/twig-templates';
        self::$debug = $options['debug'] ?? (defined('WP_DEBUG') && WP_DEBUG); 

        // Enable caching in production
        $use_cache = !self::$debug;

        // Ensure cache directory exists
        if ($use_cache && !file_exists(self::$cache_dir)) {
            if (wp_mkdir_p(self::$cache_dir)) {
                @chmod(self::$cache_dir, 0777);
            } else {
                $use_cache = false;
            }
        }

        // Ensure templates directory exists
        if (!file_exists(self::$templates_dir)) {
            wp_mkdir_p(self::$templates_dir);
        }

        // Initialize Twig with FilesystemLoader
        $loader = new FilesystemLoader(self::$templates_dir);
        self::$twig = new Environment(
            $loader,
            [
                'cache' => $use_cache ? self::$cache_dir : false,
                'debug' => self::$debug,
                'auto_reload' => self::$debug,
                'strict_variables' => false,
                'autoescape' => false, // WordPress handles escaping
            ]
        );

        // Add WordPress functions and filters
        self::add_wordpress_functions();
        self::add_wordpress_filters();
    }

    /**
     * Render a template file
     *
     * @param string $template Template file path (relative to templates directory)
     * @param array<string, mixed> $context Variables available in template
     * @return string Rendered HTML
     */
    public static function render(string $template, array $context = []): string
    {
        if (null === self::$twig) {
            self::init();
        }

        try {
            $return = self::$twig->render($template, $context);
            return $return;
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[PolyTrans] Template rendering failed: %s. Template: %s',
                $e->getMessage(),
                $template
            ));
            if (self::$debug) {
                return sprintf(
                    '<!-- Template Error: %s -->',
                    esc_html($e->getMessage())
                );
            }
            return '';
        }
    }

    /**
     * Add WordPress functions to Twig
     *
     * @return void
     */
    private static function add_wordpress_functions(): void
    {
        if (null === self::$twig) {
            return;
        }

        // Escaping functions
        self::$twig->addFunction(new \Twig\TwigFunction('esc_html', 'esc_html'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_attr', 'esc_attr'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_url', 'esc_url'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_js', 'esc_js'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_textarea', 'esc_textarea'));
        self::$twig->addFunction(new \Twig\TwigFunction('wp_kses_post', 'wp_kses_post'));

        // Translation functions
        self::$twig->addFunction(new \Twig\TwigFunction('__', '__'));
        self::$twig->addFunction(new \Twig\TwigFunction('_e', '_e'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_html__', 'esc_html__'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_html_e', 'esc_html_e'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_attr__', 'esc_attr__'));
        self::$twig->addFunction(new \Twig\TwigFunction('esc_attr_e', 'esc_attr_e'));

        // URL functions
        self::$twig->addFunction(new \Twig\TwigFunction('admin_url', 'admin_url'));
        self::$twig->addFunction(new \Twig\TwigFunction('home_url', 'home_url'));
        self::$twig->addFunction(new \Twig\TwigFunction('site_url', 'site_url'));

        // WordPress utility functions
        self::$twig->addFunction(new \Twig\TwigFunction('selected', 'selected'));
        self::$twig->addFunction(new \Twig\TwigFunction('checked', 'checked'));
        self::$twig->addFunction(new \Twig\TwigFunction('disabled', 'disabled'));
        self::$twig->addFunction(new \Twig\TwigFunction('wp_nonce_field', 'wp_nonce_field'));
        self::$twig->addFunction(new \Twig\TwigFunction('wp_create_nonce', 'wp_create_nonce'));

        // Date functions
        self::$twig->addFunction(new \Twig\TwigFunction('mysql2date', 'mysql2date'));
        self::$twig->addFunction(new \Twig\TwigFunction('get_option', 'get_option'));

        // JSON functions
        self::$twig->addFunction(new \Twig\TwigFunction('wp_json_encode', 'wp_json_encode'));

        // Custom helper functions
        self::$twig->addFunction(new \Twig\TwigFunction('polytrans_admin_url', function ($path = '') {
            return admin_url('admin.php?page=' . $path);
        }));
    }

    /**
     * Add WordPress filters to Twig
     *
     * @return void
     */
    private static function add_wordpress_filters(): void
    {
        if (null === self::$twig) {
            return;
        }

        // Escaping filters
        self::$twig->addFilter(new \Twig\TwigFilter('esc_html', 'esc_html'));
        self::$twig->addFilter(new \Twig\TwigFilter('esc_attr', 'esc_attr'));
        self::$twig->addFilter(new \Twig\TwigFilter('esc_url', 'esc_url'));
        self::$twig->addFilter(new \Twig\TwigFilter('esc_js', 'esc_js'));
        self::$twig->addFilter(new \Twig\TwigFilter('esc_textarea', 'esc_textarea'));

        // WordPress content filters
        self::$twig->addFilter(new \Twig\TwigFilter('wp_kses', 'wp_kses'));
        self::$twig->addFilter(new \Twig\TwigFilter('wp_kses_post', 'wp_kses_post'));
        self::$twig->addFilter(new \Twig\TwigFilter('wpautop', 'wpautop'));
        self::$twig->addFilter(new \Twig\TwigFilter('wptexturize', 'wptexturize'));

        // String manipulation filters
        self::$twig->addFilter(new \Twig\TwigFilter('capitalize', function($string) {
            return ucfirst($string);
        }));
    }

    /**
     * Get templates directory path
     *
     * @return string
     */
    public static function get_templates_dir(): string
    {
        if (empty(self::$templates_dir)) {
            self::init();
        }
        return self::$templates_dir;
    }
}

