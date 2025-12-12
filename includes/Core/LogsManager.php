<?php

namespace PolyTrans\Core;

/**
 * PolyTrans Logs Manager
 * Handles logs database table creation and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class LogsManager
{
    /**
     * Create the logs database table
     * 
     * @return bool True if the table was created successfully
     */
    public static function create_logs_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'polytrans_logs';

        // Check if the table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            // Table exists - let's check the structure and adapt if needed
            self::check_and_adapt_table_structure($table_name);
            return true;
        }

        // If the database logging is disabled, don't create the table
        if (!self::is_db_logging_enabled()) {
            return true;
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Create a standard logs table with all commonly needed columns
        // Using created_at instead of timestamp to avoid conflicts with reserved words
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context text,
            process_id int(11),
            source varchar(20),
            user_id bigint(20) unsigned,
            post_id bigint(20) unsigned,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY created_at (created_at),
            KEY source (source),
            KEY user_id (user_id),
            KEY post_id (post_id)
        ) $charset_collate;";

        $result = dbDelta($sql);

        // Clear the flag
        delete_option('polytrans_logs_table_needed');

        // Log successful creation
        error_log("[polytrans] Created logs table $table_name");

        return true;
    }

    /**
     * Check the structure of the logs table and adapt if needed
     * 
     * @param string $table_name The table name to check
     * @return void
     */
    private static function check_and_adapt_table_structure($table_name)
    {
        global $wpdb;
        $existing_columns = self::get_table_column_details($table_name);

        // If we have an empty array, something went wrong with the table
        if (empty($existing_columns)) {
            error_log("[polytrans] Error checking logs table structure: Could not retrieve columns");
            return;
        }

        // Define expected schema
        $expected_schema = [
            'id' => [
                'type' => 'bigint(20) unsigned',
                'null' => 'NO',
                'extra' => 'auto_increment'
            ],
            'created_at' => [
                'type' => 'datetime',
                'null' => 'NO'
            ],
            'level' => [
                'type' => 'varchar(20)',
                'null' => 'NO',
                'default' => 'info'
            ],
            'message' => [
                'type' => 'text',
                'null' => 'NO'
            ],
            'context' => [
                'type' => 'text',
                'null' => 'YES'
            ],
            'process_id' => [
                'type' => 'int(11)',
                'null' => 'YES'
            ],
            'source' => [
                'type' => 'varchar(20)',
                'null' => 'YES'
            ],
            'user_id' => [
                'type' => 'bigint(20) unsigned',
                'null' => 'YES'
            ],
            'post_id' => [
                'type' => 'bigint(20) unsigned',
                'null' => 'YES'
            ]
        ];

        $data_corrupted = false;

        // Check each expected column
        foreach ($expected_schema as $column_name => $expected) {
            if (!isset($existing_columns[$column_name])) {
                // Column doesn't exist - add it
                $sql = self::build_add_column_sql($table_name, $column_name, $expected);
                $wpdb->query($sql);
                error_log("[polytrans] Added missing column '$column_name' to logs table");
            } else {
                // Column exists - check if type matches
                $existing = $existing_columns[$column_name];

                // Normalize types for comparison
                $expected_type = self::normalize_column_type($expected['type']);
                $existing_type = self::normalize_column_type($existing['type']);

                if ($expected_type !== $existing_type) {
                    // Type mismatch detected - this indicates corrupted data
                    $data_corrupted = true;
                    error_log("[polytrans] Type mismatch detected for column '$column_name': expected '$expected_type', found '$existing_type'");
                }

                // Check if NULL constraint matches
                if ($expected['null'] !== $existing['null']) {
                    $data_corrupted = true;
                    error_log("[polytrans] NULL constraint mismatch for column '$column_name': expected '{$expected['null']}', found '{$existing['null']}'");
                }
            }
        }

        // If data corruption detected, clean and recreate table
        if ($data_corrupted) {
            error_log("[polytrans] Data corruption detected in logs table. Cleaning and recreating table structure.");
            self::clean_and_recreate_logs_table($table_name);
        } else {
            // Add indexes if they don't exist
            self::ensure_table_indexes($table_name);
        }
    }

    /**
     * Clean corrupted data and recreate the logs table with proper structure
     * 
     * @param string $table_name The table name
     * @return void
     */
    private static function clean_and_recreate_logs_table($table_name)
    {
        global $wpdb;

        try {
            // Backup any existing data that might be salvageable
            $backup_data = [];
            $salvage_query = "SELECT * FROM `$table_name` ORDER BY id DESC LIMIT 1000";
            $existing_data = $wpdb->get_results($salvage_query, ARRAY_A);

            if ($existing_data) {
                error_log("[polytrans] Attempting to salvage " . count($existing_data) . " recent log entries");
                $backup_data = $existing_data;
            }

            // Drop the corrupted table
            $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
            error_log("[polytrans] Dropped corrupted logs table");

            // Get charset collate
            $charset_collate = $wpdb->get_charset_collate();

            // Recreate table with correct structure
            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                created_at datetime NOT NULL,
                level varchar(20) NOT NULL DEFAULT 'info',
                message text NOT NULL,
                context text,
                process_id int(11),
                source varchar(20),
                user_id bigint(20) unsigned,
                post_id bigint(20) unsigned,
                PRIMARY KEY  (id),
                KEY level (level),
                KEY created_at (created_at),
                KEY source (source),
                KEY user_id (user_id),
                KEY post_id (post_id)
            ) $charset_collate;";

            $result = $wpdb->query($sql);

            if ($result !== false) {
                error_log("[polytrans] Successfully recreated logs table with correct structure");

                // Try to restore salvageable data
                if (!empty($backup_data)) {
                    self::restore_salvageable_data($table_name, $backup_data);
                }
            } else {
                error_log("[polytrans] Failed to recreate logs table: " . $wpdb->last_error);
            }
        } catch (\Exception $e) {
            error_log("[polytrans] Error during table cleanup and recreation: " . $e->getMessage());
        }
    }

    /**
     * Restore salvageable data to the recreated table
     * 
     * @param string $table_name The table name
     * @param array $backup_data The data to restore
     * @return void
     */
    private static function restore_salvageable_data($table_name, $backup_data)
    {
        global $wpdb;

        $restored_count = 0;
        $failed_count = 0;

        foreach ($backup_data as $row) {
            try {
                // Clean and validate the data
                $clean_data = [
                    'created_at' => self::clean_datetime_value($row['created_at'] ?? null),
                    'level' => self::clean_varchar_value($row['level'] ?? 'info', 20),
                    'message' => self::clean_text_value($row['message'] ?? ''),
                    'context' => self::clean_text_value($row['context'] ?? null),
                    'process_id' => self::clean_int_value($row['process_id'] ?? null),
                    'source' => self::clean_varchar_value($row['source'] ?? null, 20),
                    'user_id' => self::clean_bigint_value($row['user_id'] ?? null),
                    'post_id' => self::clean_bigint_value($row['post_id'] ?? null)
                ];

                // Remove null values for columns that don't allow them
                if (empty($clean_data['message'])) {
                    $clean_data['message'] = 'Restored log entry';
                }

                if (empty($clean_data['created_at'])) {
                    $clean_data['created_at'] = current_time('mysql');
                }

                // Insert the cleaned data
                $result = $wpdb->insert($table_name, $clean_data);

                if ($result !== false) {
                    $restored_count++;
                } else {
                    $failed_count++;
                }
            } catch (\Exception $e) {
                $failed_count++;
                error_log("[polytrans] Failed to restore log entry: " . $e->getMessage());
            }
        }

        error_log("[polytrans] Data restoration complete: $restored_count entries restored, $failed_count failed");
    }

    /**
     * Ensure all required indexes exist on the table
     * 
     * @param string $table_name The table name
     * @return void
     */
    private static function ensure_table_indexes($table_name)
    {
        global $wpdb;

        // Define expected indexes
        $indexes = [
            'level' => 'level',
            'created_at' => 'created_at',
            'source' => 'source',
            'user_id' => 'user_id',
            'post_id' => 'post_id'
        ];

        foreach ($indexes as $index_name => $column_name) {
            // Try to add index - MySQL will ignore if it already exists
            $wpdb->query("ALTER TABLE `$table_name` ADD INDEX `$index_name` (`$column_name`)");
        }
    }

    /**
     * Build SQL for adding a column
     * 
     * @param string $table_name The table name
     * @param string $column_name The column name
     * @param array $column_def The column definition
     * @return string The SQL statement
     */
    private static function build_add_column_sql($table_name, $column_name, $column_def)
    {
        $sql = "ALTER TABLE `$table_name` ADD COLUMN `$column_name` {$column_def['type']}";

        if ($column_def['null'] === 'NO') {
            $sql .= ' NOT NULL';
        }

        if (isset($column_def['default'])) {
            $sql .= " DEFAULT '{$column_def['default']}'";
        }

        if (isset($column_def['extra']) && $column_def['extra'] === 'auto_increment') {
            $sql .= ' AUTO_INCREMENT';
            if ($column_name === 'id') {
                $sql .= ' PRIMARY KEY FIRST';
            }
        }

        return $sql;
    }

    /**
     * Normalize column type for comparison
     * 
     * @param string $type The column type
     * @return string Normalized type
     */
    private static function normalize_column_type($type)
    {
        // Remove whitespace and convert to lowercase
        $type = strtolower(trim($type));

        // Handle common variations
        $type = str_replace([' unsigned', ' signed'], ['_unsigned', '_signed'], $type);

        return $type;
    }

    /**
     * Clean datetime value
     * 
     * @param mixed $value The value to clean
     * @return string|null Clean datetime or null
     */
    private static function clean_datetime_value($value)
    {
        if (empty($value)) {
            return current_time('mysql');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return current_time('mysql');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Clean varchar value
     * 
     * @param mixed $value The value to clean
     * @param int $max_length Maximum length
     * @return string|null Clean varchar or null
     */
    private static function clean_varchar_value($value, $max_length)
    {
        if ($value === null) {
            return null;
        }

        return substr(sanitize_text_field($value), 0, $max_length);
    }

    /**
     * Clean text value
     * 
     * @param mixed $value The value to clean
     * @return string|null Clean text or null
     */
    private static function clean_text_value($value)
    {
        if ($value === null) {
            return null;
        }

        return sanitize_textarea_field($value);
    }

    /**
     * Clean integer value
     * 
     * @param mixed $value The value to clean
     * @return int|null Clean integer or null
     */
    private static function clean_int_value($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return intval($value);
    }

    /**
     * Clean bigint value
     * 
     * @param mixed $value The value to clean
     * @return int|null Clean bigint or null
     */
    private static function clean_bigint_value($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return intval($value);
    }

    /**
     * Get logs with pagination and filtering
     * 
     * @param array $args Query arguments
     * @return array Array of logs
     */
    public static function get_logs($args = [])
    {
        global $wpdb;

        $defaults = [
            'page' => 1,
            'per_page' => 50,
            'level' => '',
            'source' => '',
            'post_id' => 0,
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'date_from' => '',
            'date_to' => '',
        ];

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'polytrans_logs';

        // Start building the query
        $sql = "SELECT * FROM $table_name WHERE 1=1";
        $count_sql = "SELECT COUNT(id) FROM $table_name WHERE 1=1";

        // Apply filters
        if (!empty($args['level'])) {
            $level = sanitize_text_field($args['level']);
            $sql .= $wpdb->prepare(" AND level = %s", $level);
            $count_sql .= $wpdb->prepare(" AND level = %s", $level);
        }

        if (!empty($args['source'])) {
            $source = sanitize_text_field($args['source']);
            $sql .= $wpdb->prepare(" AND source = %s", $source);
            $count_sql .= $wpdb->prepare(" AND source = %s", $source);
        }

        if (!empty($args['post_id'])) {
            $post_id = intval($args['post_id']);
            $sql .= $wpdb->prepare(" AND context LIKE %s", '%"post_id":' . $post_id . '%');
            $count_sql .= $wpdb->prepare(" AND context LIKE %s", '%"post_id":' . $post_id . '%');
        }

        if (!empty($args['search'])) {
            $search = sanitize_text_field($args['search']);
            $sql .= $wpdb->prepare(" AND message LIKE %s", '%' . $wpdb->esc_like($search) . '%');
            $count_sql .= $wpdb->prepare(" AND message LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }

        if (!empty($args['date_from'])) {
            $date_from = sanitize_text_field($args['date_from']);
            $sql .= $wpdb->prepare(" AND created_at >= %s", $date_from);
            $count_sql .= $wpdb->prepare(" AND created_at >= %s", $date_from);
        }

        if (!empty($args['date_to'])) {
            $date_to = sanitize_text_field($args['date_to']);
            $sql .= $wpdb->prepare(" AND created_at <= %s", $date_to);
            $count_sql .= $wpdb->prepare(" AND created_at <= %s", $date_to);
        }

        // Order
        $orderby = in_array($args['orderby'], ['id', 'created_at', 'level', 'source', 'user_id']) ? $args['orderby'] : 'created_at';
        $order = in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC';

        // Always include ID as secondary sort to ensure consistent ordering for logs with same timestamp
        if ($orderby !== 'id') {
            $sql .= " ORDER BY $orderby $order, id $order";
        } else {
            $sql .= " ORDER BY $orderby $order";
        }

        // Pagination
        $page = max(1, intval($args['page']));
        $per_page = max(1, intval($args['per_page']));
        $offset = ($page - 1) * $per_page;

        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

        // Get the total count
        $total_items = $wpdb->get_var($count_sql);

        // Get the logs
        $logs = $wpdb->get_results($sql);
        if ($logs === null) {
            error_log("[polytrans] Error fetching logs: " . $wpdb->last_error);
            return [];
        }

        // Process the logs to format context data
        foreach ($logs as &$log) {
            if (!empty($log->context)) {
                $log->context = json_decode($log->context, true);
            } else {
                $log->context = [];
            }
        }

        return [
            'logs' => $logs,
            'total' => intval($total_items),
            'pages' => ceil($total_items / $per_page),
            'page' => $page,
        ];
    }

    /**
     * Clear logs older than a certain number of days
     * 
     * @param int $days Number of days to keep logs (default: 30)
     * @return int Number of logs deleted
     */
    public static function clear_old_logs($days = 30)
    {
        global $wpdb;

        if (!self::logs_table_exists() || !self::is_db_logging_enabled()) {
            return 0;
        }

        $table_name = $wpdb->prefix . 'polytrans_logs';
        $date = date('Y-m-d H:i:s', strtotime('-' . intval($days) . ' days'));

        // Get the date column name (timestamp or created_at)
        $columns = self::get_table_columns($table_name);
        $date_column = in_array('created_at', $columns) ? 'created_at' : (in_array('created_at', $columns) ? 'created_at' : null);

        // If we can't find a date column, we can't delete by date
        if (!$date_column) {
            error_log("[polytrans] Cannot clear logs: No date column found in logs table");
            return 0;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE $date_column < %s",
                $date
            )
        );

        if ($result === false) {
            error_log("[polytrans] Error clearing logs: " . $wpdb->last_error);
            return 0;
        }

        return $result;
    }

    /**
     * Create admin interface to view logs
     */
    public static function admin_logs_page()
    {
        // Include necessary WordPress files
        require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

        // Check if the logs table exists
        $table_exists = self::logs_table_exists();

        // Check if database logging is enabled
        $db_logging_enabled = self::is_db_logging_enabled();

        // Display warning if database logging is disabled or table doesn't exist
        if (!$db_logging_enabled || !$table_exists) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            if (!$db_logging_enabled) {
                echo esc_html__('Database logging is currently disabled. Logs are only being written to the WordPress error log and post meta. Enable database logging in PolyTrans settings to view logs here.', 'polytrans');
            } else {
                echo esc_html__('Logs database table does not exist. Logs are only being written to the WordPress error log and post meta.', 'polytrans');
                echo ' <a href="' . esc_url(admin_url('admin.php?page=polytrans-settings')) . '">' . esc_html__('Go to Settings', 'polytrans') . '</a>';
            }
            echo '</p></div>';
        }

        // If a clear logs action is requested
        if ($table_exists && isset($_POST['polytrans_clear_logs']) && check_admin_referer('polytrans_clear_logs')) {
            $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
            $deleted = self::clear_old_logs($days);

            echo '<div class="updated"><p>';
            printf(
                _n(
                    '%d log entry cleared.',
                    '%d log entries cleared.',
                    $deleted,
                    'polytrans'
                ),
                $deleted
            );
            echo '</p></div>';
        }

        // Process filters and pagination
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

        // Get logs
        $logs_data = self::get_logs([
            'page' => $current_page,
            'per_page' => 50,
            'search' => $search,
            'level' => $level,
            'source' => $source,
            'post_id' => $post_id
        ]);

        // Display filters form
?>
        <div class="wrap">
            <h1><?php esc_html_e('PolyTrans Logs', 'polytrans'); ?></h1>

            <!-- Auto-refresh controls -->
            <div class="polytrans-autorefresh-controls" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <label for="autorefresh-interval"><?php esc_html_e('Auto-refresh every:', 'polytrans'); ?></label>
                <select id="autorefresh-interval">
                    <option value="0"><?php esc_html_e('Disabled', 'polytrans'); ?></option>
                    <option value="5"><?php esc_html_e('5 seconds', 'polytrans'); ?></option>
                    <option value="10"><?php esc_html_e('10 seconds', 'polytrans'); ?></option>
                    <option value="30"><?php esc_html_e('30 seconds', 'polytrans'); ?></option>
                    <option value="60"><?php esc_html_e('1 minute', 'polytrans'); ?></option>
                    <option value="300"><?php esc_html_e('5 minutes', 'polytrans'); ?></option>
                </select>
                <button type="button" id="pause-autorefresh" class="button" style="margin-left: 10px;">
                    <?php esc_html_e('Pause', 'polytrans'); ?>
                </button>
                <button type="button" id="manual-refresh" class="button" style="margin-left: 5px;" title="<?php esc_attr_e('Refresh now', 'polytrans'); ?>">
                    <?php esc_html_e('Refresh Now', 'polytrans'); ?>
                </button>
                <span id="autorefresh-status" style="margin-left: 10px; font-style: italic;"></span>
            </div>

            <form method="get" id="logs-filter-form">
                <input type="hidden" name="page" value="polytrans-logs">
                <input type="hidden" name="paged" value="<?php echo esc_attr($current_page); ?>">

                <div class="tablenav top">
                    <div class="alignleft actions">
                        <label for="filter-by-level" class="screen-reader-text"><?php esc_html_e('Filter by level', 'polytrans'); ?></label>
                        <select name="level" id="filter-by-level">
                            <option value=""><?php esc_html_e('All levels', 'polytrans'); ?></option>
                            <option value="info" <?php selected($level, 'info'); ?>><?php esc_html_e('Info', 'polytrans'); ?></option>
                            <option value="warning" <?php selected($level, 'warning'); ?>><?php esc_html_e('Warning', 'polytrans'); ?></option>
                            <option value="error" <?php selected($level, 'error'); ?>><?php esc_html_e('Error', 'polytrans'); ?></option>
                        </select>

                        <label for="filter-by-source" class="screen-reader-text"><?php esc_html_e('Filter by source', 'polytrans'); ?></label>
                        <select name="source" id="filter-by-source">
                            <option value=""><?php esc_html_e('All sources', 'polytrans'); ?></option>
                            <option value="web" <?php selected($source, 'web'); ?>><?php esc_html_e('Web', 'polytrans'); ?></option>
                            <option value="ajax" <?php selected($source, 'ajax'); ?>><?php esc_html_e('Ajax', 'polytrans'); ?></option>
                            <option value="cron" <?php selected($source, 'cron'); ?>><?php esc_html_e('Cron', 'polytrans'); ?></option>
                            <option value="cli" <?php selected($source, 'cli'); ?>><?php esc_html_e('CLI', 'polytrans'); ?></option>
                        </select>

                        <label for="filter-by-post" class="screen-reader-text"><?php esc_html_e('Filter by post ID', 'polytrans'); ?></label>
                        <input type="number" name="post_id" id="filter-by-post" value="<?php echo $post_id ? esc_attr($post_id) : ''; ?>" placeholder="<?php esc_attr_e('Post ID', 'polytrans'); ?>">

                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'polytrans'); ?>">

                        <?php if (!empty($search) || !empty($level) || !empty($source) || !empty($post_id)): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-logs')); ?>" class="button"><?php esc_html_e('Reset', 'polytrans'); ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="alignright">
                        <p class="search-box">
                            <label class="screen-reader-text" for="log-search-input"><?php esc_html_e('Search Logs:', 'polytrans'); ?></label>
                            <input type="search" id="log-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search logs...', 'polytrans'); ?>">
                            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search Logs', 'polytrans'); ?>">
                        </p>
                    </div>

                    <br class="clear">
                </div>
            </form>

            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $logs_data['pages'],
                        'current' => $current_page,
                    ]);

                    if ($page_links) {
                        echo '<span class="pagination-links">' . $page_links . '</span>';
                    }
                    ?>
                </div>
            </div>

            <div id="logs-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-created_at"><?php esc_html_e('Time', 'polytrans'); ?></th>
                            <th scope="col" class="manage-column column-level"><?php esc_html_e('Level', 'polytrans'); ?></th>
                            <th scope="col" class="manage-column column-message"><?php esc_html_e('Message', 'polytrans'); ?></th>
                            <th scope="col" class="manage-column column-context"><?php esc_html_e('Context', 'polytrans'); ?></th>
                            <th scope="col" class="manage-column column-source"><?php esc_html_e('Source', 'polytrans'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs_data['logs'])): ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No logs found.', 'polytrans'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs_data['logs'] as $log): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html(mysql2date('Y-m-d H:i:s', $log->created_at)); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $level_class = '';
                                        switch ($log->level) {
                                            case 'error':
                                                $level_class = 'error';
                                                break;
                                            case 'warning':
                                                $level_class = 'warning';
                                                break;
                                            case 'info':
                                            default:
                                                $level_class = 'info';
                                                break;
                                        }
                                        ?>
                                        <span class="log-level log-level-<?php echo esc_attr($level_class); ?>">
                                            <?php echo esc_html(ucfirst($log->level)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->message); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($log->context)): ?>
                                            <button type="button" class="button toggle-context" data-context-id="context-<?php echo esc_attr($log->id); ?>">
                                                <?php esc_html_e('Show Context', 'polytrans'); ?>
                                            </button>
                                            <div class="context-data" id="context-<?php echo esc_attr($log->id); ?>" style="display:none;">
                                                <pre><?php echo esc_html(json_encode($log->context, JSON_PRETTY_PRINT)); ?></pre>
                                            </div>
                                        <?php else: ?>
                                            <em><?php esc_html_e('No context data', 'polytrans'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $source = $log->source;
                                        $process_id = $log->process_id;
                                        $user_id = $log->user_id;

                                        if ($source) {
                                            echo esc_html(ucfirst($source));
                                            if ($process_id) {
                                                echo ' (' . esc_html($process_id) . ')';
                                            }
                                        }

                                        if ($user_id) {
                                            $user = get_user_by('id', $user_id);
                                            if ($user) {
                                                echo '<br>';
                                                echo esc_html($user->display_name);
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <form method="post">
                            <?php wp_nonce_field('polytrans_clear_logs'); ?>
                            <input type="number" name="days" min="1" value="30" style="width: 60px;">
                            <label for="days"><?php esc_html_e('days', 'polytrans'); ?></label>
                            <input type="submit" name="polytrans_clear_logs" class="button" value="<?php esc_attr_e('Clear Old Logs', 'polytrans'); ?>">
                        </form>
                    </div>

                    <div class="tablenav-pages">
                        <?php
                        if ($page_links) {
                            echo '<span class="pagination-links">' . $page_links . '</span>';
                        }
                        ?>
                    </div>
                    <br class="clear">
                </div>
            </div> <!-- End logs-table-container -->
        </div>

        <style>
            .polytrans-autorefresh-controls {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .polytrans-autorefresh-controls label {
                font-weight: 600;
                margin: 0;
            }

            .polytrans-autorefresh-controls select {
                min-width: 120px;
            }

            #autorefresh-status {
                color: #666;
                font-style: italic;
                margin-left: 10px;
            }

            .log-level {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-weight: bold;
            }

            .log-level-error {
                background-color: #f8d7da;
                color: #721c24;
            }

            .log-level-warning {
                background-color: #fff3cd;
                color: #856404;
            }

            .log-level-info {
                background-color: #d1ecf1;
                color: #0c5460;
            }

            .context-data {
                margin-top: 10px;
                padding: 10px;
                background-color: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 3px;
                max-height: 200px;
                overflow: auto;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                let autoRefreshInterval = null;
                let isPaused = false;
                let currentInterval = parseInt(localStorage.getItem('polytrans_logs_refresh_interval')) || 10; // Default to 10 seconds or saved preference

                // Context toggle functionality (delegated to work with AJAX-loaded content)
                $(document).on('click', '.toggle-context', function() {
                    var contextId = $(this).data('context-id');
                    $('#' + contextId).toggle();

                    var buttonText = $(this).text() === '<?php esc_html_e('Show Context', 'polytrans'); ?>' ?
                        '<?php esc_html_e('Hide Context', 'polytrans'); ?>' :
                        '<?php esc_html_e('Show Context', 'polytrans'); ?>';

                    $(this).text(buttonText);
                });

                // Handle pagination link clicks
                $(document).on('click', '.pagination-links a', function(e) {
                    e.preventDefault();
                    var url = $(this).attr('href');
                    var urlParams = new URLSearchParams(url.split('?')[1]);
                    var page = urlParams.get('paged') || 1;

                    // Update the form's paged input or add it if it doesn't exist
                    var $pagedInput = $('#logs-filter-form input[name="paged"]');
                    if ($pagedInput.length) {
                        $pagedInput.val(page);
                    } else {
                        $('#logs-filter-form').append('<input type="hidden" name="paged" value="' + page + '">');
                    }

                    // Update the URL without reloading
                    if (window.history && window.history.pushState) {
                        window.history.pushState(null, '', url);
                    }

                    // Refresh the logs table
                    refreshLogsTable();
                });

                // Auto-refresh functionality
                function updateStatus() {
                    const statusElement = $('#autorefresh-status');
                    if (currentInterval === 0) {
                        statusElement.text('<?php esc_html_e('Auto-refresh disabled', 'polytrans'); ?>');
                    } else if (isPaused) {
                        statusElement.text('<?php esc_html_e('Paused', 'polytrans'); ?>');
                    } else {
                        statusElement.text('<?php esc_html_e('Auto-refreshing every', 'polytrans'); ?> ' + currentInterval + ' <?php esc_html_e('seconds', 'polytrans'); ?>');
                    }
                }

                function startAutoRefresh() {
                    if (autoRefreshInterval) {
                        clearInterval(autoRefreshInterval);
                    }

                    if (currentInterval > 0 && !isPaused) {
                        autoRefreshInterval = setInterval(function() {
                            refreshLogsTable();
                        }, currentInterval * 1000);
                    }
                }

                function refreshLogsTable() {
                    // Get current form data to maintain filters and pagination
                    const formData = $('#logs-filter-form').serialize();

                    // Show loading indicator
                    $('#autorefresh-status').text('<?php esc_html_e('Refreshing...', 'polytrans'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'polytrans_refresh_logs',
                            nonce: '<?php echo wp_create_nonce('polytrans_refresh_logs'); ?>',
                            filters: formData
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#logs-table-container').html(response.data.html);
                                // Reattach pagination handlers - handled by delegated event above
                            }
                            // Restore status text
                            updateStatus();
                        },
                        error: function() {
                            console.log('Failed to refresh logs');
                            // Restore status text
                            updateStatus();
                        }
                    });
                }

                // Handle interval change
                $('#autorefresh-interval').on('change', function() {
                    currentInterval = parseInt($(this).val());
                    localStorage.setItem('polytrans_logs_refresh_interval', currentInterval); // Save preference
                    if (currentInterval === 0) {
                        isPaused = false;
                        $('#pause-autorefresh').text('<?php esc_html_e('Pause', 'polytrans'); ?>');
                        if (autoRefreshInterval) {
                            clearInterval(autoRefreshInterval);
                        }
                    } else {
                        if (isPaused) {
                            isPaused = false;
                            $('#pause-autorefresh').text('<?php esc_html_e('Pause', 'polytrans'); ?>');
                        }
                        startAutoRefresh();
                    }
                    updateStatus();
                });

                // Handle pause/resume button
                $('#pause-autorefresh').on('click', function() {
                    if (currentInterval === 0) {
                        return; // Do nothing if auto-refresh is disabled
                    }

                    isPaused = !isPaused;
                    $(this).text(isPaused ? '<?php esc_html_e('Resume', 'polytrans'); ?>' : '<?php esc_html_e('Pause', 'polytrans'); ?>');

                    if (isPaused) {
                        if (autoRefreshInterval) {
                            clearInterval(autoRefreshInterval);
                        }
                    } else {
                        startAutoRefresh();
                    }
                    updateStatus();
                });

                // Handle manual refresh button
                $('#manual-refresh').on('click', function() {
                    refreshLogsTable();
                });

                // Handle browser back/forward buttons
                window.addEventListener('popstate', function(event) {
                    // Parse the current URL to get the page number
                    var urlParams = new URLSearchParams(window.location.search);
                    var page = urlParams.get('paged') || 1;

                    // Update the form's paged input
                    var $pagedInput = $('#logs-filter-form input[name="paged"]');
                    if ($pagedInput.length) {
                        $pagedInput.val(page);
                    }

                    // Refresh the logs table
                    refreshLogsTable();
                });

                // Initialize
                $('#autorefresh-interval').val(currentInterval); // Set the dropdown to saved preference
                startAutoRefresh();
                updateStatus();
            });
        </script>
    <?php
    }

    /**
     * Check if database logging is enabled in settings
     * 
     * @return bool True if database logging is enabled
     */
    public static function is_db_logging_enabled()
    {
        $settings = get_option('polytrans_settings', []);
        // Default to true if not set - backward compatibility
        return isset($settings['enable_db_logging']) ? (bool)$settings['enable_db_logging'] : true;
    }

    /**
     * Check if the logs table exists
     * 
     * @return bool True if the table exists
     */
    public static function logs_table_exists()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'polytrans_logs';

        // Check if the table exists
        return ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name);
    }

    /**
     * Add an entry to the logs
     * Gracefully handles cases where the table doesn't exist or has different structure
     * Always logs to WordPress error log regardless of database settings
     * 
     * @param string $message The log message
     * @param string $level The log level (info, warning, error, debug)
     * @param array $context Additional context for the log
     * @return bool True if successful
     */
    public static function log($message, $level = 'info', $context = [])
    {
        // Always log to WordPress error log
        $context_string = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log("[polytrans] [$level] $message $context_string");

        // If database logging is disabled, we're done
        if (!self::is_db_logging_enabled()) {
            return true;
        }

        // Check if the table exists
        if (!self::logs_table_exists()) {
            return false;
        }

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'polytrans_logs';

            // Process context data
            $source = !empty($context['source']) ? $context['source'] : 'system';
            $process_id = !empty($context['process_id']) ? (int)$context['process_id'] : 0;
            $post_id = !empty($context['post_id']) ? (int)$context['post_id'] : 0;
            $user_id = get_current_user_id();

            // Format context as JSON
            $context_json = !empty($context) ? json_encode($context) : null;

            // Get the actual columns that exist in the table
            $existing_columns = self::get_table_columns($table_name);

            // If no columns were retrieved, don't try to insert
            if (empty($existing_columns) || is_null($existing_columns)) {
                error_log("[polytrans] Could not determine columns for $table_name, skipping database logging");
                return false;
            }

            // Build data array based on available columns only
            $data = [];

            // Map our data to existing columns
            $column_mappings = [
                'created_at' => ['timestamp', 'log_time', 'created_at', 'date'],
                'level' => ['level', 'log_level', 'severity'],
                'message' => ['message', 'log_message', 'content'],
                'context' => ['context', 'data', 'metadata'],
                'source' => ['source', 'log_source'],
                'process_id' => ['process_id', 'pid'],
                'user_id' => ['user_id'],
                'post_id' => ['post_id']
            ];

            // Find the best column matches for our data
            $time_column = self::find_first_match($existing_columns, $column_mappings['created_at']);

            if ($time_column) {
                $data[$time_column] = current_time('mysql');
            }

            $level_column = self::find_first_match($existing_columns, $column_mappings['level']);
            if ($level_column) {
                $data[$level_column] = $level;
            }

            $message_column = self::find_first_match($existing_columns, $column_mappings['message']);
            if ($message_column) {
                $data[$message_column] = $message;
            }

            $context_column = self::find_first_match($existing_columns, $column_mappings['context']);
            if ($context_column && !empty($context)) {
                $data[$context_column] = $context_json;
            }

            $source_column = self::find_first_match($existing_columns, $column_mappings['source']);
            if ($source_column) {
                $data[$source_column] = $source;
            }

            $process_id_column = self::find_first_match($existing_columns, $column_mappings['process_id']);
            if ($process_id_column) {
                $data[$process_id_column] = $process_id;
            }

            $user_id_column = self::find_first_match($existing_columns, $column_mappings['user_id']);
            if ($user_id_column) {
                $data[$user_id_column] = $user_id;
            }

            $post_id_column = self::find_first_match($existing_columns, $column_mappings['post_id']);
            if ($post_id_column && $post_id > 0) {
                $data[$post_id_column] = $post_id;
            }

            // Insert the log entry if we have data and required columns
            if (!empty($data) && isset($data[$message_column])) {
                $result = $wpdb->insert($table_name, $data);
                if ($result === false) {
                    error_log("[polytrans] Database error when inserting log: " . $wpdb->last_error);
                    return false;
                }
                return true;
            } else {
                error_log("[polytrans] Missing required columns for logging to database");
                return false;
            }
        } catch (\Exception $e) {
            error_log("[polytrans] Error logging to database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all column names for a table
     * 
     * @param string $table Table name
     * @return array Array of column names
     */
    private static function get_table_columns($table)
    {
        global $wpdb;
        static $columns_cache = [];

        // Return from cache if available
        if (isset($columns_cache[$table])) {
            return $columns_cache[$table];
        }

        // Get all columns for this table
        $columns = [];
        $cols = $wpdb->get_results("SHOW COLUMNS FROM `$table`");

        if ($cols) {
            foreach ($cols as $col) {
                $columns[] = $col->Field;
            }
            $columns_cache[$table] = $columns;
            return $columns;
        }

        // Return empty array if table doesn't exist or has no columns
        $columns_cache[$table] = [];
        return [];
    }

    /**
     * Get detailed column information for a table
     * 
     * @param string $table Table name
     * @return array Array with column details including types
     */
    private static function get_table_column_details($table)
    {
        global $wpdb;
        static $details_cache = [];

        // Return from cache if available
        if (isset($details_cache[$table])) {
            return $details_cache[$table];
        }

        // Get detailed column information
        $column_details = [];
        $cols = $wpdb->get_results("SHOW COLUMNS FROM `$table`");

        if ($cols) {
            foreach ($cols as $col) {
                $column_details[$col->Field] = [
                    'type' => $col->Type,
                    'null' => $col->Null,
                    'key' => $col->Key,
                    'default' => $col->Default,
                    'extra' => $col->Extra
                ];
            }
            $details_cache[$table] = $column_details;
            return $column_details;
        }

        // Return empty array if table doesn't exist or has no columns
        $details_cache[$table] = [];
        return [];
    }

    /**
     * Find the first matching column name from a list of possibilities
     * 
     * @param array $existing_columns Columns that exist in the table
     * @param array $possible_columns Possible column names to look for
     * @return string|null First matching column or null if none found
     */
    private static function find_first_match($existing_columns, $possible_columns)
    {
        foreach ($possible_columns as $column) {
            if (in_array($column, $existing_columns)) {
                return $column;
            }
        }
        return null;
    }

    /**
     * Check if a column exists in a table
     * 
     * @param string $table Table name
     * @param string $column Column name
     * @return bool True if the column exists
     */
    private static function column_exists($table, $column)
    {
        global $wpdb;
        static $columns_cache = [];

        // Cache the column information to avoid repeated queries
        $cache_key = $table . '_columns';

        if (!isset($columns_cache[$cache_key])) {
            // Get all columns for this table
            $cols = $wpdb->get_results("SHOW COLUMNS FROM `$table`");
            $columns_cache[$cache_key] = [];

            if ($cols) {
                foreach ($cols as $col) {
                    $columns_cache[$cache_key][] = $col->Field;
                }
            }
        }

        return in_array($column, $columns_cache[$cache_key]);
    }

    /**
     * AJAX handler for refreshing logs table
     */
    public static function ajax_refresh_logs()
    {
        // Check nonce
        if (!check_ajax_referer('polytrans_refresh_logs', 'nonce', false)) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Parse the filters from the form data
        $filters = isset($_POST['filters']) ? $_POST['filters'] : '';
        parse_str($filters, $filter_params);

        // Process filters
        $current_page = isset($filter_params['paged']) ? max(1, intval($filter_params['paged'])) : 1;
        $search = isset($filter_params['s']) ? sanitize_text_field($filter_params['s']) : '';
        $level = isset($filter_params['level']) ? sanitize_text_field($filter_params['level']) : '';
        $source = isset($filter_params['source']) ? sanitize_text_field($filter_params['source']) : '';
        $post_id = isset($filter_params['post_id']) ? intval($filter_params['post_id']) : 0;

        // Get logs
        $logs_data = self::get_logs([
            'page' => $current_page,
            'per_page' => 50,
            'search' => $search,
            'level' => $level,
            'source' => $source,
            'post_id' => $post_id
        ]);

        // Generate table HTML
        ob_start();
        self::render_logs_table($logs_data, $current_page, $search, $level, $source, $post_id);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Render just the logs table (for AJAX refresh)
     */
    private static function render_logs_table($logs_data, $current_page, $search = '', $level = '', $source = '', $post_id = 0)
    {
        // Build the base URL for pagination with all current filters
        $base_url = admin_url('admin.php');
        $args = ['page' => 'polytrans-logs'];

        if (!empty($search)) {
            $args['s'] = $search;
        }
        if (!empty($level)) {
            $args['level'] = $level;
        }
        if (!empty($source)) {
            $args['source'] = $source;
        }
        if (!empty($post_id)) {
            $args['post_id'] = $post_id;
        }

        $base_url = add_query_arg($args, $base_url);

        $page_links = paginate_links([
            'base' => add_query_arg('paged', '%#%', $base_url),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $logs_data['pages'],
            'current' => $current_page,
        ]);
    ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-created_at"><?php esc_html_e('Time', 'polytrans'); ?></th>
                    <th scope="col" class="manage-column column-level"><?php esc_html_e('Level', 'polytrans'); ?></th>
                    <th scope="col" class="manage-column column-message"><?php esc_html_e('Message', 'polytrans'); ?></th>
                    <th scope="col" class="manage-column column-context"><?php esc_html_e('Context', 'polytrans'); ?></th>
                    <th scope="col" class="manage-column column-source"><?php esc_html_e('Source', 'polytrans'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs_data['logs'])): ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No logs found.', 'polytrans'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs_data['logs'] as $log): ?>
                        <tr>
                            <td>
                                <?php echo esc_html(mysql2date('Y-m-d H:i:s', $log->created_at)); ?>
                            </td>
                            <td>
                                <?php
                                $level_class = '';
                                switch ($log->level) {
                                    case 'error':
                                        $level_class = 'error';
                                        break;
                                    case 'warning':
                                        $level_class = 'warning';
                                        break;
                                    case 'info':
                                    default:
                                        $level_class = 'info';
                                        break;
                                }
                                ?>
                                <span class="log-level log-level-<?php echo esc_attr($level_class); ?>">
                                    <?php echo esc_html(ucfirst($log->level)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html($log->message); ?>
                            </td>
                            <td>
                                <?php if (!empty($log->context)): ?>
                                    <button type="button" class="button toggle-context" data-context-id="context-<?php echo esc_attr($log->id); ?>">
                                        <?php esc_html_e('Show Context', 'polytrans'); ?>
                                    </button>
                                    <div class="context-data" id="context-<?php echo esc_attr($log->id); ?>" style="display:none;">
                                        <pre><?php echo esc_html(json_encode($log->context, JSON_PRETTY_PRINT)); ?></pre>
                                    </div>
                                <?php else: ?>
                                    <em><?php esc_html_e('No context data', 'polytrans'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $source = $log->source;
                                $process_id = $log->process_id;
                                $user_id = $log->user_id;

                                if ($source) {
                                    echo esc_html(ucfirst($source));
                                    if ($process_id) {
                                        echo ' (' . esc_html($process_id) . ')';
                                    }
                                }

                                if ($user_id) {
                                    $user = get_user_by('id', $user_id);
                                    if ($user) {
                                        echo '<br>';
                                        echo esc_html($user->display_name);
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions">
                <form method="post">
                    <?php wp_nonce_field('polytrans_clear_logs'); ?>
                    <input type="number" name="days" min="1" value="30" style="width: 60px;">
                    <label for="days"><?php esc_html_e('days', 'polytrans'); ?></label>
                    <input type="submit" name="polytrans_clear_logs" class="button" value="<?php esc_attr_e('Clear Old Logs', 'polytrans'); ?>">
                </form>
            </div>

            <div class="tablenav-pages">
                <?php
                if ($page_links) {
                    echo '<span class="pagination-links">' . $page_links . '</span>';
                }
                ?>
            </div>
            <br class="clear">
        </div>
<?php
    }
}
