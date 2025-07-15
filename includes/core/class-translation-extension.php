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
        PolyTrans_Logs_Manager::log("handleTranslate called", "info");

        $params = $request->get_json_params();
        $source_lang = $params['source_language'] ?? 'auto';
        $target_lang = $params['target_language'] ?? 'en';
        $original_post_id = $params['original_post_id'] ?? null;
        $to_translate = $params['toTranslate'] ?? [];
        $target_endpoint = $params['target_endpoint'] ?? null;

        if (!$target_endpoint) {
            PolyTrans_Logs_Manager::log("handleTranslate error: target_endpoint required", "info");
            return new WP_REST_Response(['error' => 'target_endpoint required'], 400);
        }

        // Update original post's status and log for external translation tracking
        // This ensures consistent meta updates between local and external pathways
        if ($original_post_id) {
            // Status and log keys for the target language
            $status_key = '_polytrans_translation_status_' . $target_lang;
            $log_key = '_polytrans_translation_log_' . $target_lang;

            // Set status to 'translating' to indicate it's being processed remotely
            update_post_meta($original_post_id, $status_key, 'translating');

            // Initialize log if needed
            $log = get_post_meta($original_post_id, $log_key, true);
            if (!is_array($log)) $log = [];

            // Add a status update log entry
            $log[] = [
                'timestamp' => time(),
                'msg' => __('External translation process started.', 'polytrans')
            ];
            update_post_meta($original_post_id, $log_key, $log);

            PolyTrans_Logs_Manager::log("External translation process started for post $original_post_id from $source_lang to $target_lang", "info");
        }

        // Get settings and determine provider
        $settings = get_option('polytrans_settings', []);
        $translation_provider = $settings['translation_provider'] ?? 'google';

        PolyTrans_Logs_Manager::log("Using translation provider: $translation_provider", "info");

        // Get the provider
        $provider = $this->get_provider($translation_provider);
        if (!$provider) {
            // Update error status for external translation
            if ($original_post_id) {
                $this->update_translation_failure($original_post_id, $target_lang, "Unknown translation provider: $translation_provider");
            }

            PolyTrans_Logs_Manager::log("Unknown translation provider: $translation_provider", "info");
            return new WP_REST_Response(['error' => "Unknown translation provider: $translation_provider"], 400);
        }

        // Check if provider is configured
        if (!$provider->is_configured($settings)) {
            // Update error status for external translation
            if ($original_post_id) {
                $this->update_translation_failure($original_post_id, $target_lang, "Translation provider $translation_provider is not properly configured");
            }

            PolyTrans_Logs_Manager::log("Translation provider $translation_provider is not properly configured", "info");
            return new WP_REST_Response(['error' => "Translation provider $translation_provider is not properly configured"], 400);
        }

        // Perform translation
        $result = $provider->translate($to_translate, $source_lang, $target_lang, $settings);

        if (!$result['success']) {
            // Update error status for external translation
            if ($original_post_id) {
                $this->update_translation_failure($original_post_id, $target_lang, $result['error']);
            }

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

        $response = $this->post_to_target($target_endpoint, $payload);

        // Check if the response from the target endpoint indicates success
        $response_code = wp_remote_retrieve_response_code($response);
        $response_success = ($response_code >= 200 && $response_code < 300);

        // If we're handling an actual post (not just content) and the response failed, mark as failed
        if ($original_post_id && !$response_success && !is_wp_error($response)) {
            $error = wp_remote_retrieve_body($response);
            $this->update_translation_failure($original_post_id, $target_lang, "Failed to deliver translation: $error (HTTP $response_code)");
        }
        // If it's a WP_Error, also mark as failed
        else if ($original_post_id && is_wp_error($response)) {
            $this->update_translation_failure($original_post_id, $target_lang, "Failed to deliver translation: " . $response->get_error_message());
        }

        PolyTrans_Logs_Manager::log("Translation finished for $source_lang->$target_lang using $translation_provider", "info");
        return new WP_REST_Response(['status' => 'sent', 'result' => $payload]);
    }

    /**
     * Helper method to update post meta for failed translations
     * 
     * @param int $post_id Original post ID
     * @param string $target_lang Target language
     * @param string $error_message Error message
     */
    private function update_translation_failure($post_id, $target_lang, $error_message)
    {
        $status_key = '_polytrans_translation_status_' . $target_lang;
        $log_key = '_polytrans_translation_log_' . $target_lang;

        // Update status to failed
        update_post_meta($post_id, $status_key, 'failed');

        // Add failure log entry
        $log = get_post_meta($post_id, $log_key, true);
        if (!is_array($log)) $log = [];

        $log[] = [
            'timestamp' => time(),
            'msg' => sprintf(__('Translation failed: %s', 'polytrans'), $error_message)
        ];

        update_post_meta($post_id, $log_key, $log);
        PolyTrans_Logs_Manager::log("External translation failed for post $post_id: $error_message", "info");
    }

    /**
     * POST translated data to target endpoint
     * 
     * @param string $endpoint Target endpoint URL
     * @param array $payload Translation payload
     * @return mixed Response from wp_remote_post
     */
    private function post_to_target($endpoint, $payload)
    {
        error_log("[polytrans] postToTarget: endpoint=$endpoint, payload=" . json_encode($payload));

        // Extract data from payload for potential status updates
        $original_post_id = $payload['original_post_id'] ?? null;
        $target_language = $payload['target_language'] ?? null;

        $settings = get_option('polytrans_settings', []);
        $secret = $settings['translation_receiver_secret'] ?? '';
        $secret_method = $settings['translation_receiver_secret_method'] ?? 'header_bearer';
        $custom_header_name = $settings['translation_receiver_secret_custom_header'] ?? 'x-polytrans-secret';

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
                    $args['headers'][$custom_header_name] = $secret;
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

            // Update the original post's status if we have post ID and language info
            if ($original_post_id && $target_language) {
                $this->update_translation_failure($original_post_id, $target_language, "Network error: " . $result->get_error_message());
            }
        } else {
            $response_code = wp_remote_retrieve_response_code($result);
            $response_body = wp_remote_retrieve_body($result);
            $success = ($response_code >= 200 && $response_code < 300);

            // If successful response from receiver endpoint (201 Created)
            if ($success && $original_post_id && $target_language) {
                try {
                    // Try to parse the response body for more detailed info
                    $response_data = json_decode($response_body, true);
                    $created_post_id = $response_data['created_post_id'] ?? 0;

                    if ($created_post_id) {
                        // Update status key to completed
                        $status_key = '_polytrans_translation_status_' . $target_language;
                        update_post_meta($original_post_id, $status_key, 'completed');

                        // Set a completion timestamp
                        update_post_meta($original_post_id, '_polytrans_translation_completed_' . $target_language, time());

                        // Store the translated post ID reference (both keys for compatibility)
                        update_post_meta($original_post_id, '_polytrans_translation_target_' . $target_language, $created_post_id);
                        update_post_meta($original_post_id, '_polytrans_translation_post_id_' . $target_language, $created_post_id);

                        // Add completion log entry
                        $log_key = '_polytrans_translation_log_' . $target_language;
                        $log = get_post_meta($original_post_id, $log_key, true);
                        if (!is_array($log)) $log = [];

                        $log[] = [
                            'timestamp' => time(),
                            'msg' => sprintf(
                                __('Translation completed successfully. New post ID: <a href="%s">%d</a>', 'polytrans'),
                                esc_url(admin_url('post.php?post=' . $created_post_id . '&action=edit')),
                                $created_post_id
                            )
                        ];

                        update_post_meta($original_post_id, $log_key, $log);
                        PolyTrans_Logs_Manager::log("External translation completed successfully for post $original_post_id -> $created_post_id", "info");
                    }
                } catch (Exception $e) {
                    error_log("[polytrans] Error processing translation response: " . $e->getMessage());
                }
            }
            // If failed response, update the failure status
            else if (!$success && $original_post_id && $target_language) {
                $this->update_translation_failure($original_post_id, $target_language, "Failed to deliver translation: HTTP $response_code - $response_body");
            }
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
        $custom_header_name = $settings['translation_receiver_secret_custom_header'] ?? 'x-polytrans-secret';

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
                $received_secret = $request->get_header($custom_header_name);
                break;
            case 'post_param':
                $params = $request->get_json_params();
                error_log("[polytrans] post_param received params: " . json_encode($params));
                $received_secret = $params['secret'] ?? '';
                break;
        }

        if (!$received_secret || $received_secret !== $expected_secret) {
            PolyTrans_Logs_Manager::log("Invalid or missing translation receiver secret (permission callback)", "info");
            return false;
        }

        return true;
    }
}
