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
                'msg' => __('Translation failed: Post not found.', 'polytrans-translation')
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
            'msg' => sprintf(__('Starting translation with %s.', 'polytrans-translation'), ucfirst($translation_provider))
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
                throw new Exception(sprintf(__('Translation provider %s not found.', 'polytrans-translation'), $translation_provider));
            }

            // Use the provider to translate
            self::log("Sending content to translation provider", "info", [
                'post_id' => $post_id,
                'provider' => $translation_provider,
                'content_length' => strlen($post->post_content)
            ]);

            $translation_result = $provider->translate($content_to_translate, $source_lang, $target_lang, $settings);

            if (!$translation_result['success']) {
                throw new Exception($translation_result['error'] ?? __('Unknown translation error.', 'polytrans-translation'));
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
                throw new Exception($result['error'] ?? __('Failed to process translation.', 'polytrans-translation'));
            }

            // Update success status and log
            update_post_meta($post_id, $status_key, 'completed');
            
            // Set a completion timestamp
            update_post_meta($post_id, '_polytrans_translation_completed_' . $target_lang, time());
            
            $log[] = [
                'timestamp' => time(),
                'msg' => sprintf(
                    __('Translation completed successfully. New post ID: <a href="%s">%d</a>', 'polytrans-translation'),
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
                'msg' => sprintf(__('Translation failed: %s', 'polytrans-translation'), $e->getMessage())
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
                __('Process complete. View detailed <a href="%s" target="_blank">system logs</a>.', 'polytrans-translation'),
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
     * Store a log entry in the database
     * 
     * @param string $message The log message
     * @param string $level The log level (info, warning, error)
     * @param array $context Additional context data
     * @return void
     */
    private static function store_log($message, $level = 'info', $context = [])
    {
        global $wpdb;

        try {
            $table_name = $wpdb->prefix . 'polytrans_logs';

            // Check if the table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                error_log("[polytrans] Cannot log to database: table $table_name doesn't exist");
                return;
            }

            // First, let's detect what columns we have in the table
            $columns_query = $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $table_name
            );

            $columns = $wpdb->get_col($columns_query);

            if (empty($columns)) {
                error_log("[polytrans] Cannot detect columns for table $table_name");
                return;
            }

            // Prepare data based on available columns
            $data = [];
            $formats = [];

            // Required fields for old table structure (from the original plugin)
            if (in_array('post_id', $columns) && !empty($context['post_id'])) {
                $data['post_id'] = intval($context['post_id']);
                $formats[] = '%d';
            }

            if (in_array('source_language', $columns) && !empty($context['source_lang'])) {
                $data['source_language'] = $context['source_lang'];
                $formats[] = '%s';
            }

            if (in_array('target_language', $columns) && !empty($context['target_lang'])) {
                $data['target_language'] = $context['target_lang'];
                $formats[] = '%s';
            }

            if (in_array('status', $columns)) {
                $data['status'] = $level === 'error' ? 'error' : 'info';
                $formats[] = '%s';
            }

            if (in_array('message', $columns)) {
                $data['message'] = $message;
                $formats[] = '%s';
            }

            // Fields for new structure (if available)
            if (in_array('timestamp', $columns)) {
                $data['timestamp'] = current_time('mysql');
                $formats[] = '%s';
            }

            if (in_array('level', $columns)) {
                $data['level'] = $level;
                $formats[] = '%s';
            }

            if (in_array('context', $columns)) {
                $data['context'] = !empty($context) ? wp_json_encode($context) : null;
                $formats[] = '%s';
            }

            if (in_array('process_id', $columns)) {
                $data['process_id'] = getmypid() ?: 0;
                $formats[] = '%d';
            }

            if (in_array('source', $columns)) {
                $data['source'] = defined('WP_CLI') ? 'cli' : (wp_doing_ajax() ? 'ajax' : (wp_doing_cron() ? 'cron' : 'web'));
                $formats[] = '%s';
            }

            if (in_array('user_id', $columns)) {
                $data['user_id'] = get_current_user_id();
                $formats[] = '%d';
            }

            // Only proceed if we have data to insert
            if (empty($data)) {
                error_log("[polytrans] No valid columns found for logging in table $table_name");
                return;
            }

            // Insert the log entry
            $wpdb->insert($table_name, $data, $formats);

            if ($wpdb->last_error) {
                error_log("[polytrans] Database error when storing log: " . $wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log("[polytrans] Exception when storing log: " . $e->getMessage());
        }
    }

    /**
     * Log to the existing table structure
     * This is a simplified logging method that uses only the most basic fields
     * that should exist in the table based on the original plugin structure
     * 
     * @param string $message The message to log
     * @param string $status The status (info, error)
     * @param int $post_id The post ID
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return bool Whether the log was inserted successfully
     */
    public static function log_to_existing_table($message, $status = 'info', $post_id = 0, $source_lang = '', $target_lang = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'polytrans_logs';

        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            error_log("[polytrans] Cannot log to database: table $table_name doesn't exist");
            return false;
        }

        // Insert a log with minimal fields (based on original structure)
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'status' => $status,
                'message' => $message,
                // 'created_at' is automatically set to CURRENT_TIMESTAMP if it exists
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($wpdb->last_error) {
            error_log("[polytrans] Database error when logging to existing table: " . $wpdb->last_error);
            return false;
        }

        return ($result !== false);
    }

    /**
     * Ensure the log table exists
     * 
     * @return bool True if the table exists or was created successfully
     */
    private static function ensure_log_table_exists()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'polytrans_logs';

        // Check if the table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        // If the table exists, it's fine - we'll adapt to whatever structure it has
        if ($table_exists) {
            return true;
        }

        // Since the table doesn't exist, let's see what the original plugin setup would create
        // Check if the function exists in the PolyTrans_Logs_Manager
        if (class_exists('PolyTrans_Logs_Manager') && method_exists('PolyTrans_Logs_Manager', 'create_logs_table')) {
            // Let the original manager create the table
            return PolyTrans_Logs_Manager::create_logs_table();
        }

        // If we can't create the table through the logs manager, we'll create a simple one
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Simple structure with basic fields that we need
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            source_language varchar(10) DEFAULT '',
            target_language varchar(10) DEFAULT '',
            status varchar(20) NOT NULL,
            message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY languages (source_language, target_language),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql);

        // Check if table was created successfully
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            // Clear the flag if it exists
            delete_option('polytrans_logs_table_needed');
            return true;
        }

        // If we couldn't create the table, set a flag for later
        update_option('polytrans_logs_table_needed', true);

        return false;
    }

    /**
     * Check log table structure and report
     * This is a utility method for debugging purposes
     * 
     * @return array Information about the logs table structure
     */
    public static function check_logs_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'polytrans_logs';
        $info = [
            'table_exists' => false,
            'columns' => []
        ];

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $info['table_exists'] = $table_exists;

        if (!$table_exists) {
            return $info;
        }

        // Get column information
        $columns_query = $wpdb->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
            ORDER BY ORDINAL_POSITION",
            DB_NAME,
            $table_name
        );

        $columns = $wpdb->get_results($columns_query);

        if ($columns) {
            foreach ($columns as $column) {
                $info['columns'][$column->COLUMN_NAME] = [
                    'type' => $column->DATA_TYPE,
                    'nullable' => $column->IS_NULLABLE,
                    'key' => $column->COLUMN_KEY
                ];
            }
        }

        // Log the structure information
        error_log("[polytrans] Logs table structure check: " . wp_json_encode($info));

        return $info;
    }

    /**
     * Get the URL to the logs admin page
     * 
     * @param array $args Optional query args to add to the URL
     * @return string The URL to the logs admin page
     */
    public static function get_logs_admin_url($args = [])
    {
        $base_url = admin_url('admin.php?page=polytrans-logs');

        if (!empty($args)) {
            $base_url = add_query_arg($args, $base_url);
        }

        return $base_url;
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
