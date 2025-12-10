<?php

/**
 * AI Assistants Menu
 * 
 * Handles the admin menu page for managing AI assistants.
 * Part of Phase 1: AI Assistants Management System.
 */

namespace PolyTrans\Menu;

use PolyTrans\Assistants\AssistantManager;
use PolyTrans\Assistants\AssistantMigration;

if (!defined('ABSPATH')) {
    exit;
}

class AssistantsMenu
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
        add_action('wp_ajax_polytrans_save_assistant', [$this, 'ajax_save_assistant']);
        add_action('wp_ajax_polytrans_delete_assistant', [$this, 'ajax_delete_assistant']);
        add_action('wp_ajax_polytrans_get_assistant', [$this, 'ajax_get_assistant']);
        add_action('wp_ajax_polytrans_test_assistant', [$this, 'ajax_test_assistant']);
        add_action('wp_ajax_polytrans_migrate_workflows', [$this, 'ajax_migrate_workflows']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'polytrans',
            __('AI Assistants', 'polytrans'),
            __('AI Assistants', 'polytrans'),
            'manage_options',
            'polytrans-assistants',
            [$this, 'render_assistants_page']
        );
    }

    /**
     * Enqueue assets for assistant management
     */
    public function enqueue_assets($hook_suffix)
    {
        // Enqueue only on assistants page
        if (strpos($hook_suffix, 'polytrans-assistants') === false) {
            return;
        }

        // Enqueue prompt editor module (reusable component)
        wp_enqueue_script(
            'polytrans-prompt-editor',
            POLYTRANS_PLUGIN_URL . 'assets/js/prompt-editor.js',
            ['jquery'],
            POLYTRANS_VERSION,
            true
        );

        wp_enqueue_script(
            'polytrans-assistants',
            POLYTRANS_PLUGIN_URL . 'assets/js/assistants-admin.js',
            ['jquery', 'wp-util', 'polytrans-prompt-editor'],
            POLYTRANS_VERSION,
            true
        );

        // Enqueue postprocessing CSS for shared prompt editor styles
        wp_enqueue_style(
            'polytrans-postprocessing',
            POLYTRANS_PLUGIN_URL . 'assets/css/postprocessing-admin.css',
            [],
            POLYTRANS_VERSION
        );

        wp_enqueue_style(
            'polytrans-assistants',
            POLYTRANS_PLUGIN_URL . 'assets/css/assistants-admin.css',
            ['polytrans-postprocessing'],
            POLYTRANS_VERSION
        );

        // Localize script
        wp_localize_script('polytrans-assistants', 'polytransAssistants', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polytrans_assistants'),
            'models' => $this->get_model_options(),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this assistant?', 'polytrans'),
                'saveSuccess' => __('Assistant saved successfully.', 'polytrans'),
                'saveError' => __('Failed to save assistant.', 'polytrans'),
                'deleteSuccess' => __('Assistant deleted successfully.', 'polytrans'),
                'deleteError' => __('Failed to delete assistant.', 'polytrans'),
                'testSuccess' => __('Test completed successfully.', 'polytrans'),
                'testError' => __('Test failed.', 'polytrans'),
                'loading' => __('Loading...', 'polytrans'),
                'requiredField' => __('This field is required.', 'polytrans'),
            ],
            'providers' => [
                'openai' => [
                    'label' => __('OpenAI', 'polytrans'),
                    'models' => ['gpt-4', 'gpt-4-turbo-preview', 'gpt-3.5-turbo']
                ],
                'claude' => [
                    'label' => __('Claude (Anthropic)', 'polytrans'),
                    'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku']
                ],
                'gemini' => [
                    'label' => __('Gemini (Google)', 'polytrans'),
                    'models' => ['gemini-pro', 'gemini-pro-vision']
                ]
            ],
            'responseFormats' => [
                'text' => __('Text', 'polytrans'),
                'json' => __('JSON', 'polytrans')
            ]
        ]);
    }

    /**
     * Render assistants management page
     */
    public function render_assistants_page()
    {
        // Get current action
        $action = $_GET['action'] ?? 'list';
        $assistant_id = isset($_GET['assistant_id']) ? intval($_GET['assistant_id']) : 0;

        switch ($action) {
            case 'edit':
            case 'new':
                $this->render_assistant_editor($assistant_id, $action === 'new');
                break;

            default:
                $this->render_assistant_list();
                break;
        }
    }

    /**
     * Render assistant list
     */
    private function render_assistant_list()
    {
        $assistants = AssistantManager::get_all_assistants();
        $migration_status = AssistantMigration::get_migration_status();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('AI Assistants', 'polytrans'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-assistants&action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'polytrans'); ?>
            </a>
            <hr class="wp-header-end">

            <p class="description">
                <?php esc_html_e('Manage AI assistants for content processing, translation enhancement, and workflow automation.', 'polytrans'); ?>
            </p>

            <?php if ($migration_status['migration_needed']) : ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e('Migration Available:', 'polytrans'); ?></strong>
                        <?php 
                        printf(
                            esc_html__('Found %d legacy workflow steps that can be migrated to managed assistants.', 'polytrans'),
                            $migration_status['ai_assistant_steps']
                        ); 
                        ?>
                    </p>
                    <p>
                        <button type="button" id="migrate-workflows-btn" class="button button-primary">
                            <?php esc_html_e('Migrate Workflows Now', 'polytrans'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin: 0 10px;"></span>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (empty($assistants)) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No assistants found. Create your first assistant to get started!', 'polytrans'); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="column-name"><?php esc_html_e('Name', 'polytrans'); ?></th>
                            <th scope="col" class="column-provider"><?php esc_html_e('Provider', 'polytrans'); ?></th>
                            <th scope="col" class="column-model"><?php esc_html_e('Model', 'polytrans'); ?></th>
                            <th scope="col" class="column-format"><?php esc_html_e('Response Format', 'polytrans'); ?></th>
                            <th scope="col" class="column-created"><?php esc_html_e('Created', 'polytrans'); ?></th>
                            <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'polytrans'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assistants as $assistant) : ?>
                            <tr>
                                <td class="column-name">
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-assistants&action=edit&assistant_id=' . $assistant['id'])); ?>">
                                            <?php echo esc_html($assistant['name']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td class="column-provider">
                                    <?php echo esc_html(ucfirst($assistant['provider'])); ?>
                                </td>
                                <td class="column-model">
                                    <?php echo esc_html($assistant['model']); ?>
                                </td>
                                <td class="column-format">
                                    <?php echo esc_html($assistant['response_format']); ?>
                                </td>
                                <td class="column-created">
                                    <?php
                                    if (!empty($assistant['created_at'])) {
                                        echo esc_html(mysql2date(get_option('date_format'), $assistant['created_at']));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-assistants&action=edit&assistant_id=' . $assistant['id'])); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'polytrans'); ?>
                                    </a>
                                    <button type="button" class="button button-small button-link-delete assistant-delete" data-assistant-id="<?php echo esc_attr($assistant['id']); ?>">
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
     * Render assistant editor
     */
    private function render_assistant_editor($assistant_id, $is_new = false)
    {
        // Get assistant data
        if ($is_new) {
            $assistant = [
                'id' => 0,
                'name' => '',
                'provider' => 'openai',
                'model' => 'gpt-4',
                'prompt_template' => '',
                'response_format' => 'text',
                'config' => [
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                ]
            ];
        } else {
            $assistant = AssistantManager::get_assistant($assistant_id);
            if (!$assistant) {
                wp_die(__('Assistant not found.', 'polytrans'));
            }
            
            // Map expected_format to response_format for UI consistency
            if (!isset($assistant['response_format']) && isset($assistant['expected_format'])) {
                $assistant['response_format'] = $assistant['expected_format'];
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo $is_new ? esc_html__('Add New Assistant', 'polytrans') : esc_html__('Edit Assistant', 'polytrans'); ?></h1>

            <form id="assistant-editor-form" method="post">
                <?php wp_nonce_field('polytrans_assistant_save', 'assistant_nonce'); ?>
                <input type="hidden" name="assistant_id" value="<?php echo esc_attr($assistant['id']); ?>">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="assistant-name"><?php esc_html_e('Name', 'polytrans'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="assistant-name" name="name" class="regular-text" value="<?php echo esc_attr($assistant['name']); ?>" required>
                                <p class="description"><?php esc_html_e('A descriptive name for this assistant.', 'polytrans'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-provider"><?php esc_html_e('Provider', 'polytrans'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select id="assistant-provider" name="provider" required>
                                    <option value="openai" <?php selected($assistant['provider'], 'openai'); ?>><?php esc_html_e('OpenAI', 'polytrans'); ?></option>
                                    <option value="claude" <?php selected($assistant['provider'], 'claude'); ?>><?php esc_html_e('Claude (Anthropic)', 'polytrans'); ?></option>
                                    <option value="gemini" <?php selected($assistant['provider'], 'gemini'); ?>><?php esc_html_e('Gemini (Google)', 'polytrans'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('AI provider to use for this assistant.', 'polytrans'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-model"><?php esc_html_e('AI Model', 'polytrans'); ?></label>
                            </th>
                            <td>
                                <select id="assistant-model" name="model" class="regular-text">
                                    <?php
                                    $models = $this->get_model_options();
                                    $current_model = $assistant['model'];
                                    
                                    // Add "Use Global Setting" option
                                    $selected = empty($current_model) ? 'selected' : '';
                                    echo '<option value="" ' . $selected . '>' . esc_html__('Use Global Setting', 'polytrans') . '</option>';
                                    
                                    foreach ($models as $group_name => $group_models) {
                                        echo '<optgroup label="' . esc_attr($group_name) . '">';
                                        foreach ($group_models as $model_value => $model_label) {
                                            $selected = ($current_model === $model_value) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($model_value) . '" ' . $selected . '>' . esc_html($model_label) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Select the AI model to use for this assistant. "Use Global Setting" will use the default model from plugin settings.', 'polytrans'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-system-prompt"><?php esc_html_e('System Instructions', 'polytrans'); ?> <span class="required">*</span></label>
                            </th>
                            <td class="workflow-field-with-variables">
                                <div id="system-prompt-editor-container"></div>
                                <p class="description"><?php esc_html_e('Instructions that define how the assistant should behave. This is static and doesn\'t change between requests.', 'polytrans'); ?></p>
                                <p class="description"><strong><?php esc_html_e('Example:', 'polytrans'); ?></strong> "You are a content quality expert. Analyze posts for grammar, SEO, and readability. Always respond in JSON format."</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-user-message"><?php esc_html_e('User Message Template', 'polytrans'); ?></label>
                            </th>
                            <td class="workflow-field-with-variables">
                                <div id="user-message-editor-container"></div>
                                <p class="description"><?php esc_html_e('Template for the user message with dynamic data. Use Twig syntax for variables: {{ variable_name }}', 'polytrans'); ?></p>
                                <p class="description"><strong><?php esc_html_e('Example:', 'polytrans'); ?></strong> "Title: {{ title }}\nContent: {{ content }}\n\nPlease analyze this content."</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-response-format"><?php esc_html_e('Response Format', 'polytrans'); ?></label>
                            </th>
                            <td>
                                <select id="assistant-response-format" name="response_format">
                                    <option value="text" <?php selected($assistant['response_format'], 'text'); ?>><?php esc_html_e('Text', 'polytrans'); ?></option>
                                    <option value="json" <?php selected($assistant['response_format'], 'json'); ?>><?php esc_html_e('JSON', 'polytrans'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Expected response format from the assistant.', 'polytrans'); ?></p>
                            </td>
                        </tr>

                        <tr id="expected-output-schema-row" style="display: none;">
                            <th scope="row">
                                <label for="assistant-expected-output-schema"><?php esc_html_e('Expected Output Schema', 'polytrans'); ?></label>
                            </th>
                            <td>
                                <textarea id="assistant-expected-output-schema" name="expected_output_schema" class="large-text code" rows="8" placeholder='{"field_name": "type"}'><?php echo esc_textarea(!empty($assistant['expected_output_schema']) ? wp_json_encode($assistant['expected_output_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Define the expected JSON structure for AI responses. Format: {"field": "type"}', 'polytrans'); ?><br>
                                    <strong><?php esc_html_e('Supported types:', 'polytrans'); ?></strong> string, number, array, object, boolean<br>
                                    <strong><?php esc_html_e('Example:', 'polytrans'); ?></strong> {"title": "string", "content": "string", "meta": "object"}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-temperature"><?php esc_html_e('Temperature', 'polytrans'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="assistant-temperature" name="config[temperature]" class="small-text" min="0" max="2" step="0.1" value="<?php echo esc_attr($assistant['config']['temperature'] ?? 0.7); ?>">
                                <p class="description"><?php esc_html_e('Controls randomness: 0 = focused, 2 = creative. Default: 0.7', 'polytrans'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-max-tokens"><?php esc_html_e('Max Tokens', 'polytrans'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="assistant-max-tokens" name="config[max_tokens]" class="small-text" min="1" max="32000" value="<?php echo esc_attr($assistant['config']['max_tokens'] ?? 2000); ?>">
                                <p class="description"><?php esc_html_e('Maximum length of the response. Default: 2000', 'polytrans'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="assistant-editor-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Assistant', 'polytrans'); ?>
                    </button>
                    <button type="button" id="test-assistant-btn" class="button">
                        <?php esc_html_e('Test Assistant', 'polytrans'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-assistants')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'polytrans'); ?>
                    </a>
                </div>
            </form>

            <!-- Test Results Container -->
            <div id="test-results-container" style="display: none; margin-top: 20px;">
                <h2><?php esc_html_e('Test Results', 'polytrans'); ?></h2>
                <div id="test-results-content"></div>
            </div>
        </div>

        <script type="text/javascript">
            // Pass assistant data to JavaScript
            window.polytransAssistantData = <?php echo json_encode($assistant); ?>;
        </script>
        <?php
    }

    /**
     * AJAX: Save assistant
     */
    public function ajax_save_assistant()
    {
        check_ajax_referer('polytrans_assistants', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'polytrans')]);
        }

        $assistant_id = isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
        $system_prompt = isset($_POST['system_prompt']) ? wp_unslash($_POST['system_prompt']) : '';
        $user_message_template = isset($_POST['user_message_template']) ? wp_unslash($_POST['user_message_template']) : '';
        $response_format = isset($_POST['response_format']) ? sanitize_text_field(wp_unslash($_POST['response_format'])) : 'text';
        $expected_output_schema = isset($_POST['expected_output_schema']) ? wp_unslash($_POST['expected_output_schema']) : null;
        $config = isset($_POST['config']) ? wp_unslash($_POST['config']) : [];

        // Validate required fields
        if (empty($name) || empty($provider) || empty($system_prompt)) {
            wp_send_json_error(['message' => __('Required fields are missing.', 'polytrans')]);
        }

        // Prepare API parameters
        $api_parameters = [
            'model' => $model,
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? 2000
        ];

        // Prepare assistant data matching Assistant Manager structure
        $assistant_data = [
            'name' => $name,
            'provider' => $provider,
            'system_prompt' => $system_prompt,
            'user_message_template' => $user_message_template,
            'api_parameters' => json_encode($api_parameters),
            'expected_format' => $response_format,
            'expected_output_schema' => $expected_output_schema,
            'output_variables' => null
        ];

        // Save or update assistant
        try {
            if ($assistant_id > 0) {
                $result = AssistantManager::update_assistant($assistant_id, $assistant_data);
            } else {
                $result = AssistantManager::create_assistant($assistant_data);
                $assistant_id = $result;
            }

            if ($result) {
                wp_send_json_success([
                    'message' => __('Assistant saved successfully.', 'polytrans'),
                    'assistant_id' => $assistant_id
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to save assistant.', 'polytrans')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Delete assistant
     */
    public function ajax_delete_assistant()
    {
        check_ajax_referer('polytrans_assistants', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'polytrans')]);
        }

        $assistant_id = isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0;

        if ($assistant_id <= 0) {
            wp_send_json_error(['message' => __('Invalid assistant ID.', 'polytrans')]);
        }

        try {
            $result = AssistantManager::delete_assistant($assistant_id);

            if ($result) {
                wp_send_json_success(['message' => __('Assistant deleted successfully.', 'polytrans')]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete assistant.', 'polytrans')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get assistant
     */
    public function ajax_get_assistant()
    {
        check_ajax_referer('polytrans_assistants', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'polytrans')]);
        }

        $assistant_id = isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0;

        if ($assistant_id <= 0) {
            wp_send_json_error(['message' => __('Invalid assistant ID.', 'polytrans')]);
        }

        try {
            $assistant = AssistantManager::get_assistant($assistant_id);

            if ($assistant) {
                wp_send_json_success(['assistant' => $assistant]);
            } else {
                wp_send_json_error(['message' => __('Assistant not found.', 'polytrans')]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Test assistant
     */
    public function ajax_test_assistant()
    {
        check_ajax_referer('polytrans_assistants', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'polytrans')]);
        }

        $assistant_id = isset($_POST['assistant_id']) ? intval($_POST['assistant_id']) : 0;
        $test_variables = isset($_POST['test_variables']) ? wp_unslash($_POST['test_variables']) : [];

        if ($assistant_id <= 0) {
            wp_send_json_error(['message' => __('Invalid assistant ID.', 'polytrans')]);
        }

        try {
            $result = PolyTrans_Assistant_Executor::execute($assistant_id, $test_variables);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => __('Test completed successfully.', 'polytrans'),
                    'result' => $result
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Test failed.', 'polytrans'),
                    'error' => $result['error']
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Migrate workflows to managed assistants
     */
    public function ajax_migrate_workflows()
    {
        check_ajax_referer('polytrans_assistants', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'polytrans')]);
        }

        try {
            $stats = AssistantMigration::migrate_workflows_to_managed_assistants();

            if (!empty($stats['errors'])) {
                wp_send_json_error([
                    'message' => __('Migration completed with errors.', 'polytrans'),
                    'stats' => $stats
                ]);
            } else {
                wp_send_json_success([
                    'message' => sprintf(
                        __('Migration completed successfully! Migrated %d steps and created %d assistants.', 'polytrans'),
                        $stats['steps_migrated'],
                        $stats['assistants_created']
                    ),
                    'stats' => $stats
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get model options for select dropdown
     * 
     * @return array Grouped model options
     */
    private function get_model_options()
    {
        // Check if OpenAI settings provider class exists
        if (!class_exists('PolyTrans_OpenAI_Settings_Provider')) {
            return $this->get_fallback_models();
        }

        try {
            $provider = new PolyTrans_OpenAI_Settings_Provider();
            $reflection = new ReflectionClass($provider);
            $method = $reflection->getMethod('get_grouped_models');
            $method->setAccessible(true);
            return $method->invoke($provider);
        } catch (Exception $e) {
            return $this->get_fallback_models();
        }
    }

    /**
     * Get fallback model options
     * 
     * @return array Fallback model options
     */
    private function get_fallback_models()
    {
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

