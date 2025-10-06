<?php

/**
 * OpenAI Settings Provider
 * Handles settings UI and management for the OpenAI translation provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_OpenAI_Settings_Provider implements PolyTrans_Settings_Provider_Interface
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


            <!-- Translation Path Rules Section -->
            <div class="openai-path-rules-section" style="margin-top:2em;">
                <h3><?php esc_html_e('Translation Path Rules', 'polytrans'); ?></h3>
                <p><?php esc_html_e('Define the order and specificity of translation paths. Each rule specifies a source language, target language, and intermediate (middle) language. "all" means any language. "none" means direct translation.', 'polytrans'); ?></p>
                <div id="openai-path-rules-list">
                    <?php foreach ($openai_path_rules as $i => $rule): ?>
                        <div class="openai-path-rule" data-index="<?php echo esc_attr($i); ?>">
                            <span class="drag-handle" title="Drag to reorder">☰</span>
                            <select name="openai_path_rules[<?php echo esc_attr($i); ?>][source]" class="openai-path-source">
                                <option value="all" <?php selected($rule['source'], 'all'); ?>><?php esc_html_e('All', 'polytrans'); ?></option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo esc_attr($lang); ?>" <?php selected($rule['source'], $lang); ?>><?php echo esc_html($language_names[array_search($lang, $languages)] ?? strtoupper($lang)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            →
                            <select name="openai_path_rules[<?php echo esc_attr($i); ?>][target]" class="openai-path-target">
                                <option value="all" <?php selected($rule['target'], 'all'); ?>><?php esc_html_e('All', 'polytrans'); ?></option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo esc_attr($lang); ?>" <?php selected($rule['target'], $lang); ?>><?php echo esc_html($language_names[array_search($lang, $languages)] ?? strtoupper($lang)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php esc_html_e('via', 'polytrans'); ?>
                            <select name="openai_path_rules[<?php echo esc_attr($i); ?>][intermediate]" class="openai-path-intermediate">
                                <option value="none" <?php selected($rule['intermediate'], 'none'); ?>><?php esc_html_e('None (Direct)', 'polytrans'); ?></option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo esc_attr($lang); ?>" <?php selected($rule['intermediate'], $lang); ?>><?php echo esc_html($language_names[array_search($lang, $languages)] ?? strtoupper($lang)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button openai-path-remove" title="Remove">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="openai-path-add-rule">+ <?php esc_html_e('Add Rule', 'polytrans'); ?></button>
                <small><?php esc_html_e('Order matters: more specific rules should be placed lower. Drag to reorder. The last matching rule for a source-target pair is used.', 'polytrans'); ?></small>
            </div>

            <!-- Model Selection Section -->
            <div class="openai-model-section" style="margin-top:2em;">
                <h3><?php esc_html_e('Default Model', 'polytrans'); ?></h3>
                <?php $this->render_model_selection($openai_model); ?>
                <br><small><?php esc_html_e('Default OpenAI model to use for translations and AI Assistant steps. This can be overridden per workflow step.', 'polytrans'); ?></small>
            </div>

            <!-- Assistant Mapping Section -->
            <div class="openai-assistants-section" id="openai-assistants-section" style="margin-top:2em; display:none;">
                <h3><?php esc_html_e('Assistant Mapping', 'polytrans'); ?></h3>
                <p><?php esc_html_e('Select which OpenAI assistant to use for each relevant translation pair. Only pairs involving the OpenAI source language and within allowed source/target languages are shown:', 'polytrans'); ?></p>
                <ul style="margin-bottom:1em; padding-left:2em;">
                    <li><strong><?php esc_html_e('Source → OpenAI', 'polytrans'); ?></strong>: <?php esc_html_e('For translating content from allowed source languages to the OpenAI source language', 'polytrans'); ?></li>
                    <li><strong><?php esc_html_e('OpenAI → Target', 'polytrans'); ?></strong>: <?php esc_html_e('For translating content from the OpenAI source language to allowed target languages', 'polytrans'); ?></li>
                </ul>
                <div id="assistants-loading" style="display:none;">
                    <p><em><?php esc_html_e('Loading assistants...', 'polytrans'); ?></em></p>
                </div>
                <div id="assistants-error" style="display:none; color:#d63638;">
                    <p><?php esc_html_e('Unable to load assistants. Please check your API key and try again.', 'polytrans'); ?></p>
                </div>
                <div id="assistants-mapping-container">
                    <?php $this->render_assistant_mapping_table($languages, $language_names, $openai_assistants, $openai_source_language); ?>
                </div>
                <small><?php esc_html_e('Assistants will be loaded automatically when a valid API key is provided. Language pairs are filtered based on your source/target language selections and update automatically when you change settings.', 'polytrans'); ?></small>
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
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => 'PolyTrans/1.0'
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        return $response_code === 200;
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
     * AJAX handler for loading OpenAI assistants
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

        if (empty($api_key)) {
            wp_send_json_error(__('API key is required.', 'polytrans'));
        }

        // Load all assistants from OpenAI API with pagination
        $all_assistants = [];
        $after = null;
        $limit = 100;

        do {
            // Build URL with query parameters
            $url = 'https://api.openai.com/v1/assistants';
            $query_params = [
                'limit' => $limit,
                'order' => 'desc'
            ];

            if ($after) {
                $query_params['after'] = $after;
            }

            $url = add_query_arg($query_params, $url);

            // Load assistants from OpenAI API
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'User-Agent' => 'PolyTrans/1.0',
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error(__('Failed to load assistants: ', 'polytrans') . $response->get_error_message());
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                $error_data = json_decode($body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Failed to load assistants.', 'polytrans');
                wp_send_json_error($error_message);
                return;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!isset($data['data']) || !is_array($data['data'])) {
                break;
            }

            // Add assistants from this page to the collection
            $all_assistants = array_merge($all_assistants, $data['data']);

            // Check if there are more pages
            $has_more = $data['has_more'] ?? false;

            // Get the last assistant ID for pagination
            if ($has_more && !empty($data['data'])) {
                $last_assistant = end($data['data']);
                $after = $last_assistant['id'];
            } else {
                $after = null;
            }
        } while ($after !== null);

        if (empty($all_assistants)) {
            wp_send_json_error(__('No assistants found.', 'polytrans'));
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
}
