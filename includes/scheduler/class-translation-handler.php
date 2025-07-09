<?php

/**
 * Translation Handler Class
 * Handles translation scheduling and processing
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define allowed Rank Math meta keys as a constant
const POLYTRANS_ALLOWED_RANK_MATH_META_KEYS = [
    'rank_math_title',
    'rank_math_description',
    'rank_math_facebook_title',
    'rank_math_facebook_description',
    'rank_math_twitter_title',
    'rank_math_twitter_description',
];

class PolyTrans_Translation_Handler
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
        add_action('wp_ajax_polytrans_get_translation_status', [$this, 'ajax_get_translation_status']);
        add_action('wp_ajax_polytrans_clear_translation_status', [$this, 'ajax_clear_translation_status']);
    }

    /**
     * Handle schedule translation AJAX request
     */
    public function handle_schedule_translation()
    {
        check_ajax_referer('polytrans_schedule_translation');

        $post_id = intval($_POST['post_id'] ?? 0);
        $scope = sanitize_text_field($_POST['scope'] ?? 'local');
        $targets = array_map('sanitize_text_field', $_POST['targets'] ?? []);
        $source_lang = function_exists('pll_get_post_language') ? pll_get_post_language($post_id) : 'pl';
        $targets = array_diff($targets, [$source_lang]);
        $needs_review = !empty($_POST['needs_review']);

        $validation = $this->validate_schedule_translation($post_id, $scope, $targets);
        if ($validation !== true) {
            wp_send_json_error($validation);
        }

        // Get allowed targets from settings
        $settings = get_option('polytrans_settings', []);
        $allowed_targets = $settings['allowed_targets'] ?? [];
        if ($scope === 'global') {
            $targets = $allowed_targets;
        }

        // Scheduled languages meta key
        $langs_key = '_polytrans_translation_langs';
        $scheduled_langs = get_post_meta($post_id, $langs_key, true);
        if (!is_array($scheduled_langs)) $scheduled_langs = [];
        $now = time();
        $newly_scheduled = [];

        foreach ($targets as $lang) {
            if ($lang === $source_lang) continue;

            $status_key = '_polytrans_translation_status_' . $lang;
            $log_key = '_polytrans_translation_log_' . $lang;
            $needs_review_key = '_polytrans_translation_needs_review_' . $lang;
            $current_status = get_post_meta($post_id, $status_key, true);

            if ($current_status === 'started') continue; // Already scheduled

            // Schedule translation
            update_post_meta($post_id, $status_key, 'started');
            update_post_meta($post_id, $needs_review_key, $needs_review ? '1' : '0');
            update_post_meta($post_id, $log_key, [[
                'timestamp' => $now,
                'msg' => __('Translation scheduled.', 'polytrans-translation'),
            ]]);

            if (!in_array($lang, $scheduled_langs, true)) {
                $scheduled_langs[] = $lang;
                $newly_scheduled[] = $lang;
            }

            // Send translation request
            $this->send_translation_request($post_id, $source_lang, $lang, $settings);
        }

        update_post_meta($post_id, $langs_key, $scheduled_langs);
        error_log("[polytrans] Translation scheduling finished for post $post_id");

        wp_send_json_success([
            'message' => 'Translation scheduled (' . esc_html($scope) . ').',
            'targets' => $targets,
            'scheduled_langs' => $scheduled_langs
        ]);
    }

    /**
     * Send translation request to external endpoint or local endpoint (asynchronously)
     */
    private function send_translation_request($post_id, $source_lang, $target_lang, $settings)
    {
        $translation_provider = $settings['translation_provider'] ?? 'google';
        $endpoint = $settings['translation_endpoint'] ?? '';
        $translation_receiver_endpoint = $settings['translation_receiver_endpoint'] ?? '';

        // If no external endpoint is configured, use local endpoint for async processing
        if (!$endpoint || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $endpoint = home_url('/wp-json/polytrans/v1/translation/translate');
            $translation_receiver_endpoint = home_url('/wp-json/polytrans/v1/translation/receive-post');
        }

        // Handle all translation requests via HTTP (async)
        $translation_receiver_secret = $settings['translation_receiver_secret'] ?? '';
        $translation_receiver_secret_method = $settings['translation_receiver_secret_method'] ?? 'header_bearer';

        // Always send via HTTP for async processing (local or external)
        if (
            $endpoint &&
            filter_var($endpoint, FILTER_VALIDATE_URL) &&
            $translation_receiver_endpoint &&
            filter_var($translation_receiver_endpoint, FILTER_VALIDATE_URL)
        ) {
            $post = get_post($post_id);
            if ($post) {
                $meta = get_post_meta($post_id);
                $allowed_meta_keys = defined('POLYTRANS_ALLOWED_RANK_MATH_META_KEYS') ? POLYTRANS_ALLOWED_RANK_MATH_META_KEYS : [];
                $meta = array_intersect_key($meta, array_flip($allowed_meta_keys));

                foreach ($meta as $k => $v) {
                    if (is_array($v) && count($v) === 1) {
                        $meta[$k] = $v[0];
                    }
                }

                $excerpt = $post->post_excerpt;
                $payload = [
                    'source_language' => $source_lang,
                    'target_language' => $target_lang,
                    'original_post_id' => $post_id,
                    'target_endpoint' => $translation_receiver_endpoint,
                    'toTranslate' => [
                        'title' => $post->post_title,
                        'content' => $post->post_content,
                        'excerpt' => $excerpt,
                        'meta' => json_decode(json_encode($meta), true),
                    ]
                ];

                $receiver_url = $endpoint;
                $headers = ['Content-Type' => 'application/json'];

                if ($translation_receiver_secret && $translation_receiver_secret_method) {
                    switch ($translation_receiver_secret_method) {
                        case 'get_param':
                            $receiver_url = add_query_arg('secret', rawurlencode($translation_receiver_secret), $receiver_url);
                            break;
                        case 'header_bearer':
                            $headers['Authorization'] = 'Bearer ' . $translation_receiver_secret;
                            break;
                        case 'header_custom':
                            $headers['x-polytrans-secret'] = $translation_receiver_secret;
                            break;
                        case 'post_param':
                            $payload['secret'] = $translation_receiver_secret;
                            break;
                    }
                }

                error_log("[polytrans] Sending async translation request for post $post_id from $source_lang to $target_lang to $receiver_url");

                $response = wp_remote_post($receiver_url, [
                    'headers' => $headers,
                    'body' => wp_json_encode($payload),
                    'timeout' => 0.1, // Very short timeout for fire-and-forget
                    'blocking' => false, // Non-blocking request for async processing
                    'sslverify' => (getenv('WP_ENV') === 'prod') ? true : false,
                ]);

                $log_key = '_polytrans_translation_log_' . $target_lang;
                $log = get_post_meta($post_id, $log_key, true);
                if (!is_array($log)) $log = [];

                if (is_wp_error($response)) {
                    $log[] = [
                        'timestamp' => time(),
                        'msg' => __('Translation request failed: ', 'polytrans-translation') . $response->get_error_message(),
                    ];
                    error_log("[polytrans] Translation request failed for post $post_id: " . $response->get_error_message());
                } else {
                    $log[] = [
                        'timestamp' => time(),
                        'msg' => __('Translation request sent successfully (async).', 'polytrans-translation'),
                    ];
                    error_log("[polytrans] Async translation request sent successfully for post $post_id to $target_lang");
                }

                update_post_meta($post_id, $log_key, $log);
            }
        } else {
            // Fallback error logging if endpoints are not configured properly
            $log_key = '_polytrans_translation_log_' . $target_lang;
            $log = get_post_meta($post_id, $log_key, true);
            if (!is_array($log)) $log = [];

            $log[] = [
                'timestamp' => time(),
                'msg' => __('Translation request failed: Invalid endpoint configuration.', 'polytrans-translation'),
            ];
            error_log("[polytrans] Translation request failed for post $post_id: Invalid endpoint configuration");
            update_post_meta($post_id, $log_key, $log);
        }
    }

    /**
     * Handle Google Translate translation directly
     * @deprecated Now using async HTTP requests for all translations
     * @todo Remove this method in future version
     */
    private function handle_google_translate_direct($post_id, $source_lang, $target_lang, $settings)
    {
        error_log("[polytrans] Starting direct Google Translate for post $post_id from $source_lang to $target_lang");

        // Update status to processing
        $status_key = '_polytrans_translation_status_' . $target_lang;
        $log_key = '_polytrans_translation_log_' . $target_lang;

        update_post_meta($post_id, $status_key, 'processing');

        // Add log entry
        $log = get_post_meta($post_id, $log_key, true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'timestamp' => time(),
            'msg' => __('Starting Google Translate translation.', 'polytrans')
        ];
        update_post_meta($post_id, $log_key, $log);

        try {
            // Get the provider registry and Google provider
            $registry = PolyTrans_Provider_Registry::get_instance();
            $google_provider = $registry->get_provider('google');

            if (!$google_provider) {
                throw new Exception('Google provider not found');
            }

            // Use the translate_post_content method from Google Translate integration
            $translated_content = $google_provider->translate_post_content($post_id, $source_lang, $target_lang);

            if (is_wp_error($translated_content)) {
                throw new Exception($translated_content->get_error_message());
            }

            // Use the new translation coordinator for processing
            $coordinator = new PolyTrans_Translation_Coordinator();

            // Prepare the request data
            $request_data = [
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'original_post_id' => $post_id,
                'translated' => $translated_content
            ];

            // Process the translation using the coordinator
            $result = $coordinator->process_translation($request_data);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            // Success - the coordinator has already handled status updates and notifications
            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(__('Google Translate translation completed successfully. Post ID: %d', 'polytrans'), $result['created_post_id'])
            ];

            error_log("[polytrans] Google Translate translation completed successfully for post $post_id -> {$result['created_post_id']}");
        } catch (Exception $e) {
            // Update error status
            update_post_meta($post_id, $status_key, 'failed');
            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(__('Google Translate translation failed: %s', 'polytrans'), $e->getMessage())
            ];

            error_log("[polytrans] Google Translate translation failed for post $post_id: " . $e->getMessage());
        }

        // Update the log
        update_post_meta($post_id, $log_key, $log);
    }

    /**
     * Handle OpenAI translation directly
     * @deprecated Now using async HTTP requests for all translations
     * @todo Remove this method in future version
     */
    private function handle_openai_direct($post_id, $source_lang, $target_lang, $settings)
    {
        error_log("[polytrans] Starting direct OpenAI translation for post $post_id from $source_lang to $target_lang");

        // Update status to processing
        $status_key = '_polytrans_translation_status_' . $target_lang;
        $log_key = '_polytrans_translation_log_' . $target_lang;

        update_post_meta($post_id, $status_key, 'processing');

        // Add log entry
        $log = get_post_meta($post_id, $log_key, true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'timestamp' => time(),
            'msg' => __('Starting OpenAI translation.', 'polytrans')
        ];
        update_post_meta($post_id, $log_key, $log);

        try {
            // Get the provider registry and OpenAI provider
            $registry = PolyTrans_Provider_Registry::get_instance();
            $openai_provider = $registry->get_provider('openai');

            if (!$openai_provider) {
                throw new Exception('OpenAI provider not found');
            }

            // Prepare content for translation
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception('Post not found');
            }

            $content_to_translate = [
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt
            ];

            // Add meta fields if they exist
            $meta = get_post_meta($post_id);
            $allowed_meta_keys = POLYTRANS_ALLOWED_RANK_MATH_META_KEYS;
            $filtered_meta = [];

            foreach ($allowed_meta_keys as $meta_key) {
                if (isset($meta[$meta_key]) && !empty($meta[$meta_key][0])) {
                    $filtered_meta[$meta_key] = $meta[$meta_key][0];
                }
            }

            if (!empty($filtered_meta)) {
                $content_to_translate['meta'] = $filtered_meta;
            }

            // Translate the content
            $translation_result = $openai_provider->translate($content_to_translate, $source_lang, $target_lang, $settings);

            if (!$translation_result['success']) {
                throw new Exception($translation_result['error']);
            }

            // Use the new translation coordinator for processing
            $coordinator = new PolyTrans_Translation_Coordinator();

            // Prepare the request data
            $request_data = [
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'original_post_id' => $post_id,
                'translated' => $translation_result['translated_content']
            ];

            // Process the translation using the coordinator
            $result = $coordinator->process_translation($request_data);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            // Success - the coordinator has already handled status updates and notifications
            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(__('OpenAI translation completed successfully. Post ID: %d', 'polytrans'), $result['created_post_id'])
            ];

            error_log("[polytrans] OpenAI translation completed successfully for post $post_id -> {$result['created_post_id']}");
        } catch (Exception $e) {
            // Update error status
            update_post_meta($post_id, $status_key, 'failed');
            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(__('OpenAI translation failed: %s', 'polytrans'), $e->getMessage())
            ];

            error_log("[polytrans] OpenAI translation failed for post $post_id: " . $e->getMessage());
        }

        // Update the log
        update_post_meta($post_id, $log_key, $log);
    }

    /**
     * Validate schedule translation request
     */
    private function validate_schedule_translation($post_id, $scope, $targets)
    {
        // Check if post exists
        if (!$post_id || !get_post($post_id)) {
            return ['message' => __('Cannot translate: the post does not exist.', 'polytrans-translation')];
        }

        // Check if post is currently being edited (has an active edit lock)
        $lock = wp_check_post_lock($post_id);
        if ($lock) {
            $user = get_userdata($lock);
            $lock_user = $user ? $user->display_name : __('another user', 'polytrans-translation');
            return ['message' => sprintf(__('Cannot translate: the post is currently being edited by %s.', 'polytrans-translation'), $lock_user)];
        }

        // Check for unsaved changes - WordPress sets a revision when autosave happens
        // If there's a more recent revision than the post itself, there are unsaved changes
        $revisions = wp_get_post_revisions($post_id, array('posts_per_page' => 1));
        if (!empty($revisions)) {
            $latest_revision = array_shift($revisions);
            $post = get_post($post_id);
            if ($latest_revision->post_modified > $post->post_modified) {
                return ['message' => __('Cannot translate: the article has unsaved changes. Please save the post first.', 'polytrans-translation')];
            }
        }

        // Legacy dirty check for backwards compatibility
        $is_dirty = get_post_meta($post_id, 'is_dirty', true);
        if ($is_dirty === '1' || $is_dirty === 1 || $is_dirty === true) {
            return ['message' => __('Cannot translate: the article has unsaved changes or is marked as dirty.', 'polytrans-translation')];
        }

        // Get allowed targets from settings
        $settings = get_option('polytrans_settings', []);
        $allowed_targets = $settings['allowed_targets'] ?? [];
        if ($scope === 'global') {
            $targets = $allowed_targets;
        }
        if ($scope === 'regional') {
            if (empty($targets)) {
                return ['message' => __('Please select at least one target language for regional translation.', 'polytrans-translation')];
            }
        }

        $invalid_targets = array_diff($targets, $allowed_targets);
        if (!empty($invalid_targets)) {
            return [
                'message' => __('Invalid target language(s) selected.', 'polytrans-translation'),
                'invalid_targets' => $invalid_targets
            ];
        }

        return true;
    }

    /**
     * AJAX handler for getting translation status
     */
    public function ajax_get_translation_status()
    {
        check_ajax_referer('polytrans_schedule_translation');

        $post_id = intval($_POST['post_id'] ?? 0);
        $langs_key = '_polytrans_translation_langs';
        $scheduled_langs = get_post_meta($post_id, $langs_key, true);
        if (!is_array($scheduled_langs)) $scheduled_langs = [];

        $result = [];
        foreach ($scheduled_langs as $lang) {
            $status_key = '_polytrans_translation_status_' . $lang;
            $log_key = '_polytrans_translation_log_' . $lang;
            $post_id_key = '_polytrans_translation_post_id_' . $lang;
            $result[$lang] = [
                'status' => get_post_meta($post_id, $status_key, true),
                'log' => get_post_meta($post_id, $log_key, true),
                'post_id' => get_post_meta($post_id, $post_id_key, true),
            ];
        }

        wp_send_json_success(['status' => $result]);
    }

    /**
     * AJAX handler for clearing translation status
     */
    public function ajax_clear_translation_status()
    {
        check_ajax_referer('polytrans_schedule_translation');

        $post_id = intval($_POST['post_id'] ?? 0);
        $lang = sanitize_text_field($_POST['lang'] ?? '');
        $langs_key = '_polytrans_translation_langs';
        $scheduled_langs = get_post_meta($post_id, $langs_key, true);
        if (!is_array($scheduled_langs)) $scheduled_langs = [];

        if ($lang && in_array($lang, $scheduled_langs, true)) {
            $scheduled_langs = array_values(array_diff($scheduled_langs, [$lang]));
            update_post_meta($post_id, $langs_key, $scheduled_langs);
            delete_post_meta($post_id, '_polytrans_translation_status_' . $lang);
            delete_post_meta($post_id, '_polytrans_translation_log_' . $lang);
            error_log("[polytrans] Cleared translation status for $lang on post $post_id");
            wp_send_json_success(['scheduled_langs' => $scheduled_langs]);
        }

        wp_send_json_error(['message' => 'Nothing to clear']);
    }
}
