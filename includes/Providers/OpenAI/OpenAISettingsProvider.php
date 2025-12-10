<?php

namespace PolyTrans\Providers\OpenAI;

use PolyTrans\Assistants\AssistantManager;

/**
 * OpenAI Settings Provider
 * Handles settings UI and management for the OpenAI translation provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class OpenAISettingsProvider implements \PolyTrans_Settings_Provider_Interface
{
    /**
     * Get the settings provider identifier
     */
    public function get_provider_id()
    {
        return 'openai';
    }

    /**
     * Get the tab label for this provider's settings
     */
    public function get_tab_label()
    {
        return __('OpenAI Configuration', 'polytrans');
    }

    /**
     * Get the tab description
     */
    public function get_tab_description()
    {
        return __('Configure your OpenAI API key and translation assistants for AI-powered translations.', 'polytrans');
    }

    /**
     * Get required JavaScript files
     */
    public function get_required_js_files()
    {
        return [
            'assets/js/translator/openai-integration.js'
        ];
    }

    /**
     * Get required CSS files
     */
    public function get_required_css_files()
    {
        return [
            'assets/css/translator/openai-integration.css'
        ];
    }

    /**
     * Get provider-specific settings keys
     */
    public function get_settings_keys()
    {
        return [
            'openai_api_key',
            'openai_source_language',
            'openai_assistants',
            'openai_model'
        ];
    }

    /**
     * Render the provider settings UI
     */
    public function render_settings_ui(array $settings, array $languages, array $language_names)
    {
        $openai_api_key = $settings['openai_api_key'] ?? '';
        $openai_source_language = $settings['openai_source_language'] ?? ($languages[0] ?? 'en');
        $openai_assistants = $settings['openai_assistants'] ?? [];
        $openai_path_rules = $settings['openai_path_rules'] ?? [
            [
                'source' => 'all',
                'target' => 'all',
                'intermediate' => 'en',
            ]
        ];
        $openai_model = $settings['openai_model'] ?? 'gpt-4o-mini';
?>
        <script>
            window.POLYTRANS_LANGS = <?php echo json_encode(array_values($languages)); ?>;
            window.POLYTRANS_LANG_NAMES = <?php
                                            $lang_name_map = [];
                                            foreach ($languages as $i => $lang) {
                                                $lang_name_map[$lang] = $language_names[$i] ?? strtoupper($lang);
                                            }
                                            echo json_encode($lang_name_map);
                                            ?>;
        </script>
        <div class="openai-config-section">
            <h2><?php echo esc_html($this->get_tab_label()); ?></h2>
            <p><?php echo esc_html($this->get_tab_description()); ?></p>

            <!-- API Key Section -->
            <div class="openai-api-key-section">
                <h3><?php esc_html_e('API Key', 'polytrans'); ?></h3>
                <div style="display:flex;gap:0.5em;align-items:center;max-width:600px;">
                    <input type="password"
                        id="openai-api-key"
                        name="openai_api_key"
                        value="<?php echo esc_attr($openai_api_key); ?>"
                        style="width:100%"
                        placeholder="sk-..."
                        autocomplete="off" />
                    <button type="button" id="validate-openai-key" class="button"><?php esc_html_e('Validate', 'polytrans'); ?></button>
                    <button type="button" id="toggle-openai-key-visibility" class="button">👁</button>
                </div>
                <div id="openai-validation-message" style="margin-top:0.5em;"></div>
                <small><?php esc_html_e('Enter your OpenAI API key. It will be validated before saving.', 'polytrans'); ?></small>
            </div>

            <!-- Model Selection Section -->
            <div class="openai-model-section" style="margin-top:2em;">
                <h3><?php esc_html_e('Default Model', 'polytrans'); ?></h3>
                <?php $this->render_model_selection($openai_model); ?>
                <br><small><?php esc_html_e('Default OpenAI model to use for translations and AI Assistant steps. This can be overridden per workflow step.', 'polytrans'); ?></small>
            </div>

            <div style="margin-top:2em; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                <p style="margin: 0;">
                    <strong><?php esc_html_e('Note:', 'polytrans'); ?></strong>
                    <?php esc_html_e('Assistant Mapping and Translation Path Rules have been moved to the', 'polytrans'); ?>
                    <a href="<?php echo admin_url('admin.php?page=polytrans-settings#language-pairs-settings'); ?>">
                        <?php esc_html_e('Language Pairs', 'polytrans'); ?>
                    </a>
                    <?php esc_html_e('tab for better organization.', 'polytrans'); ?>
                </p>
            </div>
        </div>

        <style>
            .language-pair-group h4 {
                margin-top: 0;
                margin-bottom: 0.5em;
                color: #333;
            }

            .openai-assistant-input {
                font-family: monospace;
                font-size: 12px;
            }
        </style>
    <?php
    }

    /**
     * Validate and sanitize provider-specific settings
     */
    public function validate_settings(array $posted_data)
    {
        $validated = [];

        // Validate API key
        if (isset($posted_data['openai_api_key'])) {
            $validated['openai_api_key'] = sanitize_text_field($posted_data['openai_api_key']);
        }

        // Validate source language
        if (isset($posted_data['openai_source_language'])) {
            $validated['openai_source_language'] = sanitize_text_field($posted_data['openai_source_language']);
        }

        // Validate assistants
        if (isset($posted_data['openai_assistants']) && is_array($posted_data['openai_assistants'])) {
            $validated['openai_assistants'] = [];
            foreach ($posted_data['openai_assistants'] as $pair => $assistant_id) {
                $pair = sanitize_text_field($pair);
                $assistant_id = sanitize_text_field($assistant_id);

                // Only save non-empty assistant IDs
                if (!empty($assistant_id)) {
                    $validated['openai_assistants'][$pair] = $assistant_id;
                }
            }
        }

        // Validate translation path rules
        if (isset($posted_data['openai_path_rules']) && is_array($posted_data['openai_path_rules'])) {
            $validated['openai_path_rules'] = [];
            foreach ($posted_data['openai_path_rules'] as $rule) {
                if (!is_array($rule)) continue;
                $source = isset($rule['source']) ? sanitize_text_field($rule['source']) : 'all';
                $target = isset($rule['target']) ? sanitize_text_field($rule['target']) : 'all';
                $intermediate = isset($rule['intermediate']) ? sanitize_text_field($rule['intermediate']) : 'none';
                // Only save rules with at least source and target
                if ($source !== '' && $target !== '') {
                    $validated['openai_path_rules'][] = [
                        'source' => $source,
                        'target' => $target,
                        'intermediate' => $intermediate,
                    ];
                }
            }
        }

        // Validate model selection
        if (isset($posted_data['openai_model'])) {
            $allowed_models = $this->get_all_available_models();
            $model = sanitize_text_field($posted_data['openai_model']);
            if (in_array($model, $allowed_models)) {
                $validated['openai_model'] = $model;
            } else {
                $validated['openai_model'] = 'gpt-4o-mini';
            }
        }

        return $validated;
    }

    /**
     * Get default settings
     */
    public function get_default_settings()
    {
        return [
            'openai_api_key' => '',
            'openai_source_language' => 'en',
            'openai_assistants' => [],
            'openai_model' => 'gpt-4o-mini' // Default model
        ];
    }

    /**
     * Check if the provider settings are properly configured
     */
    public function is_configured(array $settings)
    {
        $api_key = $settings['openai_api_key'] ?? '';
        $assistants = $settings['openai_assistants'] ?? [];

        // Check if we have an API key and at least one assistant configured
        if (empty($api_key)) {
            return false;
        }

        // Check if we have at least one non-empty assistant (language pair)
        $has_assistant = false;
        foreach ($assistants as $language_pair => $assistant_id) {
            if (!empty($assistant_id)) {
                $has_assistant = true;
                break;
            }
        }

        return $has_assistant;
    }

    /**
     * Get configuration status message
     */
    public function get_configuration_status(array $settings)
    {
        $api_key = $settings['openai_api_key'] ?? '';
        $assistants = $settings['openai_assistants'] ?? [];

        if (empty($api_key)) {
            return __('OpenAI API key is required.', 'polytrans');
        }

        $assistant_count = count(array_filter($assistants, function ($id) {
            return !empty($id);
        }));
        if ($assistant_count === 0) {
            return __('At least one translation assistant must be configured.', 'polytrans');
        }

        return ''; // Properly configured
    }

    /**
     * Enqueue additional scripts and styles
     */
    public function enqueue_assets()
    {

        // OpenAI AJAX handlers - register early
        // Enqueue JavaScript
        foreach ($this->get_required_js_files() as $js_file) {
            $handle = 'polytrans-openai-' . basename($js_file, '.js');
            if (file_exists(POLYTRANS_PLUGIN_DIR . $js_file)) {
                wp_enqueue_script($handle, POLYTRANS_PLUGIN_URL . $js_file, ['jquery'], POLYTRANS_VERSION, true);
            }
        }

        // Enqueue CSS
        foreach ($this->get_required_css_files() as $css_file) {
            $handle = 'polytrans-openai-' . basename($css_file, '.css');
            if (file_exists(POLYTRANS_PLUGIN_DIR . $css_file)) {
                wp_enqueue_style($handle, POLYTRANS_PLUGIN_URL . $css_file, [], POLYTRANS_VERSION);
            }
        }

        // Add AJAX URL and nonce for JavaScript
        $js_handle = 'polytrans-openai-' . basename($this->get_required_js_files()[0], '.js');
        wp_localize_script($js_handle, 'polytrans_openai', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polytrans_openai_nonce'),
            'models' => $this->get_grouped_models(),
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

    /**
     * Get AJAX handlers that this provider needs to register
     */
    public function get_ajax_handlers()
    {
        return [
            'polytrans_validate_openai_key' => [
                'callback' => [$this, 'ajax_validate_openai_key'],
                'is_static' => false
            ],
            'polytrans_load_openai_assistants' => [
                'callback' => [$this, 'ajax_load_openai_assistants'],
                'is_static' => false
            ]
        ];
    }

    /**
     * Register provider-specific AJAX handlers
     */
    public function register_ajax_handlers()
    {
        $handlers = $this->get_ajax_handlers();

        foreach ($handlers as $action => $handler_info) {
            $callback = $handler_info['callback'];
            $is_static = $handler_info['is_static'] ?? false;

            // Register for logged-in users
            add_action("wp_ajax_{$action}", $callback);

            // If this should be available for non-logged-in users, uncomment the line below
            // add_action("wp_ajax_nopriv_{$action}", $callback);
        }
    }

    /**
     * Render the assistant mapping table
     */
    private function render_assistant_mapping_table($languages, $language_names, $openai_assistants, $openai_source_language)
    {
        // For now, we'll generate language pairs directly since we don't have the integration class
        // This will need to be updated to use the actual OpenAI integration if available
        $language_pairs = $this->get_language_pairs($languages);

        if (empty($language_pairs)) {
            echo '<p><em>' . esc_html__('No language pairs available. Please configure languages first.', 'polytrans') . '</em></p>';
            return;
        }

    ?>
        <table class="widefat fixed striped" id="openai-assistants-table">
            <thead>
                <tr>
                    <th style="width:30%;"><?php esc_html_e('Translation Pair', 'polytrans'); ?></th>
                    <th style="width:30%;"><?php esc_html_e('Translation Path', 'polytrans'); ?></th>
                    <th style="width:40%;"><?php esc_html_e('Assistant', 'polytrans'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($language_pairs as $pair): ?>
                    <?php
                    $source_name = $this->get_language_name($pair['source'], $languages, $language_names);
                    $target_name = $this->get_language_name($pair['target'], $languages, $language_names);
                    $assistant_key = $pair['key'];
                    $selected_assistant = $openai_assistants[$assistant_key] ?? '';
                    ?>
                    <tr data-pair="<?php echo esc_attr($pair['key']); ?>"
                        data-source="<?php echo esc_attr($pair['source']); ?>"
                        data-target="<?php echo esc_attr($pair['target']); ?>"
                        class="language-pair-row" style="display:none;">
                        <td>
                            <strong><?php echo esc_html($source_name); ?> → <?php echo esc_html($target_name); ?></strong>
                        </td>
                        <td>
                            <span class="translation-path-direct"><?php esc_html_e('Direct', 'polytrans'); ?></span>
                            <br><small class="translation-path-details">
                                <?php echo esc_html($source_name . ' → ' . $target_name); ?>
                            </small>
                        </td>
                        <td>
                            <!-- Hidden input to preserve the actual saved value -->
                            <input type="hidden"
                                name="openai_assistants[<?php echo esc_attr($assistant_key); ?>]"
                                id="assistant_hidden_<?php echo esc_attr($assistant_key); ?>"
                                value="<?php echo esc_attr($selected_assistant); ?>"
                                class="assistant-hidden-input" />

                            <!-- Visible select for user interaction -->
                            <select class="assistant-select"
                                id="assistant_select_<?php echo esc_attr($assistant_key); ?>"
                                style="width:100%;max-width:350px;"
                                data-pair="<?php echo esc_attr($assistant_key); ?>"
                                data-selected="<?php echo esc_attr($selected_assistant); ?>"
                                data-hidden-input="assistant_hidden_<?php echo esc_attr($assistant_key); ?>">
                                <option value=""><?php esc_html_e('Not selected', 'polytrans'); ?></option>
                                <!-- Options will be populated via JavaScript when assistants are loaded -->
                            </select>

                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr class="no-relevant-pairs-message" style="display:none;">
                    <td colspan="3" style="text-align:center; color:#888; font-style:italic; padding:20px;">
                        <?php esc_html_e('No relevant language pairs available. Please check your source/target language selections and OpenAI source language configuration.', 'polytrans'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
<?php
    }

    /**
     * Generate language pairs for assistant mapping
     */
    private function get_language_pairs($languages)
    {
        $language_pairs = [];
        foreach ($languages as $source_lang) {
            foreach ($languages as $target_lang) {
                if ($source_lang !== $target_lang) {
                    $language_pairs[] = [
                        'source' => $source_lang,
                        'target' => $target_lang,
                        'key' => $source_lang . '_to_' . $target_lang
                    ];
                }
            }
        }
        return $language_pairs;
    }

    /**
     * Get language display name
     */
    private function get_language_name($lang_code, $languages, $language_names)
    {
        $index = array_search($lang_code, $languages);
        return $index !== false ? ($language_names[$index] ?? strtoupper($lang_code)) : strtoupper($lang_code);
    }

    /**
     * Get grouped models for UI display - SINGLE SOURCE OF TRUTH
     */
    private function get_grouped_models()
    {
        return [
            'GPT-4o Models (Latest)' => [
                'gpt-4o' => 'GPT-4o (Latest)',
                'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cost-effective)',
                'gpt-4o-2024-11-20' => 'GPT-4o (2024-11-20)',
                'gpt-4o-2024-08-06' => 'GPT-4o (2024-08-06)',
                'gpt-4o-2024-05-13' => 'GPT-4o (2024-05-13)',
                'gpt-4o-mini-2024-07-18' => 'GPT-4o Mini (2024-07-18)',
            ],
            'GPT-4 Turbo Models' => [
                'gpt-4-turbo' => 'GPT-4 Turbo (Latest)',
                'gpt-4-turbo-2024-04-09' => 'GPT-4 Turbo (2024-04-09)',
                'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
                'gpt-4-0125-preview' => 'GPT-4 Turbo (0125-preview)',
                'gpt-4-1106-preview' => 'GPT-4 Turbo (1106-preview)',
            ],
            'GPT-4 Models' => [
                'gpt-4' => 'GPT-4 (Latest)',
                'gpt-4-0613' => 'GPT-4 (0613)',
                'gpt-4-0314' => 'GPT-4 (0314)',
            ],
            'GPT-3.5 Turbo Models' => [
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Latest)',
                'gpt-3.5-turbo-0125' => 'GPT-3.5 Turbo (0125)',
                'gpt-3.5-turbo-1106' => 'GPT-3.5 Turbo (1106)',
                'gpt-3.5-turbo-0613' => 'GPT-3.5 Turbo (0613)',
                'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K (Latest)',
                'gpt-3.5-turbo-16k-0613' => 'GPT-3.5 Turbo 16K (0613)',
            ],
        ];
    }

    /**
     * Get all available OpenAI models (flattened from grouped models)
     */
    private function get_all_available_models()
    {
        $models = [];
        foreach ($this->get_grouped_models() as $group => $group_models) {
            $models = array_merge($models, array_keys($group_models));
        }
        return $models;
    }

    /**
     * Render model selection dropdown
     */
    private function render_model_selection($selected_model)
    {
        $grouped_models = $this->get_grouped_models();

        echo '<select name="openai_model" id="openai-model" style="max-width:300px;">';

        foreach ($grouped_models as $group_name => $models) {
            echo '<optgroup label="' . esc_attr($group_name) . '">';
            foreach ($models as $model_value => $model_label) {
                $selected = selected($selected_model, $model_value, false);
                echo '<option value="' . esc_attr($model_value) . '" ' . $selected . '>' . esc_html($model_label) . '</option>';
            }
            echo '</optgroup>';
        }

        echo '</select>';
    }

    /**
     * Validate OpenAI API key using the OpenAI API
     */
    private function validate_openai_api_key($api_key)
    {
        $client = new OpenAIClient($api_key);
        $models = $client->get_models();

        return !empty($models);
    }

    /**
     * AJAX handler for validating OpenAI API key
     */
    public function ajax_validate_openai_key()
    {
        // Check nonce
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
        $is_valid_key = $this->validate_openai_api_key($api_key);

        if ($is_valid_key) {
            wp_send_json_success(__('API key is valid!', 'polytrans'));
        } else {
            wp_send_json_error(__('Invalid API key.', 'polytrans'));
        }
    }

    /**
     * AJAX handler for loading OpenAI assistants (both Managed and OpenAI API)
     */
    public function ajax_load_openai_assistants()
    {
        // Check nonce
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

        // Prepare grouped assistants structure
        $grouped_assistants = [
            'managed' => [],
            'openai' => [],
            'claude' => [], // Future: Claude Projects
            'gemini' => []  // Future: Gemini Tuned Models
        ];

        // 1. Load Managed Assistants from local database
        $managed_assistants = AssistantManager::get_all_assistants();
        if (!empty($managed_assistants)) {
            foreach ($managed_assistants as $assistant) {
                $model_display = 'No model';
                
                // Try to get model from api_parameters
                if (!empty($assistant['api_parameters'])) {
                    $api_params = is_string($assistant['api_parameters']) 
                        ? json_decode($assistant['api_parameters'], true) 
                        : $assistant['api_parameters'];

                    if (is_array($api_params) && !empty($api_params['model'])) {
                        $model_display = $api_params['model'];
                    }
                }
                
                // Fallback: use global setting indicator
                if ($model_display === 'No model' || empty($model_display)) {
                    $model_display = 'Global Setting';
                }

                $grouped_assistants['managed'][] = [
                    'id' => 'managed_' . $assistant['id'], // Prefix to distinguish from OpenAI IDs
                    'name' => $assistant['name'],
                    'description' => $assistant['description'] ?? '',
                    'model' => $model_display,
                    'provider' => $assistant['provider'] ?? 'openai'
                ];
            }
        }

        // 2. Load OpenAI API Assistants (if API key provided)
        if (!empty($api_key)) {
            $client = new OpenAIClient($api_key);
            $openai_assistants = $client->get_all_assistants();

            if (!empty($openai_assistants)) {
                foreach ($openai_assistants as $assistant) {
                    $grouped_assistants['openai'][] = [
                        'id' => $assistant['id'], // Keep original asst_xxx format
                'name' => $assistant['name'] ?? 'Unnamed Assistant',
                'description' => $assistant['description'] ?? '',
                'model' => $assistant['model'] ?? 'gpt-4'
            ];
                }
            }
        }

        // Return grouped structure
        wp_send_json_success($grouped_assistants);
    }
}
