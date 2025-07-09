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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_scripts']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_post_meta']);

        // AJAX handlers
        add_action('wp_ajax_polytrans_schedule_translation', [$this, 'ajax_schedule_translation']);
        add_action('wp_ajax_polytrans_validate_openai_key', [$this, 'ajax_validate_openai_key']);
        add_action('wp_ajax_polytrans_load_openai_assistants', [$this, 'ajax_load_openai_assistants']);
        add_action('wp_ajax_polytrans_search_users', [$this, 'ajax_search_users']);

        // Post status transition for notifications
        add_action('transition_post_status', [$this, 'handle_post_status_transition'], 10, 3);
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
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            __('PolyTrans Settings', 'polytrans'),
            __('PolyTrans', 'polytrans'),
            'manage_options',
            'polytrans-settings',
            [$this, 'admin_page']
        );
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        $settings_page = new polytrans_settings();
        $settings_page->render();
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

            // Enqueue OpenAI integration script with proper nonce
            wp_enqueue_script('polytrans-openai-integration', $plugin_url . 'assets/js/translator/openai-integration.js', ['jquery'], POLYTRANS_VERSION, true);
            wp_enqueue_style('polytrans-openai-integration', $plugin_url . 'assets/css/translator/openai-integration.css', [], POLYTRANS_VERSION);

            // Localize script for OpenAI integration with correct nonce
            wp_localize_script('polytrans-openai-integration', 'polytrans_openai', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_openai_nonce'),
                'strings' => [
                    'validating' => __('Validating...', 'polytrans'),
                    'valid' => __('API key is valid', 'polytrans'),
                    'invalid' => __('API key is invalid', 'polytrans'),
                    'error' => __('Error validating API key', 'polytrans'),
                    'testing' => __('Testing translation...', 'polytrans'),
                    'test_success' => __('Translation successful!', 'polytrans'),
                    'test_failed' => __('Translation failed', 'polytrans'),
                ]
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
     * AJAX handler for validating OpenAI API key
     */
    public function ajax_validate_openai_key()
    {
        // Check nonce - support both possible nonce names
        $nonce_check = false;
        if (isset($_POST['nonce'])) {
            $nonce_check = wp_verify_nonce($_POST['nonce'], 'polytrans_openai_nonce');
        }

        if (!$nonce_check) {
            wp_send_json_error(__('Security check failed.', 'polytrans'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'polytrans'));
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($api_key)) {
            wp_send_json_error(__('API key is required.', 'polytrans'));
        }

        // Validate the OpenAI API key
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => 'PolyTrans/1.0'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(__('Failed to validate API key: ', 'polytrans') . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            wp_send_json_success(__('API key is valid!', 'polytrans'));
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Invalid API key.', 'polytrans');
            wp_send_json_error($error_message);
        }
    }

    /**
     * AJAX handler for loading OpenAI assistants
     */
    public function ajax_load_openai_assistants()
    {
        // Check nonce - support both possible nonce names
        $nonce_check = false;
        if (isset($_POST['nonce'])) {
            $nonce_check = wp_verify_nonce($_POST['nonce'], 'polytrans_openai_nonce');
        }

        if (!$nonce_check) {
            wp_send_json_error(__('Security check failed.', 'polytrans'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'polytrans'));
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($api_key)) {
            wp_send_json_error(__('API key is required.', 'polytrans'));
        }

        // Load assistants from OpenAI API
        $response = wp_remote_get('https://api.openai.com/v1/assistants', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => 'PolyTrans/1.0',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(__('Failed to load assistants: ', 'polytrans') . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['data']) && is_array($data['data'])) {
                $assistants = array_map(function ($assistant) {
                    return [
                        'id' => $assistant['id'],
                        'name' => $assistant['name'] ?? 'Unnamed Assistant',
                        'description' => $assistant['description'] ?? '',
                        'model' => $assistant['model'] ?? 'gpt-4'
                    ];
                }, $data['data']);

                wp_send_json_success($assistants);
            } else {
                wp_send_json_error(__('No assistants found.', 'polytrans'));
            }
        } else {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Failed to load assistants.', 'polytrans');
            wp_send_json_error($error_message);
        }
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
}
