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
        add_action('wp_ajax_polytrans_migrate_workflows', [$this, 'ajax_migrate_workflows']);
        add_action('wp_ajax_polytrans_get_provider_models', [$this, 'ajax_get_provider_models']);
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

        // Get current model and provider from assistant being edited (if any)
        $current_model = '';
        $current_provider = 'openai';
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $assistant_id = intval($_GET['id']);
            $assistant = AssistantManager::get_assistant($assistant_id);
            if ($assistant) {
                if (!empty($assistant['model'])) {
                    $current_model = $assistant['model'];
                }
                if (!empty($assistant['provider'])) {
                    $current_provider = $assistant['provider'];
                }
            }
        }
        
        // Localize script
        wp_localize_script('polytrans-assistants', 'polytransAssistants', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polytrans_assistants'),
            'models' => $this->get_model_options($current_provider, $current_model),
            'selected_model' => $current_model,
            'current_provider' => $current_provider,
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this assistant?', 'polytrans'),
                'saveSuccess' => __('Assistant saved successfully.', 'polytrans'),
                'saveError' => __('Failed to save assistant.', 'polytrans'),
                'deleteSuccess' => __('Assistant deleted successfully.', 'polytrans'),
                'deleteError' => __('Failed to delete assistant.', 'polytrans'),
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
        
        // Get enabled providers to check if provider is disabled
        $settings = get_option('polytrans_settings', []);
        $enabled_providers = $settings['enabled_translation_providers'] ?? ['google'];
        
        // Map assistant data for display
        foreach ($assistants as &$assistant) {
            // Extract model from api_parameters
            $assistant['model'] = $assistant['api_parameters']['model'] ?? '';
            // Map expected_format to response_format
            $assistant['response_format'] = $assistant['expected_format'] ?? 'text';
            
            // Check if model is empty and if there's a default model in settings
            $assistant['has_default_model'] = false;
            if (empty($assistant['model'])) {
                $provider_id = $assistant['provider'];
                $model_setting_key = $provider_id . '_model';
                $default_model = $settings[$model_setting_key] ?? '';
                $assistant['has_default_model'] = !empty($default_model);
            }
        }
        unset($assistant); // Break reference

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
                                    <?php 
                                    $provider_name = ucfirst($assistant['provider']);
                                    $is_provider_enabled = in_array($assistant['provider'], $enabled_providers);
                                    if (!$is_provider_enabled) {
                                        echo '<span style="color: #dc3232; font-weight: bold;">' . esc_html($provider_name) . '</span>';
                                    } else {
                                        echo esc_html($provider_name);
                                    }
                                    ?>
                                </td>
                                <td class="column-model">
                                    <?php 
                                    if (!empty($assistant['model'])) {
                                        echo esc_html($assistant['model']);
                                    } else {
                                        // Show "default" or red warning if no default model
                                        if ($assistant['has_default_model']) {
                                            echo '<span style="color: #666; font-style: italic;">' . esc_html__('default', 'polytrans') . '</span>';
                                        } else {
                                            echo '<span style="color: #dc3232; font-weight: bold;">' . esc_html__('default', 'polytrans') . '</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="column-format">
                                    <?php 
                                    if (!empty($assistant['response_format'])) {
                                        echo esc_html(ucfirst($assistant['response_format']));
                                    } else {
                                        echo '<span style="color: #666; font-style: italic;">' . esc_html__('default', 'polytrans') . '</span>';
                                    }
                                    ?>
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
                    'temperature' => 0.7
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

            // Map api_parameters to config for UI consistency
            if (isset($assistant['api_parameters']) && is_array($assistant['api_parameters'])) {
                $assistant['config'] = [
                    'temperature' => $assistant['api_parameters']['temperature'] ?? 0.7
                ];
                // Also map model for easier access
                if (isset($assistant['api_parameters']['model'])) {
                    $assistant['model'] = $assistant['api_parameters']['model'];
                }
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
                                <?php
                                // Get available providers that support assistants
                                $registry = \PolyTrans_Provider_Registry::get_instance();
                                $settings = get_option('polytrans_settings', []);
                                $enabled_providers = $settings['enabled_translation_providers'] ?? ['google'];
                                $all_providers = $registry->get_providers();
                                
                                $available_assistant_providers = [];
                                $provider_manifests = []; // Store manifests for JS
                                foreach ($all_providers as $provider_id => $provider) {
                                    // Check if provider is enabled
                                    if (!in_array($provider_id, $enabled_providers)) {
                                        continue;
                                    }
                                    
                                    // Check if provider supports chat or assistants via manifest
                                    // Managed assistants can use providers with 'chat' capability (via system prompt)
                                    // or providers with 'assistants' capability (via dedicated API)
                                    $settings_provider_class = $provider->get_settings_provider_class();
                                    if ($settings_provider_class && class_exists($settings_provider_class)) {
                                        $settings_provider = new $settings_provider_class();
                                        if (method_exists($settings_provider, 'get_provider_manifest')) {
                                            $manifest = $settings_provider->get_provider_manifest($settings);
                                            $capabilities = $manifest['capabilities'] ?? [];
                                            // Managed assistants can use 'chat' or 'assistants' capability
                                            if (in_array('chat', $capabilities) || in_array('assistants', $capabilities)) {
                                                $available_assistant_providers[$provider_id] = $provider;
                                                // Store manifest for JavaScript (system_prompt capability info)
                                                $capabilities = $manifest['capabilities'] ?? [];
                                                $provider_manifests[$provider_id] = [
                                                    'capabilities' => $capabilities, // Store full capabilities array
                                                    'supports_system_prompt' => in_array('system_prompt', $capabilities), // Check system_prompt capability (for backward compatibility)
                                                ];
                                            }
                                        }
                                    }
                                }
                                
                                // If no providers found, fallback to hardcoded list (for backward compatibility)
                                if (empty($available_assistant_providers)) {
                                    $available_assistant_providers = [
                                        'openai' => $registry->get_provider('openai'),
                                        'claude' => $registry->get_provider('claude'),
                                        'gemini' => null, // Placeholder
                                    ];
                                }
                                ?>
                                <select id="assistant-provider" name="provider" required>
                                    <?php foreach ($available_assistant_providers as $provider_id => $provider): ?>
                                        <?php if ($provider): ?>
                                            <option value="<?php echo esc_attr($provider_id); ?>" <?php selected($assistant['provider'] ?? 'openai', $provider_id); ?>>
                                                <?php echo esc_html($provider->get_name()); ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="<?php echo esc_attr($provider_id); ?>" disabled <?php selected($assistant['provider'] ?? 'openai', $provider_id); ?>>
                                                <?php echo esc_html(ucfirst($provider_id)); ?> <?php esc_html_e('(Not Available)', 'polytrans'); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('AI provider to use for this assistant. Only enabled providers with assistant support are shown.', 'polytrans'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="assistant-model"><?php esc_html_e('AI Model', 'polytrans'); ?></label>
                            </th>
                            <td>
                                <select id="assistant-model" name="model" class="regular-text" data-selected-model="<?php echo esc_attr($assistant['model'] ?? ''); ?>" data-provider="<?php echo esc_attr($assistant['provider'] ?? 'openai'); ?>">
                                    <?php
                                    $current_model = $assistant['model'] ?? '';
                                    $current_provider = $assistant['provider'] ?? 'openai';
                                    $models = $this->get_model_options($current_provider, $current_model);

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
                                <button type="button" id="refresh-models" class="button" style="margin-left: 0.5em;" title="<?php esc_attr_e('Refresh models from provider API', 'polytrans'); ?>">
                                    <?php esc_html_e('Refresh', 'polytrans'); ?>
                                </button>
                                <p class="description"><?php esc_html_e('Select the AI model to use for this assistant. "Use Global Setting" will use the default model from plugin settings.', 'polytrans'); ?></p>
                            </td>
                        </tr>

                        <tr id="system-prompt-row" class="system-prompt-field-row">
                            <th scope="row">
                                <label for="assistant-system-prompt"><?php esc_html_e('System Instructions', 'polytrans'); ?> <span class="required system-prompt-required">*</span></label>
                            </th>
                            <td class="workflow-field-with-variables">
                                <div id="system-prompt-editor-container"></div>
                                <p class="description"><?php esc_html_e('Instructions that define how the assistant should behave. This is static and doesn\'t change between requests.', 'polytrans'); ?></p>
                                <p class="description"><strong><?php esc_html_e('Example:', 'polytrans'); ?></strong> "You are a content quality expert. Analyze posts for grammar, SEO, and readability. Always respond in JSON format."</p>
                                <p class="description system-prompt-not-supported" style="display:none; color: #d63638;">
                                    <strong><?php esc_html_e('Note:', 'polytrans'); ?></strong> 
                                    <?php esc_html_e('This provider does not support system prompts. Only the User Message Template will be used.', 'polytrans'); ?>
                                </p>
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

                    </tbody>
                </table>

                <div class="assistant-editor-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Assistant', 'polytrans'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-assistants')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'polytrans'); ?>
                    </a>
                </div>
            </form>

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

        // Check if provider supports system prompt
        $supports_system_prompt = true; // Default to true for backward compatibility
        $settings = get_option('polytrans_settings', []);
        $registry = \PolyTrans_Provider_Registry::get_instance();
        $provider_obj = $registry->get_provider($provider);
        if ($provider_obj) {
            $settings_provider_class = $provider_obj->get_settings_provider_class();
            if ($settings_provider_class && class_exists($settings_provider_class)) {
                $settings_provider = new $settings_provider_class();
                    if (method_exists($settings_provider, 'get_provider_manifest')) {
                        $manifest = $settings_provider->get_provider_manifest($settings);
                        $capabilities = $manifest['capabilities'] ?? [];
                        // Check for system_prompt capability, fallback to supports_system_prompt for backward compatibility
                        $supports_system_prompt = in_array('system_prompt', $capabilities) || ($manifest['supports_system_prompt'] ?? true);
                    }
            }
        }
        
        // Validate required fields
        // System prompt is only required if provider supports it
        if (empty($name) || empty($provider)) {
            wp_send_json_error(['message' => __('Required fields are missing.', 'polytrans')]);
        }
        
        // If provider doesn't support system prompt, ensure it's empty
        if (!$supports_system_prompt) {
            $system_prompt = ''; // Clear system prompt if provider doesn't support it
        } elseif (empty($system_prompt)) {
            // Only require system prompt if provider supports it
            wp_send_json_error(['message' => __('System Instructions are required for this provider.', 'polytrans')]);
        }

        // Prepare API parameters
        $api_parameters = [
            'model' => $model,
            'temperature' => $config['temperature'] ?? 0.7
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get model options for select dropdown based on provider
     * 
     * @param string|null $provider_id Provider ID (e.g., 'openai', 'claude')
     * @param string|null $selected_model Currently selected model (for backward compatibility)
     * @return array Grouped model options
     */
    private function get_model_options($provider_id = null, $selected_model = null, $force_refresh = false)
    {
        // Default to OpenAI for backward compatibility
        if (empty($provider_id)) {
            $provider_id = 'openai';
        }
        
        $registry = \PolyTrans_Provider_Registry::get_instance();
        $provider = $registry->get_provider($provider_id);
        
        if (!$provider) {
            return $this->get_fallback_models();
        }
        
        $settings_provider_class = $provider->get_settings_provider_class();
        if (!$settings_provider_class || !class_exists($settings_provider_class)) {
            return $this->get_fallback_models();
        }
        
        try {
            $settings_provider = new $settings_provider_class();
            $settings = get_option('polytrans_settings', []);
            
            // Check if provider implements SettingsProviderInterface and has load_models method
            if ($settings_provider instanceof \PolyTrans\Providers\SettingsProviderInterface) {
                if (method_exists($settings_provider, 'load_models')) {
                    // Pass force_refresh parameter if method signature supports it
                    // For now, we'll check if cache should be cleared before calling
                    if ($force_refresh) {
                        // Clear cache for this provider before loading
                        $api_key_setting = $this->get_api_key_setting_key($provider_id);
                        $api_key = $settings[$api_key_setting] ?? '';
                        if (!empty($api_key)) {
                            $cache_key = 'polytrans_' . $provider_id . '_models_' . md5($api_key);
                            delete_transient($cache_key);
                        }
                    }
                    $models = $settings_provider->load_models($settings);
                    if (!empty($models) && is_array($models)) {
                        return $models;
                    }
                }
            }
            
            // Legacy: Check if provider has get_grouped_models method (for backward compatibility)
            if (method_exists($settings_provider, 'get_grouped_models')) {
                return $settings_provider->get_grouped_models($selected_model);
            }
        } catch (\Exception $e) {
            error_log("[PolyTrans] Failed to get models for provider $provider_id: " . $e->getMessage());
        }
        
        // Provider-specific fallback based on provider_id
        return $this->get_fallback_models($provider_id);
    }

    /**
     * Get API key setting key for provider
     * 
     * @param string $provider_id Provider ID
     * @return string API key setting key
     */
    private function get_api_key_setting_key($provider_id)
    {
        return $provider_id . '_api_key';
    }
    
    /**
     * Get fallback model options
     * 
     * @param string|null $provider_id Provider ID to get provider-specific fallback
     * @return array Fallback model options (empty - no hardcoded models)
     */
    private function get_fallback_models($provider_id = null)
    {
        // No fallback models - return empty array
        // Models must be loaded from API
        return [];
    }
    
    /**
     * AJAX handler for getting provider models
     */
    public function ajax_get_provider_models()
    {
        // Check nonce - accept multiple nonce types for compatibility
        // Universal JS uses polytrans_nonce, AssistantsMenu uses polytrans_assistants
        $nonce_check = false;
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field($_POST['nonce']);
            // Try different nonce types:
            // 1. polytrans_assistants (from AssistantsMenu)
            // 2. polytrans_nonce (from SettingsMenu - Universal JS)
            // 3. polytrans_openai_nonce (backward compatibility)
            $nonce_check = wp_verify_nonce($nonce, 'polytrans_assistants') ||
                          wp_verify_nonce($nonce, 'polytrans_nonce') ||
                          wp_verify_nonce($nonce, 'polytrans_openai_nonce');
        }
        
        if (!$nonce_check) {
            wp_send_json_error(__('Security check failed.', 'polytrans'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', 'polytrans'));
            return;
        }
        
        $provider_id = sanitize_text_field($_POST['provider_id'] ?? 'openai');
        $selected_model = sanitize_text_field($_POST['selected_model'] ?? '');
        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === '1';
        
        // Get models for the specified provider
        $models = $this->get_model_options($provider_id, $selected_model, $force_refresh);
        
        wp_send_json_success([
            'models' => $models,
            'selected_model' => $selected_model
        ]);
    }
}
