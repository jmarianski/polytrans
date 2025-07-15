<?php

/**
 * Background Process Handler
 * Handles running tasks in background processes
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Background_Processor
{

    /**
     * Spawn a background process using available system methods
     * 
     * @param array $args The arguments to pass to the background process
     * @param string $action The action to perform (default: 'process-translation')
     * @return bool True if process was spawned successfully
     */
    public static function spawn($args, $action = 'process-translation')
    {
        // Validate args
        if (empty($args['post_id']) || empty($args['source_lang']) || empty($args['target_lang'])) {
            self::log("Background process spawn failed: Invalid arguments", "error", $args);
            return false;
        }

        // Method 1: Try PHP execution functions if available
        if (self::is_exec_available()) {
            return self::spawn_exec($args, $action);
        }

        // Method 2: Use direct loopback HTTP request (most compatible)
        return self::spawn_http_request($args, $action);
    }

    /**
     * Check if exec() is available
     * 
     * @return bool True if exec() is available
     */
    private static function is_exec_available()
    {
        // Common disabled functions in PHP
        $disabled_functions = array_map('trim', explode(',', ini_get('disable_functions')));
        $exec_disabled = in_array('exec', $disabled_functions);
        $system_disabled = in_array('system', $disabled_functions);
        $shell_exec_disabled = in_array('shell_exec', $disabled_functions);

        // Check if any exec function is available
        if (!$exec_disabled && function_exists('exec')) {
            return true;
        }

        if (!$shell_exec_disabled && function_exists('shell_exec')) {
            return true;
        }

        if (!$system_disabled && function_exists('system')) {
            return true;
        }

        return false;
    }

    /**
     * Spawn a background process using exec() or equivalent
     * 
     * @param array $args Arguments to pass to the process
     * @param string $action The action to perform
     * @return bool True if successful
     */
    private static function spawn_exec($args, $action)
    {
        // Generate a unique token for this process
        $token = md5(uniqid(mt_rand(), true));

        // Store the args in a transient for retrieval by the background process
        set_transient('polytrans_bg_' . $token, [
            'args' => $args,
            'action' => $action
        ], 3600); // expires in 1 hour

        // Get absolute path to WordPress
        $wp_load_path = ABSPATH . 'wp-load.php';

        // Build PHP script to execute
        $script = "<?php
            // Load WordPress
            require_once('$wp_load_path');
            
            // Get the data
            \$data = get_transient('polytrans_bg_$token');
            if (!\$data) {
                error_log('[polytrans] Background process failed: Could not retrieve data');
                exit;
            }
            
            // Extract arguments
            \$args = \$data['args'];
            \$action = \$data['action'];
            
            // Call the processing function
            if (class_exists('PolyTrans_Background_Processor')) {
                PolyTrans_Background_Processor::process_task(\$args, \$action);
            }
            
            // Clean up
            delete_transient('polytrans_bg_$token');
        ?>";

        // Create temporary file for the script
        $temp_file = wp_tempnam('polytrans_bg_');
        file_put_contents($temp_file, $script);

        // Get PHP binary
        $php_binary = PHP_BINARY ?: 'php';

        // Try multiple command execution methods
        $success = false;
        $output = '';
        $cmd = "$php_binary $temp_file > /dev/null 2>&1 &";

        if (function_exists('exec')) {
            @exec($cmd, $output, $return_var);
            $success = ($return_var === 0);
            if ($success) {
                self::log("Spawned background process with exec", "info", [
                    'cmd' => $cmd,
                    'token' => $token,
                    'action' => $action,
                    'post_id' => $args['post_id']
                ]);
            }
        }

        // Try shell_exec if exec failed
        if (!$success && function_exists('shell_exec')) {
            @shell_exec($cmd);
            $success = true; // Can't verify success with shell_exec
            self::log("Spawned background process with shell_exec", "info", [
                'cmd' => $cmd,
                'token' => $token,
                'action' => $action,
                'post_id' => $args['post_id']
            ]);
        }

        // Try system if shell_exec failed
        if (!$success && function_exists('system')) {
            @system($cmd, $return_var);
            $success = ($return_var === 0);
            if ($success) {
                self::log("Spawned background process with system", "info", [
                    'cmd' => $cmd,
                    'token' => $token,
                    'action' => $action,
                    'post_id' => $args['post_id']
                ]);
            }
        }

        // Clean up temp file after a delay
        wp_schedule_single_event(time() + 600, 'polytrans_cleanup_temp_file', [$temp_file]);

        return $success;
    }

    /**
     * Process a task directly (called from background process)
     * 
     * @param array $args Arguments for the process
     * @param string $action The action to perform
     * @return void
     */
    public static function process_task($args, $action)
    {
        // Make sure we run for as long as needed
        ignore_user_abort(true);
        set_time_limit(0);

        $post_id = $args['post_id'] ?? 0;
        $source_lang = $args['source_lang'] ?? '';
        $target_lang = $args['target_lang'] ?? '';

        if (!$post_id || !$source_lang || !$target_lang) {
            self::log("Background task failed: Invalid arguments", "error", $args);
            return;
        }

        // Log the start of processing
        self::log("Started background task processing: $action", "info", [
            'post_id' => $post_id,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang
        ]);

        // Process based on action
        switch ($action) {
            case 'process-translation':
                self::process_translation($args);
                break;
            default:
                do_action("polytrans_bg_process_$action", $args);
                break;
        }
    }

    /**
     * Spawn a background process using HTTP request
     * 
     * @param array $args Arguments to pass to the process
     * @param string $action The action to perform
     * @return bool True if successful
     */
    private static function spawn_http_request($args, $action)
    {
        // Generate a unique token for this process
        $token = md5(uniqid(mt_rand(), true));

        // Store the args in a transient
        set_transient('polytrans_bg_' . $token, [
            'args' => $args,
            'action' => $action
        ], 3600); // expires in 1 hour

        // Build the URL to our processing endpoint
        $url = add_query_arg([
            'polytrans_bg' => 1,
            'token' => $token,
            'action' => $action,
            'nonce' => wp_create_nonce('polytrans_bg_process')
        ], home_url('/'));

        // Make a non-blocking request with multiple fallbacks
        $success = false;

        // Method 1: WordPress HTTP API
        $response = wp_remote_post($url, [
            'timeout' => 0.1, // Very short timeout for fire-and-forget
            'blocking' => false, // Non-blocking request
            'sslverify' => false, // Don't verify SSL for local requests
            'headers' => [
                'X-Polytrans-BG' => 'Processing',
                'User-Agent' => 'PolyTrans Background Process'
            ]
        ]);

        $success = !is_wp_error($response);

        // Method 2: Try file_get_contents with stream context if Method 1 failed
        if (!$success) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "X-Polytrans-BG: Processing\r\nUser-Agent: PolyTrans Background Process\r\n",
                    'timeout' => 0.1
                ]
            ]);

            @file_get_contents($url, false, $context);
            $success = true; // Can't verify success with file_get_contents non-blocking
        }

        // Method 3: Try cURL if available
        if (!$success && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PolyTrans Background Process');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Polytrans-BG: Processing']);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            @curl_exec($ch);
            curl_close($ch);
            $success = true; // Can't verify success with curl non-blocking
        }

        self::log("Spawned background process with HTTP request", "info", [
            'url' => $url,
            'token' => $token,
            'action' => $action,
            'post_id' => $args['post_id']
        ]);

        return $success;
    }

    /**
     * Process a translation request (called from background process)
     * 
     * @param array $args Arguments for the process
     * @return void
     */
    private static function process_translation($args)
    {
        $post_id = $args['post_id'] ?? 0;
        $source_lang = $args['source_lang'] ?? '';
        $target_lang = $args['target_lang'] ?? '';

        if (!$post_id || !$source_lang || !$target_lang) {
            self::log("Translation task failed: Invalid arguments", "error", $args);
            return;
        }

        // Get plugin settings
        $settings = get_option('polytrans_settings', []);
        $translation_provider = $settings['translation_provider'] ?? 'google';
        $transport_mode = $settings['translation_transport_mode'] ?? 'external';

        // Status and log keys
        $status_key = '_polytrans_translation_status_' . $target_lang;
        $log_key = '_polytrans_translation_log_' . $target_lang;

        // Get the current status - don't override if it's not set to 'translating'
        $current_status = get_post_meta($post_id, $status_key, true);

        // Only update if the status is 'started' or 'translating' to avoid overwriting completed or failed states
        if ($current_status === 'started' || $current_status === 'translating') {
            // Update status to processing
            update_post_meta($post_id, $status_key, 'processing');
        }

        // Initialize log entry if it doesn't exist
        $log = get_post_meta($post_id, $log_key, true);
        if (!is_array($log)) $log = [];

        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            self::log("Background translation failed: Post not found", "error", [
                'post_id' => $post_id
            ]);

            // Update error status and log
            update_post_meta($post_id, $status_key, 'failed');
            $log[] = [
                'timestamp' => time(),
                'msg' => __('Translation failed: Post not found.', 'polytrans')
            ];
            update_post_meta($post_id, $log_key, $log);
            return;
        }

        self::log("Starting translation process", "info", [
            'post_id' => $post_id,
            'provider' => $translation_provider,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'transport_mode' => $transport_mode,
            'post_title' => $post->post_title
        ]);

        // Log start in post meta
        $log[] = [
            'timestamp' => time(),
            'msg' => sprintf(__('Starting translation with %s.', 'polytrans'), ucfirst($translation_provider))
        ];
        update_post_meta($post_id, $log_key, $log);

        try {
            // Get post content and metadata
            self::log("Preparing content for translation", "info", ['post_id' => $post_id]);

            $meta = get_post_meta($post_id);
            $allowed_meta_keys = defined('POLYTRANS_ALLOWED_RANK_MATH_META_KEYS') ? POLYTRANS_ALLOWED_RANK_MATH_META_KEYS : [];
            $meta = array_intersect_key($meta, array_flip($allowed_meta_keys));

            foreach ($meta as $k => $v) {
                if (is_array($v) && count($v) === 1) {
                    $meta[$k] = $v[0];
                }
            }

            $content_to_translate = [
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'meta' => json_decode(json_encode($meta), true)
            ];

            // Handle the translation based on provider
            self::log("Loading translation provider: $translation_provider", "info", ['post_id' => $post_id]);

            $registry = PolyTrans_Provider_Registry::get_instance();
            $provider = $registry->get_provider($translation_provider);

            if (!$provider) {
                throw new Exception(sprintf(__('Translation provider %s not found.', 'polytrans'), $translation_provider));
            }

            // Use the provider to translate
            self::log("Sending content to translation provider", "info", [
                'post_id' => $post_id,
                'provider' => $translation_provider,
                'content_length' => strlen($post->post_content)
            ]);

            $translation_result = $provider->translate($content_to_translate, $source_lang, $target_lang, $settings);

            if (!$translation_result['success']) {
                throw new Exception($translation_result['error'] ?? __('Unknown translation error.', 'polytrans'));
            }

            self::log("Translation received from provider, processing result", "info", [
                'post_id' => $post_id,
                'provider' => $translation_provider
            ]);

            // Process the translation using the coordinator
            require_once POLYTRANS_PLUGIN_DIR . 'includes/receiver/class-translation-coordinator.php';
            $coordinator = new PolyTrans_Translation_Coordinator();

            // Prepare the request data for processing
            $request_data = [
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'original_post_id' => $post_id,
                'translated' => $translation_result['translated_content']
            ];

            // Process the translation - this creates the translated post
            self::log("Creating translated post", "info", [
                'post_id' => $post_id,
                'source_lang' => $source_lang,
                'target_lang' => $target_lang
            ]);

            $result = $coordinator->process_translation($request_data);

            if (!$result['success']) {
                throw new Exception($result['error'] ?? __('Failed to process translation.', 'polytrans'));
            }

            // Update success status and log
            update_post_meta($post_id, $status_key, 'completed');

            // Set a completion timestamp
            update_post_meta($post_id, '_polytrans_translation_completed_' . $target_lang, time());

            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(
                    __('Translation completed successfully. New post ID: <a href="%s">%d</a>', 'polytrans'),
                    esc_url(admin_url('post.php?post=' . $result['created_post_id'] . '&action=edit')),
                    $result['created_post_id']
                )
            ];

            // Store the created post ID
            update_post_meta($post_id, '_polytrans_translation_post_id_' . $target_lang, $result['created_post_id']);

            self::log("Translation completed successfully", "info", [
                'post_id' => $post_id,
                'created_post_id' => $result['created_post_id'],
                'source_lang' => $source_lang,
                'target_lang' => $target_lang
            ]);
        } catch (Exception $e) {
            // Update error status and log
            update_post_meta($post_id, $status_key, 'failed');

            // Store error details
            update_post_meta($post_id, '_polytrans_translation_error_' . $target_lang, $e->getMessage());

            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(__('Translation failed: %s', 'polytrans'), $e->getMessage())
            ];

            self::log("Translation failed: " . $e->getMessage(), "error", [
                'post_id' => $post_id,
                'source_lang' => $source_lang,
                'target_lang' => $target_lang,
                'error' => $e->getMessage()
            ]);
        }

        // Add a link to the logs page in the final log entry
        $logs_url = admin_url('admin.php?page=polytrans-logs&post_id=' . $post_id);
        $log[] = [
            'timestamp' => time(),
            'msg' => sprintf(
                __('Process complete. View detailed <a href="%s" target="_blank">system logs</a>.', 'polytrans'),
                esc_url($logs_url)
            )
        ];

        // Update the final log entries
        update_post_meta($post_id, $log_key, $log);
    }

    /**
     * Log a message to both WordPress error log and optionally to our custom log table
     * 
     * @param string $message The log message
     * @param string $level The log level (info, warning, error)
     * @param array $context Additional context data
     * @return void
     */
    public static function log($message, $level = 'info', $context = [])
    {
        // Load the logs manager class
        if (!class_exists('PolyTrans_Logs_Manager')) {
            require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-logs-manager.php';
        }

        // Extract post ID and languages from context if available
        $post_id = isset($context['post_id']) ? intval($context['post_id']) : 0;
        $source_lang = isset($context['source_lang']) ? $context['source_lang'] : '';
        $target_lang = isset($context['target_lang']) ? $context['target_lang'] : '';

        // Use the logs manager to log (it will handle both error_log and DB)
        PolyTrans_Logs_Manager::log($message, $level, $context);

        // Also log to post meta for this specific translation if we have a post ID
        if ($post_id && $target_lang) {
            $log_key = '_polytrans_translation_log_' . $target_lang;
            $log = get_post_meta($post_id, $log_key, true);
            if (!is_array($log)) $log = [];

            $log[] = [
                'timestamp' => time(),
                'msg' => $message,
                'level' => $level
            ];

            update_post_meta($post_id, $log_key, $log);
        }
    }

    /**
     * Check and log table structure on plugin activation
     * This is a static method that can be called during plugin activation
     * to debug table structure issues
     */
    /**
     * Check logs table and functionality on plugin activation
     */
    public static function check_on_activation()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'polytrans_logs';

        // Load the logs manager
        if (!class_exists('PolyTrans_Logs_Manager')) {
            require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-logs-manager.php';
        }

        // Check if the logs table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            error_log("[polytrans] Logs table exists: $table_name");

            // Check the table columns
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table_name`");
            $column_names = [];

            if ($columns) {
                foreach ($columns as $col) {
                    $column_names[] = $col->Field;
                }
                error_log("[polytrans] Logs table columns: " . implode(', ', $column_names));
            } else {
                error_log("[polytrans] Could not retrieve logs table columns");
            }

            // Add a test log entry
            self::log("Plugin activated - test log entry from Background Processor", "info", [
                'source' => 'activation_check'
            ]);
        } else {
            error_log("[polytrans] Logs table does not exist, using postmeta only");
        }

        // Test post meta logging
        $test_post_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_type = 'post' ORDER BY ID DESC LIMIT 1");
        if ($test_post_id) {
            $meta_key = '_polytrans_activation_test';
            update_post_meta($test_post_id, $meta_key, time());
            $meta_value = get_post_meta($test_post_id, $meta_key, true);
            if ($meta_value) {
                error_log("[polytrans] Post meta test successful on post ID: $test_post_id");
                // Clean up
                delete_post_meta($test_post_id, $meta_key);
            } else {
                error_log("[polytrans] Post meta test failed on post ID: $test_post_id");
            }
        } else {
            error_log("[polytrans] No posts found to test post meta");
        }
    }
}
