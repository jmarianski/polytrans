<?php

namespace PolyTrans\Receiver;

/**
 * Extension that handles receiving translated posts from external translation services.
 * This class acts as a REST API endpoint and delegates the actual translation processing
 * to specialized manager classes for better organization and maintainability.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TranslationReceiverExtension
{
    private $coordinator;
    private $security_manager;

    public function __construct()
    {
        $this->coordinator = new TranslationCoordinator();
        $this->security_manager = new Managers\SecurityManager();
    }

    /**
     * Main handler for receiving translated posts from external services.
     * 
     * @param WP_REST_Request $request The REST API request containing translation data
     * @return WP_REST_Response Response with created post ID or error
     */
    public function handle_receive_post($request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new WP_REST_Response(['error' => 'Invalid JSON data'], 400);
        }

        // Extract key parameters for logging
        $original_post_id = $params['original_post_id'] ?? 0;
        $target_language = $params['target_language'] ?? '';
        $source_language = $params['source_language'] ?? '';

        // Use global class with leading backslash (not namespaced)
        \PolyTrans_Logs_Manager::log("Received translation data for post $original_post_id from $source_language to $target_language", "info");

        // Process the translation using the coordinator
        $result = $this->coordinator->process_translation($params);

        if (!$result['success']) {
            $status_code = (isset($result['code']) && $result['code'] === 'missing_data') ? 400 : 500;
            error_log("[polytrans] Translation processing failed: " . $result['error']);
            return new WP_REST_Response(['error' => $result['error']], $status_code);
        }

        error_log("[polytrans] Translation processing succeeded, created post ID: " . $result['created_post_id']);

        // Include detailed information in the response for the sender to update status
        return new WP_REST_Response([
            'created_post_id' => $result['created_post_id'],
            'status' => $result['status'],
            'original_post_id' => $original_post_id,
            'target_language' => $target_language,
            'message' => sprintf(__('Translation successfully created with post ID %d', 'polytrans'), $result['created_post_id'])
        ], 201);
    }

    /**
     * Validates permission for translation receiver endpoints.
     * 
     * @param WP_REST_Request $request The REST API request
     * @return bool True if authorized, false otherwise
     */
    public function permission_callback($request)
    {
        // Validate authentication secret
        if (!$this->security_manager->validate_permission($request)) {
            return false;
        }

        // Validate IP address if configured
        if (!$this->security_manager->validate_ip_address($request)) {
            return false;
        }

        return true;
    }

    /**
     * Registers REST API routes for the translation receiver.
     */
    public function register_routes()
    {
        register_rest_route('polytrans/v1', '/translation/receive-post', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_receive_post'],
            'permission_callback' => [$this, 'permission_callback'],
        ]);
    }

    /**
     * Initializes REST API extension by registering routes.
     */
    public function register_rest_api_extension()
    {
        $this->register_routes();
    }

    /**
     * Registers the extension with WordPress hooks.
     */
    public function register()
    {
        add_action('rest_api_init', [$this, 'register_rest_api_extension']);
    }

    /**
     * Gets the coordinator for external access.
     * 
     * @return PolyTrans_Translation_Coordinator
     */
    public function get_coordinator()
    {
        return $this->coordinator;
    }
}
