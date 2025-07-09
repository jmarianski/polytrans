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
        require_once $includes_dir . 'core/class-tag-translation.php';
        require_once $includes_dir . 'core/class-user-autocomplete.php';


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

        // REST API endpoints
        require_once $includes_dir . 'api/class-translation-api.php';
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
        PolyTrans_Translation_API::get_instance();

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
        // Main menu item
        add_menu_page(
            __('PolyTrans', 'polytrans'),
            __('PolyTrans', 'polytrans'),
            'manage_options',
            'polytrans',
            [$this, 'render_dashboard'],
            'dashicons-translation',
            80
        );

        // Settings submenu
        add_submenu_page(
            'polytrans',
            __('Settings', 'polytrans'),
            __('Settings', 'polytrans'),
            'manage_options',
            'polytrans-settings',
            [$this, 'render_settings']
        );
        
        // Logs submenu
        add_submenu_page(
            'polytrans',
            __('Logs', 'polytrans'),
            __('Logs', 'polytrans'),
            'manage_options',
            'polytrans-logs',
            [$this, 'render_logs']
        );

        // Rename the first submenu item
        global $submenu;
        if (isset($submenu['polytrans'])) {
            $submenu['polytrans'][0][0] = __('Dashboard', 'polytrans');
        }
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard()
    {
        // Get translation status summary
        require_once POLYTRANS_PLUGIN_DIR . 'includes/scheduler/class-translation-handler.php';
        $handler = PolyTrans_Translation_Handler::get_instance();
        $summary = $handler->get_translation_status_summary();
        
        // Dashboard content
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('PolyTrans Dashboard', 'polytrans') . '</h1>';
        echo '<p>' . esc_html__('Welcome to PolyTrans, the advanced multilingual translation management system.', 'polytrans') . '</p>';
        
        // Translation status summary
        echo '<div class="card" style="max-width:800px; margin-top:20px; padding:20px;">';
        echo '<h2>' . esc_html__('Translation Status Summary', 'polytrans') . '</h2>';
        
        echo '<div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">';
        
        // Total translations
        echo '<div style="flex:1; min-width:200px;">';
        echo '<h3>' . esc_html__('Total Translations', 'polytrans') . '</h3>';
        echo '<p style="font-size:24px;">' . esc_html($summary['total']) . '</p>';
        echo '</div>';
        
        // Status breakdown
        echo '<div style="flex:2; min-width:300px;">';
        echo '<h3>' . esc_html__('By Status', 'polytrans') . '</h3>';
        echo '<ul style="list-style:none; padding:0;">';
        foreach ($summary['by_status'] as $status => $count) {
            $status_label = ucfirst($status);
            $status_class = '';
            
            switch ($status) {
                case 'completed':
                    $status_class = 'color:green;';
                    break;
                case 'failed':
                    $status_class = 'color:red;';
                    break;
                case 'started':
                case 'translating':
                case 'processing':
                case 'in-progress':
                    $status_class = 'color:orange;';
                    break;
            }
            
            echo '<li style="margin-bottom:8px;"><span style="font-weight:bold;' . $status_class . '">' . esc_html($status_label) . ':</span> ' . esc_html($count) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        
        echo '</div>';
        
        // Potentially stuck translations
        if (!empty($summary['potentially_stuck'])) {
            echo '<h3 style="color:red;">' . esc_html__('Potentially Stuck Translations', 'polytrans') . '</h3>';
            echo '<table class="widefat" style="margin-top:10px;">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Post', 'polytrans') . '</th>';
            echo '<th>' . esc_html__('Language', 'polytrans') . '</th>';
            echo '<th>' . esc_html__('Status', 'polytrans') . '</th>';
            echo '<th>' . esc_html__('Hours Since Activity', 'polytrans') . '</th>';
            echo '<th>' . esc_html__('Actions', 'polytrans') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($summary['potentially_stuck'] as $stuck) {
                echo '<tr>';
                echo '<td><a href="' . esc_url(admin_url('post.php?post=' . $stuck['post_id'] . '&action=edit')) . '">' . esc_html($stuck['post_title']) . '</a></td>';
                echo '<td>' . esc_html(strtoupper($stuck['language'])) . '</td>';
                echo '<td>' . esc_html($stuck['status']) . '</td>';
                echo '<td>' . esc_html($stuck['hours_since_activity']) . '</td>';
                echo '<td>';
                echo '<a href="#" class="button" onclick="alert(\'' . esc_js(__('This feature is coming soon!', 'polytrans')) . '\'); return false;">' . esc_html__('Fix', 'polytrans') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>'; // card
        
        echo '</div>'; // wrap
    }

    /**
     * Render settings page
     */
    public function render_settings()
    {
        require_once POLYTRANS_PLUGIN_DIR . 'includes/settings/class-translation-settings.php';
        $settings = new polytrans_settings();
        $settings->render();
    }
    
    /**
     * Render logs page
     */
    public function render_logs()
    {
        require_once POLYTRANS_PLUGIN_DIR . 'includes/core/class-logs-manager.php';
        PolyTrans_Logs_Manager::admin_logs_page();
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        $plugin_url = POLYTRANS_PLUGIN_URL;

        // Load on translation settings page
        if ($hook === 'settings_page_polytrans-settings') {
            wp_enqueue_script('polytrans-settings', $plugin_url . 'assets/js/settings/translation-settings-admin.js', ['jquery'], POLYTRANS_VERSION, true);
            wp_enqueue_script('polytrans-user-autocomplete', $plugin_url . 'assets/js/core/user-autocomplete.js', ['jquery-ui-autocomplete'], POLYTRANS_VERSION, true);

            wp_enqueue_style('polytrans-settings', $plugin_url . 'assets/css/settings/translation-settings-admin.css', [], POLYTRANS_VERSION);
            wp_enqueue_style('jquery-ui-autocomplete');

            // Localize script for main settings
            $settings = get_option('polytrans_settings', []);
            wp_localize_script('polytrans-settings', 'PolyTransAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_nonce'),
                'settings' => $settings,
                'translation_receiver_endpoint' => $settings['translation_receiver_endpoint'] ?? '',
            ]);
        }

        // Load on post edit pages
        if (in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_script('polytrans-scheduler', $plugin_url . 'assets/js/scheduler/translation-scheduler.js', ['jquery'], POLYTRANS_VERSION, true);
            wp_enqueue_style('polytrans-scheduler', $plugin_url . 'assets/css/scheduler/translation-scheduler.css', [], POLYTRANS_VERSION);

            $settings = get_option('polytrans_settings', []);
            $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
            $lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];

            wp_localize_script('polytrans-scheduler', 'PolyTransScheduler', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'settings' => $settings,
                'langs' => $langs,
                'lang_names' => $lang_names,
                'postId' => get_the_ID(),
                'nonce' => wp_create_nonce('polytrans_schedule_translation'),
                'edit_url' => admin_url('post.php?post=__ID__&action=edit'),
            ]);
        }
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
     * Add dashboard widgets
     */
    public function add_dashboard_widgets()
    {
        wp_add_dashboard_widget(
            'polytrans_status_dashboard_widget',
            __('PolyTrans Translation Status', 'polytrans'),
            [$this, 'render_dashboard_widget']
        );
    }
    
    /**
     * Render the dashboard widget showing translation status
     */
    public function render_dashboard_widget()
    {
        // Load the status manager class if not already loaded
        if (!class_exists('PolyTrans_Translation_Status_Manager')) {
            require_once POLYTRANS_PLUGIN_DIR . 'includes/receiver/managers/class-translation-status-manager.php';
        }
        
        $status_manager = new PolyTrans_Translation_Status_Manager();
        $summary = $status_manager->get_status_summary();
        
        echo '<div class="polytrans-dashboard-widget">';
        echo '<h4>' . __('Translation Status Summary', 'polytrans') . '</h4>';
        
        echo '<table class="widefat fixed" style="margin-bottom: 10px;">';
        echo '<thead><tr>';
        echo '<th>' . __('Status', 'polytrans') . '</th>';
        echo '<th>' . __('Count', 'polytrans') . '</th>';
        echo '</tr></thead><tbody>';
        
        $statuses = [
            'started' => __('Started', 'polytrans'),
            'translating' => __('Translating', 'polytrans'),
            'processing' => __('Processing', 'polytrans'),
            'completed' => __('Completed', 'polytrans'),
            'failed' => __('Failed', 'polytrans')
        ];
        
        foreach ($statuses as $status_key => $status_label) {
            $count = $summary[$status_key] ?? 0;
            echo '<tr>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . $count . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Check for potentially stuck translations
        $non_terminal_count = ($summary['started'] ?? 0) + ($summary['translating'] ?? 0) + ($summary['processing'] ?? 0);
        if ($non_terminal_count > 0) {
            echo '<div class="notice notice-warning inline"><p>';
            echo sprintf(
                __('There are %d translations in a non-terminal state that might be stuck. <a href="%s">Check now</a>.', 'polytrans'),
                $non_terminal_count,
                admin_url('admin.php?page=polytrans-logs&check_stuck=1')
            );
            echo '</p></div>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=polytrans-logs') . '" class="button">';
        echo __('View Translation Logs', 'polytrans');
        echo '</a></p>';
        
        echo '</div>';
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
            add_action('admin_notices', function() {
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
        
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                esc_html($message) . 
                '</p></div>';
        });
    }
}
