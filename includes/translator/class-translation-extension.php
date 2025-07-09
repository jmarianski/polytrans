<?php

/**
 * Translation Extension Class
 * Handles incoming translation requests from other servers and sends back translated content
 * This is the missing piece that allows one server to translate content for another
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Extension
{
    private static $instance = null;
    private $providers = [];

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->initialize_providers();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Initialize translation providers using the new provider registry
     */
    private function initialize_providers()
    {
        // Load the provider registry
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/interface-translation-provider.php';
        require_once POLYTRANS_PLUGIN_DIR . 'includes/providers/class-provider-registry.php';

        // Get all registered providers from the registry
        $registry = PolyTrans_Provider_Registry::get_instance();
        $this->providers = $registry->get_providers();

        error_log('[polytrans] Translation Extension initialized with providers: ' . implode(', ', array_keys($this->providers)));
    }

    /**
     * Get provider by name
     */
    private function get_provider(string $provider_name)
    {
        $provider = $this->providers[$provider_name] ?? null;

        if (!$provider) {
            error_log("[polytrans] Provider '$provider_name' not found. Available providers: " . implode(', ', array_keys($this->providers)));
        }

        return $provider;
    }

    /**
     * Register REST API routes
     */
    public function register_routes()
    {
        register_rest_route('polytrans/v1', '/translation/translate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_translate'],
            'permission_callback' => [$this, 'permission_callback'],
        ]);
    }

    /**
     * Handle translation request
     */
    public function handle_translate($request)
    {
        error_log("[polytrans] handleTranslate called");

        $params = $request->get_json_params();
        $source_lang = $params['source_language'] ?? 'auto';
        $target_lang = $params['target_language'] ?? 'en';
        $original_post_id = $params['original_post_id'] ?? null;
        $to_translate = $params['toTranslate'] ?? [];
        $target_endpoint = $params['target_endpoint'] ?? null;

        if (!$target_endpoint) {
            error_log("[polytrans] handleTranslate error: target_endpoint required");
            return new WP_REST_Response(['error' => 'target_endpoint required'], 400);
        }

        // Get settings and determine provider
        $settings = get_option('polytrans_settings', []);
        $translation_provider = $settings['translation_provider'] ?? 'google';

        error_log("[polytrans] Using translation provider: $translation_provider");

        // Get the provider
        $provider = $this->get_provider($translation_provider);
        if (!$provider) {
            error_log("[polytrans] Unknown translation provider: $translation_provider");
            return new WP_REST_Response(['error' => "Unknown translation provider: $translation_provider"], 400);
        }

        // Check if provider is configured
        if (!$provider->is_configured($settings)) {
            error_log("[polytrans] Translation provider $translation_provider is not properly configured");
            return new WP_REST_Response(['error' => "Translation provider $translation_provider is not properly configured"], 400);
        }

        // Perform translation
        $result = $provider->translate($to_translate, $source_lang, $target_lang, $settings);

        if (!$result['success']) {
            error_log("[polytrans] Translation failed: " . $result['error']);
            return new WP_REST_Response(['error' => $result['error']], 500);
        }

        // Send result to target endpoint
        $payload = [
            'source_language' => $source_lang,
            'target_language' => $target_lang,
            'original_post_id' => $original_post_id,
            'translated' => $result['translated_content']
        ];

        $this->post_to_target($target_endpoint, $payload);

        error_log("[polytrans] Translation finished for $source_lang->$target_lang using $translation_provider");
        return new WP_REST_Response(['status' => 'sent', 'result' => $payload]);
    }

    /**
     * POST translated data to target endpoint
     */
    private function post_to_target($endpoint, $payload)
    {
        error_log("[polytrans] postToTarget: endpoint=$endpoint, payload=" . json_encode($payload));

        $settings = get_option('polytrans_settings', []);
        $secret = $settings['translation_receiver_secret'] ?? '';
        $secret_method = $settings['translation_receiver_secret_method'] ?? 'header_bearer';

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'sslverify' => (getenv('WP_ENV') === 'prod') ? true : false,
        ];

        if ($secret && $secret_method !== 'none') {
            switch ($secret_method) {
                case 'get_param':
                    $endpoint = add_query_arg('secret', $secret, $endpoint);
                    break;
                case 'header_bearer':
                    $args['headers']['Authorization'] = 'Bearer ' . $secret;
                    break;
                case 'header_custom':
                    $args['headers']['x-polytrans-secret'] = $secret;
                    break;
                case 'post_param':
                    // Add secret to payload
                    $body = json_decode($args['body'], true);
                    $body['secret'] = $secret;
                    $args['body'] = wp_json_encode($body);
                    break;
            }
        }

        $result = wp_remote_post($endpoint, $args);

        if (is_wp_error($result)) {
            error_log("[polytrans] postToTarget error: " . $result->get_error_message());
        } else {
            error_log("[polytrans] postToTarget success: " . wp_remote_retrieve_response_code($result));
        }

        return $result;
    }

    /**
     * Permission callback for translation requests
     */
    public function permission_callback($request)
    {
        $settings = get_option('polytrans_settings', []);
        $expected_secret = $settings['translation_receiver_secret'] ?? '';
        $secret_method = $settings['translation_receiver_secret_method'] ?? 'header_bearer';

        if (empty($expected_secret) || $secret_method === 'none') {
            return true;
        }

        $received_secret = '';

        switch ($secret_method) {
            case 'get_param':
                $received_secret = $request->get_param('secret');
                break;
            case 'header_bearer':
                $auth = $request->get_header('authorization');
                if ($auth && stripos($auth, 'bearer ') === 0) {
                    $received_secret = trim(substr($auth, 7));
                }
                break;
            case 'header_custom':
                $received_secret = $request->get_header('x-polytrans-secret');
                break;
            case 'post_param':
                $params = $request->get_json_params();
                error_log("[polytrans] post_param received params: " . json_encode($params));
                $received_secret = $params['secret'] ?? '';
                break;
        }

        if (!$received_secret || $received_secret !== $expected_secret) {
            error_log('[polytrans] Invalid or missing translation receiver secret (permission callback)');
            return false;
        }

        return true;
    }
}
