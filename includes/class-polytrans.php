<?php

/**
 * Main PolyTrans Plugin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans
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
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_scripts']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_post_meta']);

        // AJAX handlers
        add_action('wp_ajax_polytrans_schedule_translation', [$this, 'ajax_schedule_translation']);
        add_action('wp_ajax_polytrans_search_users', [$this, 'ajax_search_users']);
        add_action('wp_ajax_polytrans_refresh_logs', [PolyTrans_Logs_Manager::class, 'ajax_refresh_logs']);
        // Post status transition for notifications
        add_action('transition_post_status', [$this, 'handle_post_status_transition'], 10, 3);

        // Dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        $includes_dir = POLYTRANS_PLUGIN_DIR . 'includes/';

        // Provider system - load interfaces first
        require_once $includes_dir . 'providers/interface-translation-provider.php';
        require_once $includes_dir . 'providers/interface-settings-provider.php';
        require_once $includes_dir . 'providers/class-provider-registry.php';

        // Core WordPress integration classes
        require_once $includes_dir . 'core/class-translation-meta-box.php';
        require_once $includes_dir . 'core/class-translation-notifications.php';
        require_once $includes_dir . 'core/class-user-autocomplete.php';
        require_once $includes_dir . '/core/class-logs-manager.php';


        // Translation scheduler
        require_once $includes_dir . 'scheduler/class-translation-scheduler.php';
        require_once $includes_dir . 'scheduler/class-translation-handler.php';

        // Translation extension (handles incoming translation requests)
        require_once $includes_dir . 'translator/class-translation-extension.php';

        // Settings and admin interface
        require_once $includes_dir . 'settings/class-translation-settings.php';

        // Receiver architecture
        require_once $includes_dir . 'receiver/managers/class-translation-request-validator.php';
        require_once $includes_dir . 'receiver/managers/class-translation-post-creator.php';
        require_once $includes_dir . 'receiver/managers/class-translation-metadata-manager.php';
        require_once $includes_dir . 'receiver/managers/class-translation-taxonomy-manager.php';
        require_once $includes_dir . 'receiver/managers/class-translation-language-manager.php';
        require_once $includes_dir . 'receiver/managers/class-translation-notification-manager.php';
        require_once $includes_dir . 'receiver/managers/class-translation-status-manager.php';
        require_once $includes_dir . 'receiver/managers/class-translation-security-manager.php';
        require_once $includes_dir . 'receiver/class-translation-coordinator.php';
        require_once $includes_dir . 'receiver/class-translation-receiver-extension.php';

        //menu
        require_once $includes_dir . 'menu/class-settings-menu.php';
        require_once $includes_dir . 'menu/class-logs-menu.php';
        require_once $includes_dir . 'menu/class-tag-translation.php';
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Initialize components
        PolyTrans_Translation_Meta_Box::get_instance();
        PolyTrans_Translation_Scheduler::get_instance();
        PolyTrans_Translation_Handler::get_instance();
        PolyTrans_Translation_Notifications::get_instance();
        PolyTrans_Tag_Translation::get_instance();
        PolyTrans_User_Autocomplete::get_instance();

        // Initialize the translation extension (handles incoming translation requests)
        PolyTrans_Translation_Extension::get_instance();

        // Initialize the advanced receiver extension
        $receiver_extension = new PolyTrans_Translation_Receiver_Extension();
        $receiver_extension->register();

        // Initialize provider-specific AJAX handlers
        $this->init_provider_ajax_handlers();

        if (is_admin() && isset($_GET['check_stuck']) && current_user_can('manage_options')) {
            $this->handle_check_stuck_translations();
        }
    }

    /**
     * Initialize AJAX handlers for all registered providers
     */
    private function init_provider_ajax_handlers()
    {
        // Initialize provider registry and let it handle provider initialization
        $registry = PolyTrans_Provider_Registry::get_instance();
        $registry->init_providers();
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus()
    {
        PolyTrans_Settings_Menu::get_instance()->add_admin_menu();
        PolyTrans_Tag_Translation::get_instance()->add_admin_menu();
        PolyTrans_Logs_Menu::get_instance()->add_logs_submenu();

        // Rename the first submenu item
        global $submenu;
        if (isset($submenu['polytrans'])) {
            $submenu['polytrans'][0][0] = __('Settings', 'polytrans');
        }
    }


    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        PolyTrans_Settings_Menu::get_instance()->add_scripts($hook);
        PolyTrans_Logs_Menu::get_instance()->add_scripts($hook);
        PolyTrans_Tag_Translation::get_instance()->enqueue_admin_scripts($hook);
        PolyTrans_Translation_Scheduler::get_instance()->enqueue_admin_scripts($hook);
    }

    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_public_scripts()
    {
        // Add any public scripts here if needed
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes()
    {
        // Translation meta box
        add_meta_box(
            'polytrans_translation',
            __('Translation', 'polytrans'),
            [$this, 'translation_meta_box_callback'],
            ['post', 'page'],
            'side',
            'high'
        );

        // Translation scheduler meta box
        add_meta_box(
            'polytrans_translation_scheduler',
            __('PolyTrans Scheduler', 'polytrans'),
            [$this, 'translation_scheduler_meta_box_callback'],
            ['post', 'page'],
            'side',
            'high'
        );
    }

    /**
     * Translation meta box callback
     */
    public function translation_meta_box_callback($post)
    {
        $meta_box = PolyTrans_Translation_Meta_Box::get_instance();
        $meta_box->render($post);
    }

    /**
     * Translation scheduler meta box callback
     */
    public function translation_scheduler_meta_box_callback($post)
    {
        $scheduler = PolyTrans_Translation_Scheduler::get_instance();
        $scheduler->render($post);
    }

    /**
     * Save post meta
     */
    public function save_post_meta($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Handle translation meta box
        $meta_box = PolyTrans_Translation_Meta_Box::get_instance();
        $meta_box->save($post_id);
    }

    /**
     * AJAX handler for scheduling translation
     */
    public function ajax_schedule_translation()
    {
        $handler = PolyTrans_Translation_Handler::get_instance();
        $handler->handle_schedule_translation();
    }

    /**
     * AJAX handler for user search
     */
    public function ajax_search_users()
    {
        $user_autocomplete = PolyTrans_User_Autocomplete::get_instance();
        $user_autocomplete->ajax_search_users();
    }

    /**
     * Handle post status transitions for notifications
     */
    public function handle_post_status_transition($new_status, $old_status, $post)
    {
        $notifications = PolyTrans_Translation_Notifications::get_instance();
        $notifications->handle_post_status_transition($new_status, $old_status, $post);
    }


    /**
     * Get plugin version
     */
    public function get_version()
    {
        return POLYTRANS_VERSION;
    }

    /**
     * Get plugin directory path
     */
    public function get_plugin_dir()
    {
        return POLYTRANS_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     */
    public function get_plugin_url()
    {
        return POLYTRANS_PLUGIN_URL;
    }

    /**
     * Handle checking stuck translations from admin
     */
    private function handle_check_stuck_translations()
    {
        global $wpdb;

        $timeout_hours = 24; // Consider translations stuck after 24 hours
        $timeout_seconds = $timeout_hours * 3600;
        $now = time();
        $fixed = 0;
        $checked = 0;
        $stuck = [];

        // Query for all post meta entries with non-terminal statuses
        $non_terminal_states = ['started', 'translating', 'processing'];
        $meta_query = $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE %s 
            AND meta_value IN ('started', 'translating', 'processing')",
            '_polytrans_translation_status_%'
        );

        $stuck_translations = $wpdb->get_results($meta_query);
        $checked = count($stuck_translations);

        if ($checked === 0) {
            // No translations in non-terminal state
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__('No stuck translations found.', 'polytrans') .
                    '</p></div>';
            });
            return;
        }

        foreach ($stuck_translations as $item) {
            // Extract language from meta key
            preg_match('/_polytrans_translation_status_(.+)$/', $item->meta_key, $matches);
            if (empty($matches[1])) continue;

            $language = $matches[1];
            $post_id = $item->post_id;

            // Get the log to check when this translation was started
            $log_key = '_polytrans_translation_log_' . $language;
            $log = get_post_meta($post_id, $log_key, true);

            if (!is_array($log) || empty($log)) continue;

            // Get the first log timestamp
            $first_log = reset($log);
            $start_time = $first_log['timestamp'] ?? 0;

            if ($start_time > 0 && ($now - $start_time) > $timeout_seconds) {
                // This translation has been stuck for too long
                $status_key = '_polytrans_translation_status_' . $language;
                $error_message = sprintf(
                    __('Translation timed out after %d hours in "%s" status.', 'polytrans'),
                    $timeout_hours,
                    $item->meta_value
                );

                // Update status to failed
                update_post_meta($post_id, $status_key, 'failed');

                // Add error details
                update_post_meta(
                    $post_id,
                    '_polytrans_translation_error_' . $language,
                    $error_message
                );

                // Add to log
                $log[] = [
                    'timestamp' => $now,
                    'msg' => $error_message
                ];
                update_post_meta($post_id, $log_key, $log);

                $fixed++;
                $stuck[] = [
                    'post_id' => $post_id,
                    'language' => $language,
                    'status' => $item->meta_value,
                    'stuck_for_hours' => round(($now - $start_time) / 3600, 1)
                ];

                // Optional: Log to WordPress error log
                error_log(sprintf(
                    '[polytrans] Marked stuck translation as failed: Post ID %d, Language %s, Previous Status %s',
                    $post_id,
                    $language,
                    $item->meta_value
                ));
            }
        }

        // Show admin notice with results
        $message = sprintf(
            __('Checked %d translations, fixed %d stuck translations.', 'polytrans'),
            $checked,
            $fixed
        );

        add_action('admin_notices', function () use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html($message) .
                '</p></div>';
        });
    }
}
