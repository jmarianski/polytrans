<?php

/**
 * Translation API Class
 * Handles REST API endpoints for translation functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_API
{

    private static $instance = null;

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
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes()
    {
        // DISABLED: This endpoint updates existing posts instead of creating new ones
        // The new PolyTrans_Translation_Receiver_Extension handles this endpoint now
        /*
        register_rest_route('polytrans/v1', '/translation/receive-post', [
            'methods' => 'POST',
            'callback' => [$this, 'receive_translated_post'],
            'permission_callback' => [$this, 'verify_translation_request'],
        ]);
        */

        register_rest_route('polytrans/v1', '/translation/status/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_translation_status'],
            'permission_callback' => 'is_user_logged_in',
            'args' => [
                'post_id' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);
    }

    /**
     * Verify translation request permissions
     */
    public function verify_translation_request($request)
    {
        $settings = get_option('polytrans_settings', []);
        $secret = $settings['translation_receiver_secret'] ?? '';
        $method = $settings['translation_receiver_secret_method'] ?? 'header_bearer';

        if (!$secret || $method === 'none') {
            return true; // No secret configured, allow all requests
        }

        $provided_secret = '';

        switch ($method) {
            case 'get_param':
                $provided_secret = $request->get_param('secret') ?? '';
                break;
            case 'header_bearer':
                $auth_header = $request->get_header('authorization');
                if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
                    $provided_secret = substr($auth_header, 7);
                }
                break;
            case 'header_custom':
                $provided_secret = $request->get_header('x-polytrans-secret') ?? '';
                break;
            case 'post_param':
                $body = $request->get_json_params();
                $provided_secret = $body['secret'] ?? '';
                break;
        }

        return hash_equals($secret, $provided_secret);
    }

    /**
     * Receive translated post
     */
    public function receive_translated_post($request)
    {
        $body = $request->get_json_params();

        if (!$body) {
            return new WP_Error('invalid_json', 'Invalid JSON data', ['status' => 400]);
        }

        $required_fields = ['source_language', 'target_language', 'original_post_id', 'translated'];
        foreach ($required_fields as $field) {
            if (!isset($body[$field])) {
                return new WP_Error('missing_field', "Missing required field: $field", ['status' => 400]);
            }
        }

        $source_language = sanitize_text_field($body['source_language']);
        $target_language = sanitize_text_field($body['target_language']);
        $original_post_id = intval($body['original_post_id']);
        $translated = $body['translated'];

        // Verify original post exists
        $original_post = get_post($original_post_id);
        if (!$original_post) {
            return new WP_Error('post_not_found', 'Original post not found', ['status' => 404]);
        }

        // Log the received translation
        error_log("[polytrans] Received translation for post $original_post_id from $source_language to $target_language");

        // Create or update translated post
        $result = $this->create_translated_post($original_post, $translated, $source_language, $target_language);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update translation status
        $this->update_translation_status($original_post_id, $target_language, 'completed', $result['post_id']);

        // Send reviewer notification if needed
        $this->maybe_send_reviewer_notification($result['post_id'], $target_language);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Translation received and processed successfully',
            'post_id' => $result['post_id'],
            'edit_link' => get_edit_post_link($result['post_id'], 'edit'),
        ]);
    }

    /**
     * Create translated post
     */
    private function create_translated_post($original_post, $translated, $source_language, $target_language)
    {
        $settings = get_option('polytrans_settings', []);
        $lang_settings = $settings[$target_language] ?? [];
        $post_status = $lang_settings['status'] ?? 'draft';

        // Handle "same as source" status
        if ($post_status === 'source') {
            $post_status = $original_post->post_status;
        }

        $translated_post_data = [
            'post_title' => wp_kses_post($translated['title'] ?? $original_post->post_title),
            'post_content' => wp_kses_post($translated['content'] ?? $original_post->post_content),
            'post_excerpt' => wp_kses_post($translated['excerpt'] ?? $original_post->post_excerpt),
            'post_status' => $post_status,
            'post_type' => $original_post->post_type,
            'post_author' => $original_post->post_author,
            'post_category' => wp_get_post_categories($original_post->ID),
        ];

        // Check if translation already exists
        $existing_translation_id = get_post_meta($original_post->ID, '_polytrans_translation_target_' . $target_language, true);

        if ($existing_translation_id && get_post($existing_translation_id)) {
            // Update existing translation
            $translated_post_data['ID'] = $existing_translation_id;
            $post_id = wp_update_post($translated_post_data);
        } else {
            // Create new translation
            $post_id = wp_insert_post($translated_post_data);

            if ($post_id && !is_wp_error($post_id)) {
                // Set up translation relationship
                update_post_meta($original_post->ID, '_polytrans_translation_target_' . $target_language, $post_id);
                update_post_meta($post_id, 'polytrans_is_translation_target', true);
                update_post_meta($post_id, 'polytrans_translation_source', $original_post->ID);
                update_post_meta($post_id, 'polytrans_translation_lang', $target_language);

                // Set language with Polylang if available
                if (function_exists('pll_set_post_language')) {
                    pll_set_post_language($post_id, $target_language);

                    // Set up translation relationships
                    $translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($original_post->ID) : [];
                    $translations[$target_language] = $post_id;
                    if (function_exists('pll_save_post_translations')) {
                        pll_save_post_translations($translations);
                    }
                }
            }
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Handle meta fields if provided
        if (isset($translated['meta']) && is_array($translated['meta'])) {
            foreach ($translated['meta'] as $meta_key => $meta_value) {
                // Only allow specific meta keys for security
                $allowed_meta_keys = POLYTRANS_ALLOWED_RANK_MATH_META_KEYS ?? [];
                if (in_array($meta_key, $allowed_meta_keys)) {
                    update_post_meta($post_id, $meta_key, sanitize_text_field($meta_value));
                }
            }
        }

        // Copy tags and categories if functions exist
        $this->copy_taxonomies($original_post->ID, $post_id, $target_language);

        return [
            'post_id' => $post_id,
            'status' => $post_status,
        ];
    }

    /**
     * Update translation status
     */
    private function update_translation_status($original_post_id, $target_language, $status, $translated_post_id = null)
    {
        $status_key = '_polytrans_translation_status_' . $target_language;
        $log_key = '_polytrans_translation_log_' . $target_language;
        $post_id_key = '_polytrans_translation_target_' . $target_language;

        update_post_meta($original_post_id, $status_key, $status);

        if ($translated_post_id) {
            update_post_meta($original_post_id, $post_id_key, $translated_post_id);
        }

        // Add log entry
        $log = get_post_meta($original_post_id, $log_key, true);
        if (!is_array($log)) $log = [];

        $log[] = [
            'timestamp' => time(),
            'msg' => sprintf(__('Translation %s.', 'polytrans-translation'), $status),
        ];

        update_post_meta($original_post_id, $log_key, $log);

        error_log("[polytrans] Updated translation status for post $original_post_id to $target_language: $status");
    }

    /**
     * Copy taxonomies to translated post
     */
    private function copy_taxonomies($source_post_id, $target_post_id, $target_language)
    {
        $taxonomies = get_object_taxonomies(get_post_type($source_post_id));

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($source_post_id, $taxonomy, ['fields' => 'ids']);

            if (!empty($terms) && !is_wp_error($terms)) {
                $translated_term_ids = [];

                foreach ($terms as $term_id) {
                    $translated_term_id = null;

                    if (function_exists('pll_get_term')) {
                        $translated_term_id = pll_get_term($term_id, $target_language);
                    }

                    if ($translated_term_id) {
                        $translated_term_ids[] = $translated_term_id;
                    } else {
                        // Fallback: use original term if no translation exists
                        $translated_term_ids[] = $term_id;
                    }
                }

                if (!empty($translated_term_ids)) {
                    wp_set_object_terms($target_post_id, $translated_term_ids, $taxonomy);
                }
            }
        }
    }

    /**
     * Maybe send reviewer notification
     */
    private function maybe_send_reviewer_notification($post_id, $target_language)
    {
        $settings = get_option('polytrans_settings', []);
        $lang_settings = $settings[$target_language] ?? [];
        $reviewer_id = $lang_settings['reviewer'] ?? '';

        if ($reviewer_id && $reviewer_id !== 'none') {
            $notifications = PolyTrans_Translation_Notifications::get_instance();
            $notifications->send_reviewer_notification($post_id, $reviewer_id, $target_language);
        }
    }

    /**
     * Get translation status
     */
    public function get_translation_status($request)
    {
        $post_id = $request['post_id'];

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'You do not have permission to view this post\'s translation status', ['status' => 403]);
        }

        $langs_key = '_polytrans_translation_langs';
        $scheduled_langs = get_post_meta($post_id, $langs_key, true);
        if (!is_array($scheduled_langs)) $scheduled_langs = [];

        $status = [];
        foreach ($scheduled_langs as $lang) {
            $status_key = '_polytrans_translation_status_' . $lang;
            $log_key = '_polytrans_translation_log_' . $lang;
            $post_id_key = '_polytrans_translation_target_' . $lang;

            $status[$lang] = [
                'status' => get_post_meta($post_id, $status_key, true),
                'log' => get_post_meta($post_id, $log_key, true),
                'post_id' => get_post_meta($post_id, $post_id_key, true),
            ];
        }

        return rest_ensure_response([
            'post_id' => $post_id,
            'scheduled_languages' => $scheduled_langs,
            'status' => $status,
        ]);
    }
}
