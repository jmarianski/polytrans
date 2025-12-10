<?php

/**
 * Post-Processing Workflows Menu
 * 
 * Handles the admin menu page for managing post-processing workflows.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Postprocessing_Menu
{
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_polytrans_save_workflow', [$this, 'ajax_save_workflow']);
        add_action('wp_ajax_polytrans_delete_workflow', [$this, 'ajax_delete_workflow']);
        add_action('wp_ajax_polytrans_duplicate_workflow', [$this, 'ajax_duplicate_workflow']);
        add_action('wp_ajax_polytrans_get_workflow', [$this, 'ajax_get_workflow']);
        add_action('wp_ajax_polytrans_test_workflow', [$this, 'ajax_test_workflow']);
        add_action('wp_ajax_polytrans_search_posts', [$this, 'ajax_search_posts']);
        add_action('wp_ajax_polytrans_get_post_data', [$this, 'ajax_get_post_data']);
        add_action('wp_ajax_polytrans_load_openai_assistants_for_workflow', [$this, 'ajax_load_openai_assistants_for_workflow']);
        add_action('wp_ajax_polytrans_load_managed_assistants', [$this, 'ajax_load_managed_assistants']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'polytrans',
            __('Post-Processing Workflows', 'polytrans'),
            __('Post-Processing', 'polytrans'),
            'edit_posts',
            'polytrans-workflows',
            [$this, 'render_workflow_page']
        );

        add_submenu_page(
            'polytrans',
            __('Execute Workflow', 'polytrans'),
            __('Execute Workflow', 'polytrans'),
            'edit_posts',
            'polytrans-execute-workflow',
            [$this, 'render_execute_workflow_page']
        );
    }

    /**
     * Enqueue assets for workflow management
     */
    public function enqueue_assets($hook_suffix)
    {
        // Enqueue for workflow management page
        if (strpos($hook_suffix, 'polytrans-workflows') !== false) {
            // Enqueue prompt editor module (reusable component)
            wp_enqueue_script(
                'polytrans-prompt-editor',
                POLYTRANS_PLUGIN_URL . 'assets/js/prompt-editor.js',
                ['jquery'],
                POLYTRANS_VERSION,
                true
            );

            wp_enqueue_script(
                'polytrans-workflows',
                POLYTRANS_PLUGIN_URL . 'assets/js/postprocessing-admin.js',
                ['jquery', 'wp-util', 'polytrans-prompt-editor'],
                POLYTRANS_VERSION,
                true
            );

            wp_enqueue_style(
                'polytrans-workflows',
                POLYTRANS_PLUGIN_URL . 'assets/css/postprocessing-admin.css',
                [],
                POLYTRANS_VERSION
            );

            // Enqueue user autocomplete assets
            wp_enqueue_script(
                'polytrans-user-autocomplete',
                POLYTRANS_PLUGIN_URL . 'assets/js/core/user-autocomplete.js',
                ['jquery-ui-autocomplete'],
                POLYTRANS_VERSION,
                true
            );
            wp_enqueue_style('jquery-ui-autocomplete');

            // Localize script
            wp_localize_script('polytrans-workflows', 'polytransWorkflows', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_workflows_nonce'),
                'models' => $this->get_openai_models(),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this workflow?', 'polytrans'),
                    'confirmDuplicate' => __('Create a copy of this workflow?', 'polytrans'),
                    'saveSuccess' => __('Workflow saved successfully!', 'polytrans'),
                    'saveError' => __('Error saving workflow.', 'polytrans'),
                    'deleteSuccess' => __('Workflow deleted successfully!', 'polytrans'),
                    'deleteError' => __('Error deleting workflow.', 'polytrans'),
                    'testSuccess' => __('Test completed successfully!', 'polytrans'),
                    'testError' => __('Test failed.', 'polytrans'),
                    'loading' => __('Loading...', 'polytrans'),
                    'addStep' => __('Add Step', 'polytrans'),
                    'removeStep' => __('Remove Step', 'polytrans'),
                    'moveUp' => __('Move Up', 'polytrans'),
                    'moveDown' => __('Move Down', 'polytrans'),
                    'clearSelection' => __('Clear', 'polytrans')
                ]
            ]);

            // Localize user autocomplete script
            wp_localize_script('polytrans-user-autocomplete', 'PolyTransUserAutocomplete', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_nonce'),
                'i18n' => [
                    'no_results' => esc_html__('No users found.', 'polytrans'),
                    'searching' => esc_html__('Searching users...', 'polytrans'),
                    'clear_selection' => esc_html__('Clear selection', 'polytrans'),
                    'type_to_search' => esc_html__('Type to search users...', 'polytrans'),
                    'min_chars' => esc_html__('Type at least 2 characters to search.', 'polytrans'),
                ]
            ]);
        }

        // Enqueue for execute workflow page
        if (strpos($hook_suffix, 'polytrans-execute-workflow') !== false) {
            wp_enqueue_script(
                'polytrans-execute-workflow',
                POLYTRANS_PLUGIN_URL . 'assets/js/postprocessing/execute-workflow.js',
                ['jquery', 'wp-util'],
                POLYTRANS_VERSION,
                true
            );

            wp_enqueue_style(
                'polytrans-execute-workflow',
                POLYTRANS_PLUGIN_URL . 'assets/css/postprocessing-admin.css',
                [],
                POLYTRANS_VERSION
            );

            // Get available languages
            $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
            $lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];

            wp_localize_script('polytrans-execute-workflow', 'polytransExecuteWorkflow', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('polytrans_workflows_nonce'),
                'languages' => array_combine($langs, $lang_names),
                'strings' => [
                    'loading' => __('Loading...', 'polytrans'),
                    'searching' => __('Searching...', 'polytrans'),
                    'selectWorkflow' => __('Select workflow...', 'polytrans'),
                    'selectPost' => __('Select a post...', 'polytrans'),
                    'noWorkflows' => __('No workflows available', 'polytrans'),
                    'noPosts' => __('No posts found', 'polytrans'),
                    'executing' => __('Executing...', 'polytrans'),
                    'verifying' => __('Verifying...', 'polytrans'),
                    'verify' => __('Verify', 'polytrans'),
                    'execute' => __('Execute Workflow', 'polytrans'),
                    'executeAnother' => __('Execute Another Workflow', 'polytrans'),
                    'viewPost' => __('View Post', 'polytrans'),
                    'editPost' => __('Edit Post', 'polytrans'),
                    'success' => __('Success!', 'polytrans'),
                    'failed' => __('Failed', 'polytrans'),
                    'error' => __('Error', 'polytrans'),
                    'alreadyRunning' => __('This workflow is already running on this post.', 'polytrans'),
                    'workflowNotFound' => __('Selected workflow does not exist.', 'polytrans'),
                    'postNotFound' => __('Selected post does not exist.', 'polytrans'),
                    'noTranslation' => __('This post does not have a translation in the selected language.', 'polytrans'),
                    'languageMismatch' => __('Post translation language does not match workflow language.', 'polytrans'),
                    'permissionDenied' => __('You do not have permission to execute workflows on this post.', 'polytrans'),
                    'timeout' => __('Execution timed out. Please check logs for details.', 'polytrans'),
                ]
            ]);
        }
    }

    /**
     * Render workflow management page
     */
    public function render_workflow_page()
    {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        // Get current action
        $action = $_GET['action'] ?? 'list';
        $workflow_id = $_GET['workflow_id'] ?? '';

        switch ($action) {
            case 'edit':
            case 'new':
                $this->render_workflow_editor($workflow_id, $action === 'new');
                break;

            case 'test':
                $this->render_workflow_tester($workflow_id);
                break;

            default:
                $this->render_workflow_list();
                break;
        }
    }

    /**
     * Render workflow list
     */
    private function render_workflow_list()
    {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();
        $workflows = $storage_manager->get_all_workflows();
        $statistics = $storage_manager->get_workflow_statistics();

        // Get available languages
        $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
        $lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];

?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Post-Processing Workflows', 'polytrans'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-workflows&action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New Workflow', 'polytrans'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="workflow-statistics" style="margin-bottom: 20px;">
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Workflow Statistics', 'polytrans'); ?></h2>
                    <div class="inside">
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <div>
                                <strong><?php esc_html_e('Total Workflows:', 'polytrans'); ?></strong>
                                <?php echo esc_html($statistics['total_workflows']); ?>
                            </div>
                            <div>
                                <strong><?php esc_html_e('Enabled:', 'polytrans'); ?></strong>
                                <?php echo esc_html($statistics['enabled_workflows']); ?>
                            </div>
                            <div>
                                <strong><?php esc_html_e('Disabled:', 'polytrans'); ?></strong>
                                <?php echo esc_html($statistics['disabled_workflows']); ?>
                            </div>
                        </div>

                        <?php if (!empty($statistics['languages'])): ?>
                            <div style="margin-top: 10px;">
                                <strong><?php esc_html_e('By Language:', 'polytrans'); ?></strong>
                                <?php foreach ($statistics['languages'] as $lang => $count): ?>
                                    <span style="margin-right: 15px;">
                                        <?php echo esc_html(strtoupper($lang) . ': ' . $count); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($workflows)): ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('No workflows found.', 'polytrans'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-workflows&action=new')); ?>">
                            <?php esc_html_e('Create your first workflow', 'polytrans'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php esc_html_e('Name', 'polytrans'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Language', 'polytrans'); ?></th>
                            <th style="width: 8%;"><?php esc_html_e('Steps', 'polytrans'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Status', 'polytrans'); ?></th>
                            <th style="width: 22%;"><?php esc_html_e('Description', 'polytrans'); ?></th>
                            <th style="width: 25%;"><?php esc_html_e('Actions', 'polytrans'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workflows as $workflow): ?>
                            <tr data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                <td>
                                    <strong><?php echo esc_html($workflow['name']); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $lang_index = array_search($workflow['language'], $langs);
                                    echo esc_html($lang_index !== false ? $lang_names[$lang_index] : strtoupper($workflow['language']));
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html(count($workflow['steps'] ?? [])); ?>
                                </td>
                                <td>
                                    <?php if (isset($workflow['enabled']) && $workflow['enabled']): ?>
                                        <span class="workflow-status enabled"><?php esc_html_e('Enabled', 'polytrans'); ?></span>
                                    <?php else: ?>
                                        <span class="workflow-status disabled"><?php esc_html_e('Disabled', 'polytrans'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($workflow['description'] ?? ''); ?>
                                </td>
                                <td class="row-actions-visible">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-workflows&action=edit&workflow_id=' . urlencode($workflow['id']))); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'polytrans'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-workflows&action=test&workflow_id=' . urlencode($workflow['id']))); ?>" class="button button-small">
                                        <?php esc_html_e('Test', 'polytrans'); ?>
                                    </a>
                                    <?php
                                    $is_enabled = isset($workflow['enabled']) && $workflow['enabled'];
                                    ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-execute-workflow&workflow_id=' . urlencode($workflow['id']))); ?>"
                                        class="button button-small button-primary"
                                        <?php echo !$is_enabled ? 'disabled aria-disabled="true" style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                                        <?php esc_html_e('Execute', 'polytrans'); ?>
                                    </a>
                                    <button type="button" class="button button-small workflow-duplicate" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                        <?php esc_html_e('Duplicate', 'polytrans'); ?>
                                    </button>
                                    <button type="button" class="button button-small button-link-delete workflow-delete" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                        <?php esc_html_e('Delete', 'polytrans'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Render workflow editor
     */
    private function render_workflow_editor($workflow_id, $is_new = false)
    {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        // Get workflow data
        if ($is_new) {
            $workflow = [
                'id' => 'workflow_' . uniqid(),
                'name' => '',
                'description' => '',
                'language' => 'en',
                'enabled' => true,
                'triggers' => [
                    'on_translation_complete' => true,
                    'manual_only' => false,
                    'conditions' => []
                ],
                'steps' => []
            ];
        } else {
            $workflow = $storage_manager->get_workflow($workflow_id);
            if (!$workflow) {
                wp_die(__('Workflow not found.', 'polytrans'));
            }

            // Ensure workflow has proper default values for any missing fields
            $workflow = $this->normalize_workflow_data($workflow);
        }

        // Get available languages
        $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
        $lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];

        // Get available variable documentation
        $data_providers = $workflow_manager->get_data_providers();
        $variable_docs = [];
        foreach ($data_providers as $provider) {
            $provider_docs = $provider->get_variable_documentation();
            $variable_docs = array_merge($variable_docs, $provider_docs);
        }

    ?>
        <div class="wrap">
            <h1><?php echo $is_new ? esc_html__('Add New Workflow', 'polytrans') : esc_html__('Edit Workflow', 'polytrans'); ?></h1>

            <form id="workflow-editor-form" method="post">
                <?php wp_nonce_field('polytrans_workflow_save', 'workflow_nonce'); ?>
                <input type="hidden" name="workflow_id" value="<?php echo esc_attr($workflow['id']); ?>">

                <div id="workflow-editor-container">
                    <!-- Workflow basic settings will be rendered here by JavaScript -->
                </div>

                <div class="workflow-editor-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Workflow', 'polytrans'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-workflows')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'polytrans'); ?>
                    </a>
                </div>
            </form>

            <!-- Legacy variable documentation panel removed - now using compact pills in JS -->
        </div>

        <script type="text/javascript">
            // Pass workflow data to JavaScript
            <?php
            // Add user label for attribution user if set
            if (!empty($workflow['attribution_user'])) {
                $attribution_user = get_user_by('id', $workflow['attribution_user']);
                if ($attribution_user) {
                    $workflow['attribution_user_label'] = $attribution_user->display_name . ' (' . $attribution_user->user_email . ')';
                } else {
                    // User not found, clear the invalid user ID
                    $workflow['attribution_user'] = null;
                }
            }
            ?>
            window.polytransWorkflowData = <?php echo json_encode($workflow); ?>;
            window.polytransLanguages = <?php echo json_encode(array_combine($langs, $lang_names)); ?>;
        </script>
    <?php
    }

    /**
     * Render workflow tester
     */
    private function render_workflow_tester($workflow_id)
    {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        $workflow = $storage_manager->get_workflow($workflow_id);
        if (!$workflow) {
            wp_die(__('Workflow not found.', 'polytrans'));
        }

    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Test Workflow', 'polytrans'); ?>: <?php echo esc_html($workflow['name']); ?></h1>

            <div id="workflow-tester-container">
                <!-- Workflow tester will be rendered here by JavaScript -->
            </div>
        </div>

        <script type="text/javascript">
            window.polytransWorkflowTestData = <?php echo json_encode($workflow); ?>;
        </script>
    <?php
    }

    /**
     * Render execute workflow page
     */
    public function render_execute_workflow_page()
    {
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        // Get URL parameters
        $workflow_id = isset($_GET['workflow_id']) ? sanitize_text_field($_GET['workflow_id']) : '';
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $language_filter = isset($_GET['language']) ? sanitize_text_field($_GET['language']) : '';
        $locked = isset($_GET['lock']) && $_GET['lock'] === '1';

        // Get all workflows (only enabled ones)
        $all_workflows_raw = $storage_manager->get_all_workflows();
        $all_workflows = array_values(array_filter($all_workflows_raw, function ($workflow) use ($language_filter) {
            $is_enabled = isset($workflow['enabled']) && $workflow['enabled'];

            // If language filter is set, also filter by language
            if ($language_filter && !empty($workflow['language'])) {
                return $is_enabled && $workflow['language'] === $language_filter;
            }

            return $is_enabled;
        }));

        // Pre-selected workflow data
        $selected_workflow = null;
        if ($workflow_id) {
            $selected_workflow = $storage_manager->get_workflow($workflow_id);
        }

        // Pre-selected post data
        $selected_post = null;
        if ($post_id) {
            $selected_post = get_post($post_id);
        }

        // Get available languages
        $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
        $lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];

    ?>
        <div class="wrap execute-workflow-page">
            <h1><?php esc_html_e('Execute Workflow Manually', 'polytrans'); ?></h1>
            <p class="description">
                <?php esc_html_e('Run post-processing workflows on existing translated posts on-demand.', 'polytrans'); ?>
            </p>

            <div id="execute-wizard" class="execute-wizard">

                <!-- Step 1: Select Workflow -->
                <div class="execute-step" id="step-workflow">
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Step 1: Select Workflow', 'polytrans'); ?></h2>
                        <div class="inside">
                            <?php if ($language_filter): ?>
                                <div class="notice notice-info inline" style="margin: 0 0 15px 0; padding: 10px;">
                                    <p style="margin: 0;">
                                        <span class="dashicons dashicons-info" style="vertical-align: middle;"></span>
                                        <?php
                                        $lang_name = '';
                                        $lang_index = array_search($language_filter, $langs);
                                        if ($lang_index !== false) {
                                            $lang_name = $lang_names[$lang_index];
                                        } else {
                                            $lang_name = strtoupper($language_filter);
                                        }
                                        printf(
                                            esc_html__('Showing workflows for %s posts only. This matches your selected post\'s language.', 'polytrans'),
                                            '<strong>' . esc_html($lang_name) . '</strong>'
                                        );
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="workflow-select"><?php esc_html_e('Workflow', 'polytrans'); ?></label>
                                    </th>
                                    <td>
                                        <select id="workflow-select" class="regular-text" <?php echo $locked ? 'disabled' : ''; ?>>
                                            <option value=""><?php esc_html_e('Select workflow...', 'polytrans'); ?></option>
                                            <?php foreach ($all_workflows as $workflow): ?>
                                                <option
                                                    value="<?php echo esc_attr($workflow['id']); ?>"
                                                    data-language="<?php echo esc_attr($workflow['language']); ?>"
                                                    data-steps="<?php echo esc_attr(count($workflow['steps'] ?? [])); ?>"
                                                    <?php selected($workflow_id, $workflow['id']); ?>>
                                                    <?php echo esc_html($workflow['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($locked && $selected_workflow): ?>
                                            <input type="hidden" id="workflow-id-locked" value="<?php echo esc_attr($workflow_id); ?>">
                                        <?php endif; ?>
                                        <p class="description">
                                            <?php esc_html_e('Select the workflow you want to execute. Workflows are filtered to match your post\'s language.', 'polytrans'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <div id="workflow-details" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">
                                <h4 style="margin-top: 0;"><?php esc_html_e('Workflow Details', 'polytrans'); ?></h4>
                                <p>
                                    <strong><?php esc_html_e('Target Language:', 'polytrans'); ?></strong>
                                    <span id="workflow-language"></span>
                                </p>
                                <p>
                                    <strong><?php esc_html_e('Number of Steps:', 'polytrans'); ?></strong>
                                    <span id="workflow-steps-count"></span>
                                </p>
                                <div id="workflow-steps-list"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Select Post -->
                <div class="execute-step" id="step-post" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Step 2: Select Post', 'polytrans'); ?></h2>
                        <div class="inside">

                            <!-- Post Search -->
                            <div style="margin-bottom: 25px;">
                                <label for="post-search" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
                                    <?php esc_html_e('Search for post:', 'polytrans'); ?>
                                </label>
                                <input
                                    type="text"
                                    id="post-search"
                                    class="regular-text"
                                    style="width: 100%; max-width: 500px; padding: 8px 12px; font-size: 14px;"
                                    placeholder="<?php esc_attr_e('Type post title to search...', 'polytrans'); ?>"
                                    <?php echo $locked ? 'disabled' : ''; ?> />
                                <div id="post-search-results" style="display: none; margin-top: 10px;"></div>
                            </div>

                            <!-- Or Divider -->
                            <div style="text-align: center; margin: 30px 0; position: relative;">
                                <span style="background: #fff; padding: 0 15px; position: relative; z-index: 1; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">
                                    <?php esc_html_e('OR', 'polytrans'); ?>
                                </span>
                                <hr style="position: absolute; top: 50%; left: 0; right: 0; margin: 0; z-index: 0; border: none; border-top: 1px solid #ddd;">
                            </div>

                            <!-- Direct ID Entry -->
                            <div style="margin-bottom: 25px;">
                                <label for="post-id-input" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">
                                    <?php esc_html_e('Enter post ID:', 'polytrans'); ?>
                                </label>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <input
                                        type="number"
                                        id="post-id-input"
                                        class="regular-text"
                                        style="width: 120px; padding: 8px 12px; font-size: 14px;"
                                        placeholder="<?php esc_attr_e('Post ID', 'polytrans'); ?>"
                                        <?php echo $locked ? 'disabled' : ''; ?> />
                                    <button
                                        type="button"
                                        id="verify-post-id"
                                        class="button button-secondary"
                                        style="padding: 6px 20px; height: auto;"
                                        <?php echo $locked ? 'disabled' : ''; ?>>
                                        <?php esc_html_e('Load Post', 'polytrans'); ?>
                                    </button>
                                </div>
                                <?php if ($locked && $selected_post): ?>
                                    <input type="hidden" id="post-id-locked" value="<?php echo esc_attr($post_id); ?>">
                                <?php endif; ?>
                            </div>

                            <!-- Selected Post Display -->
                            <div id="selected-post-display" style="display: none; margin-top: 25px; padding: 20px; background: #f0f9ff; border-left: 4px solid #0073aa; border-radius: 4px;">
                                <h4 style="margin: 0 0 15px 0; color: #0073aa; font-size: 14px; font-weight: 600;">
                                    ✓ <?php esc_html_e('Selected Post', 'polytrans'); ?>
                                </h4>
                                <div id="selected-posts-info"></div>
                            </div>

                            <!-- Error Display -->
                            <div id="post-selection-error" class="notice notice-error" style="display: none; margin-top: 20px;">
                                <p style="margin: 10px 0;"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Review & Execute -->
                <div class="execute-step" id="step-execute" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Step 3: Review & Execute', 'polytrans'); ?></h2>
                        <div class="inside">
                            <div id="execution-review">
                                <!-- Review information will be populated by JavaScript -->
                            </div>

                            <div class="execution-warning" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                                <p style="margin: 0;">
                                    <strong>⚠️ <?php esc_html_e('Warning:', 'polytrans'); ?></strong>
                                    <?php esc_html_e('This will modify the translated post content. Make sure you have selected the correct workflow and post.', 'polytrans'); ?>
                                </p>
                            </div>

                            <div style="text-align: center; margin-top: 20px;">
                                <button type="button" id="execute-workflow-btn" class="button button-primary button-large">
                                    <?php esc_html_e('Execute Workflow', 'polytrans'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Execution Status -->
                <div class="execute-step" id="step-status" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle">📊 <?php esc_html_e('Execution Status', 'polytrans'); ?></h2>
                        <div class="inside">
                            <div id="execution-status-content">
                                <!-- Status will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Display -->
                <div class="execute-step" id="step-results" style="display: none;">
                    <div class="postbox">
                        <h2 class="hndle" id="results-title"></h2>
                        <div class="inside">
                            <div id="execution-results-content">
                                <!-- Results will be populated by JavaScript -->
                            </div>

                            <div style="text-align: center; margin-top: 20px;">
                                <button type="button" id="execute-another-btn" class="button button-primary">
                                    <?php esc_html_e('Execute Another Workflow', 'polytrans'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script type="text/javascript">
            window.polytransExecuteWorkflowData = {
                workflows: <?php echo json_encode($all_workflows); ?>,
                selectedWorkflow: <?php echo $selected_workflow ? json_encode($selected_workflow) : 'null'; ?>,
                selectedPost: <?php echo $selected_post ? json_encode([
                                    'ID' => $selected_post->ID,
                                    'post_title' => $selected_post->post_title,
                                    'post_type' => $selected_post->post_type
                                ]) : 'null'; ?>,
                locked: <?php echo $locked ? 'true' : 'false'; ?>,
                workflowId: '<?php echo esc_js($workflow_id); ?>',
                postId: <?php echo $post_id; ?>,
                languageFilter: '<?php echo esc_js($language_filter); ?>'
            };
        </script>
<?php
    }

    /**
     * AJAX: Save workflow
     */
    public function ajax_save_workflow()
    {
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $workflow_data = $_POST['workflow'] ?? [];

        if (empty($workflow_data)) {
            wp_send_json_error('No workflow data provided');
            return;
        }

        // Remove WordPress magic quotes if they exist
        $workflow_data = wp_unslash($workflow_data);

        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        // Sanitize workflow data
        $workflow = $this->sanitize_workflow_data($workflow_data);
        $saveResult = $storage_manager->save_workflow($workflow);

        if ($saveResult['success']) {
            wp_send_json_success([
                'message' => __('Workflow saved successfully!', 'polytrans'),
                'workflow_id' => $workflow['id']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save workflow.', 'polytrans'),
                'errors' => $saveResult['errors'] ?? []
            ]);
        }
    }

    /**
     * AJAX: Delete workflow
     */
    public function ajax_delete_workflow()
    {
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $workflow_id = sanitize_text_field($_POST['workflow_id'] ?? '');

        if (empty($workflow_id)) {
            wp_send_json_error('Workflow ID required');
            return;
        }

        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        if ($storage_manager->delete_workflow($workflow_id)) {
            wp_send_json_success('Workflow deleted successfully');
        } else {
            wp_send_json_error('Failed to delete workflow');
        }
    }

    /**
     * AJAX: Duplicate workflow
     */
    public function ajax_duplicate_workflow()
    {
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $workflow_id = sanitize_text_field($_POST['workflow_id'] ?? '');
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');

        if (empty($workflow_id)) {
            wp_send_json_error('Workflow ID required');
            return;
        }

        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();

        $new_workflow_id = $storage_manager->duplicate_workflow($workflow_id, $new_name);

        if ($new_workflow_id) {
            wp_send_json_success([
                'message' => __('Workflow duplicated successfully!', 'polytrans'),
                'new_workflow_id' => $new_workflow_id
            ]);
        } else {
            wp_send_json_error('Failed to duplicate workflow');
        }
    }

    /**
     * AJAX: Get workflow data
     */
    public function ajax_get_workflow()
    {
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $workflow_id = sanitize_text_field($_POST['workflow_id'] ?? '');

        if (empty($workflow_id)) {
            wp_send_json_error('Workflow ID required');
            return;
        }

        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $storage_manager = $workflow_manager->get_storage_manager();
        $workflow = $storage_manager->get_workflow($workflow_id);

        if ($workflow) {
            wp_send_json_success($workflow);
        } else {
            wp_send_json_error('Workflow not found');
        }
    }

    /**
     * AJAX: Test workflow
     */
    public function ajax_test_workflow()
    {
        // This is handled by the workflow manager
        $workflow_manager = PolyTrans_Workflow_Manager::get_instance();
        $workflow_manager->ajax_test_workflow();
    }

    /**
     * AJAX: Search posts
     */
    public function ajax_search_posts()
    {
        // Delegate to the autocomplete class which now supports language filtering
        $post_autocomplete = PolyTrans_Post_Autocomplete::get_instance();
        $post_autocomplete->ajax_search_posts();
    }

    /**
     * AJAX: Get post data
     */
    public function ajax_get_post_data()
    {
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $target_language = sanitize_text_field($_POST['target_language'] ?? '');

        if (empty($post_id)) {
            wp_send_json_error('Post ID required');
            return;
        }

        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('You do not have permission to edit this post');
            return;
        }

        // Get post language
        $post_language = function_exists('pll_get_post_language') ?
            pll_get_post_language($post_id) : 'en';

        // If target_language is provided, validate it matches the post language
        if (!empty($target_language) && $post_language !== $target_language) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Selected post is in %s but workflow requires %s', 'polytrans'),
                    $post_language,
                    $target_language
                )
            ]);
            return;
        }

        // Return post data - using it as both original and translated
        // For manual workflows, we work directly with the selected post
        $post_data = [
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'language' => $post_language,
            'edit_url' => get_edit_post_link($post->ID, 'raw')
        ];

        wp_send_json_success([
            'original_post' => $post_data,
            'translated_post' => $post_data
        ]);
    }

    /**
     * AJAX: Load OpenAI assistants for workflow
     */
    public function ajax_load_openai_assistants_for_workflow()
    {
        // Check nonce
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Create OpenAI client from settings
        $client = PolyTrans_OpenAI_Client::from_settings();
        if (!$client) {
            wp_send_json_error('OpenAI API key not configured');
            return;
        }

        // Load all assistants using the client
        $all_assistants = $client->get_all_assistants();

        if (empty($all_assistants)) {
            wp_send_json_error('No assistants found');
            return;
        }

        // Transform assistants data
        $assistants = array_map(function ($assistant) {
            return [
                'id' => $assistant['id'],
                'name' => $assistant['name'] ?? 'Unnamed Assistant',
                'description' => $assistant['description'] ?? '',
                'model' => $assistant['model'] ?? 'gpt-4'
            ];
        }, $all_assistants);

        wp_send_json_success($assistants);
    }

    /**
     * AJAX: Load managed assistants for workflow editor
     */
    public function ajax_load_managed_assistants()
    {
        // Check nonce
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get all managed assistants
        $assistants = PolyTrans_Assistant_Manager::get_all_assistants();

        if (empty($assistants)) {
            wp_send_json_error('No managed assistants found. Create one in PolyTrans > AI Assistants.');
            return;
        }

        wp_send_json_success($assistants);
    }

    /**
     * Get OpenAI assistants from the settings provider
     */
    private function get_openai_assistants()
    {
        // Check if OpenAI settings provider class exists
        if (!class_exists('PolyTrans_OpenAI_Settings_Provider')) {
            return [];
        }

        try {
            $provider = new PolyTrans_OpenAI_Settings_Provider();
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('get_assistants');
            $method->setAccessible(true);
            return $method->invoke($provider);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get OpenAI models from the settings provider
     */
    private function get_openai_models()
    {
        // Check if OpenAI settings provider class exists
        if (!class_exists('PolyTrans_OpenAI_Settings_Provider')) {
            return [];
        }

        try {
            $provider = new PolyTrans_OpenAI_Settings_Provider();
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('get_grouped_models');
            $method->setAccessible(true);
            return $method->invoke($provider);
        } catch (Exception $e) {
            // Fallback to basic models if we can't access the provider
            return [
                'GPT-4o Models' => [
                    'gpt-4o' => 'GPT-4o (Latest)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cost-effective)',
                ],
                'GPT-4 Models' => [
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-4' => 'GPT-4',
                ],
                'GPT-3.5 Models' => [
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ]
            ];
        }
    }

    /**
     * Sanitize workflow data from form submission
     */
    private function sanitize_workflow_data($workflow_data)
    {
        $attribution_user = intval($workflow_data['attribution_user'] ?? 0);
        if ($attribution_user === 0) {
            $attribution_user = null;
        }

        return [
            'id' => sanitize_text_field($workflow_data['id'] ?? ''),
            'name' => sanitize_text_field($workflow_data['name'] ?? ''),
            'description' => sanitize_textarea_field($workflow_data['description'] ?? ''),
            'language' => sanitize_text_field($workflow_data['language'] ?? 'en'),
            'enabled' => $workflow_data['enabled'] === 'true',
            'attribution_user' => $attribution_user,
            'triggers' => [
                'on_translation_complete' => !empty($workflow_data['triggers']['on_translation_complete']),
                'manual_only' => $workflow_data['triggers']['manual_only'] === 'true',
                'conditions' => $this->sanitize_trigger_conditions($workflow_data['triggers']['conditions'] ?? [])
            ],
            'steps' => $this->sanitize_workflow_steps($workflow_data['steps'] ?? [])
        ];
    }

    /**
     * Sanitize trigger conditions
     */
    private function sanitize_trigger_conditions($conditions)
    {
        $sanitized = [];

        if (isset($conditions['post_type']) && is_array($conditions['post_type'])) {
            $sanitized['post_type'] = array_map('sanitize_text_field', $conditions['post_type']);
        }

        if (isset($conditions['category']) && is_array($conditions['category'])) {
            $sanitized['category'] = array_map('sanitize_text_field', $conditions['category']);
        }

        return $sanitized;
    }

    /**
     * Sanitize workflow steps
     */
    private function sanitize_workflow_steps($steps)
    {
        if (!is_array($steps)) {
            return [];
        }

        $sanitized_steps = [];

        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }

            $sanitized_step = [
                'id' => sanitize_text_field($step['id'] ?? ''),
                'name' => sanitize_text_field($step['name'] ?? ''),
                'type' => sanitize_text_field($step['type'] ?? ''),
                'enabled' => !empty($step['enabled'])
            ];

            // Sanitize step-specific fields
            switch ($sanitized_step['type']) {
                case 'ai_assistant':
                    $sanitized_step['system_prompt'] = $this->sanitize_prompt_field($step['system_prompt'] ?? '');
                    $sanitized_step['user_message'] = $this->sanitize_prompt_field($step['user_message'] ?? '');
                    $sanitized_step['model'] = $this->sanitize_model_field($step['model'] ?? '');
                    $sanitized_step['expected_format'] = sanitize_text_field($step['expected_format'] ?? 'text');
                    $sanitized_step['max_tokens'] = !empty($step['max_tokens']) ? intval($step['max_tokens']) : null;
                    $sanitized_step['temperature'] = !empty($step['temperature']) ? floatval($step['temperature']) : 0.7;

                    if (isset($step['output_variables']) && is_array($step['output_variables'])) {
                        $sanitized_step['output_variables'] = array_map('sanitize_text_field', $step['output_variables']);
                    } elseif (isset($step['output_variables'])) {
                        // Handle comma-separated string
                        $output_vars = explode(',', $step['output_variables']);
                        $sanitized_step['output_variables'] = array_map('trim', array_map('sanitize_text_field', $output_vars));
                    }

                    // Handle output actions
                    if (isset($step['output_actions']) && is_array($step['output_actions'])) {
                        $sanitized_step['output_actions'] = $this->sanitize_output_actions($step['output_actions']);
                    }
                    break;

                case 'predefined_assistant':
                    $sanitized_step['assistant_id'] = sanitize_text_field($step['assistant_id'] ?? '');
                    $sanitized_step['user_message'] = $this->sanitize_prompt_field($step['user_message'] ?? '');

                    if (isset($step['output_variables']) && is_array($step['output_variables'])) {
                        $sanitized_step['output_variables'] = array_map('sanitize_text_field', $step['output_variables']);
                    } elseif (isset($step['output_variables'])) {
                        // Handle comma-separated string
                        $output_vars = explode(',', $step['output_variables']);
                        $sanitized_step['output_variables'] = array_map('trim', array_map('sanitize_text_field', $output_vars));
                    }

                    // Handle output actions
                    if (isset($step['output_actions']) && is_array($step['output_actions'])) {
                        $sanitized_step['output_actions'] = $this->sanitize_output_actions($step['output_actions']);
                    }
                    break;

                case 'managed_assistant':
                    $sanitized_step['assistant_id'] = intval($step['assistant_id'] ?? 0);

                    // Handle output actions
                    if (isset($step['output_actions']) && is_array($step['output_actions'])) {
                        $sanitized_step['output_actions'] = $this->sanitize_output_actions($step['output_actions']);
                    }
                    break;
            }

            $sanitized_steps[] = $sanitized_step;
        }

        return $sanitized_steps;
    }

    /**
     * Sanitize output actions
     */
    private function sanitize_output_actions($output_actions)
    {
        if (!is_array($output_actions)) {
            return [];
        }

        $sanitized_actions = [];

        foreach ($output_actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $sanitized_action = [
                'type' => sanitize_text_field($action['type'] ?? ''),
                'source_variable' => sanitize_text_field($action['source_variable'] ?? ''),
                'target' => sanitize_text_field($action['target'] ?? '')
            ];

            // Only add if required fields are present
            // Allow empty source_variable as it can be auto-detected
            if (!empty($sanitized_action['type'])) {
                $sanitized_actions[] = $sanitized_action;
            }
        }

        return $sanitized_actions;
    }

    /**
     * Sanitize prompt field while preserving angle brackets and quotes
     */
    private function sanitize_prompt_field($prompt)
    {
        if (empty($prompt)) {
            return '';
        }

        // Remove null bytes and validate UTF-8
        $prompt = str_replace(chr(0), '', $prompt);
        $prompt = wp_check_invalid_utf8($prompt);

        // Remove dangerous script tags and on* attributes while preserving other content
        $prompt = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $prompt);
        $prompt = preg_replace('/\son\w+\s*=\s*["\'][^"\']*["\']/i', '', $prompt);

        // Normalize line breaks
        $prompt = str_replace(array("\r\n", "\r"), "\n", $prompt);

        return trim($prompt);
    }

    /**
     * Sanitize model field and validate against available models
     */
    private function sanitize_model_field($model)
    {
        if (empty($model)) {
            return '';
        }

        $model = sanitize_text_field($model);

        // Get available models from the OpenAI settings provider
        $available_models = $this->get_openai_models();

        // Flatten the grouped models to get all valid model values
        $valid_models = [];
        foreach ($available_models as $group => $models) {
            $valid_models = array_merge($valid_models, array_keys($models));
        }

        // Return the model if it's valid, otherwise return empty string
        return in_array($model, $valid_models) ? $model : '';
    }

    /**
     * Normalize workflow data to ensure all required fields are present with default values
     */
    private function normalize_workflow_data($workflow)
    {
        // Ensure triggers section exists with all required fields
        if (!isset($workflow['triggers'])) {
            $workflow['triggers'] = [];
        }

        // Set default values for missing trigger fields
        $workflow['triggers'] = array_merge([
            'on_translation_complete' => true,
            'manual_only' => false,
            'conditions' => []
        ], $workflow['triggers']);

        // Ensure other required fields exist
        $workflow = array_merge([
            'id' => '',
            'name' => '',
            'description' => '',
            'language' => 'en',
            'enabled' => true,
            'attribution_user' => null,
            'steps' => []
        ], $workflow);

        return $workflow;
    }
}
