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
            'manage_options',
            'polytrans-workflows',
            [$this, 'render_workflow_page']
        );
    }

    /**
     * Enqueue assets for workflow management
     */
    public function enqueue_assets($hook_suffix)
    {
        if (strpos($hook_suffix, 'polytrans-workflows') === false) {
            return;
        }

        wp_enqueue_script(
            'polytrans-workflows',
            POLYTRANS_PLUGIN_URL . 'assets/js/postprocessing-admin.js',
            ['jquery', 'wp-util'],
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
                            <th style="width: 30%;"><?php esc_html_e('Name', 'polytrans'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Language', 'polytrans'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Steps', 'polytrans'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Status', 'polytrans'); ?></th>
                            <th style="width: 25%;"><?php esc_html_e('Description', 'polytrans'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Actions', 'polytrans'); ?></th>
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

            <!-- Variable Documentation Panel -->
            <div id="variable-docs-panel" style="margin-top: 30px;">
                <h2><?php esc_html_e('Available Variables', 'polytrans'); ?></h2>
                <div class="postbox">
                    <div class="inside">
                        <p><?php esc_html_e('You can use these variables in your system prompts by wrapping them in curly braces, e.g., {original_post.title}', 'polytrans'); ?></p>
                        <div class="variable-docs-grid">
                            <?php foreach ($variable_docs as $var_name => $doc): ?>
                                <div class="variable-doc-item">
                                    <code class="variable-name"><?php echo esc_html($var_name); ?></code>
                                    <p class="variable-description"><?php echo esc_html($doc['description']); ?></p>
                                    <?php if (isset($doc['example'])): ?>
                                        <code class="variable-example"><?php echo esc_html($doc['example']); ?></code>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
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
     * AJAX: Save workflow
     */
    public function ajax_save_workflow()
    {
        if (!check_ajax_referer('polytrans_workflows_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
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

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        if (empty($post_id)) {
            wp_send_json_error('Post ID required');
            return;
        }

        $post_autocomplete = PolyTrans_Post_Autocomplete::get_instance();
        $post_data = $post_autocomplete->get_post_data($post_id);

        if ($post_data) {
            wp_send_json_success($post_data);
        } else {
            wp_send_json_error('Post not found');
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
            'enabled' => !empty($workflow_data['enabled']),
            'attribution_user' => $attribution_user,
            'triggers' => [
                'on_translation_complete' => !empty($workflow_data['triggers']['on_translation_complete']),
                'manual_only' => $workflow_data['triggers']['manual_only'] !== 'false',
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
            if (!empty($sanitized_action['type']) && !empty($sanitized_action['source_variable'])) {
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
