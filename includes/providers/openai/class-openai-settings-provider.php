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
            'openai_assistants'
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
?>
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

            <!-- Source Language Section -->
            <div class="openai-source-language-section" style="margin-top:2em;">
                <h3><?php esc_html_e('OpenAI Source Language', 'polytrans'); ?></h3>
                <select name="openai_source_language" id="openai-source-language" style="max-width:200px;">
                    <?php foreach ($languages as $i => $lang): ?>
                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($openai_source_language, $lang); ?>>
                            <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br><small><?php esc_html_e('OpenAI will translate all content to this language first, then to the target language if needed. This enables multi-step translation through your preferred intermediate language.', 'polytrans'); ?></small>
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
            'openai_assistants' => []
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
                    <th style="width:25%;"><?php esc_html_e('Translation Pair', 'polytrans'); ?></th>
                    <th style="width:20%;"><?php esc_html_e('Type', 'polytrans'); ?></th>
                    <th style="width:20%;"><?php esc_html_e('Translation Path', 'polytrans'); ?></th>
                    <th style="width:35%;"><?php esc_html_e('Assistant', 'polytrans'); ?></th>
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
                            <span class="pair-type-badge pair-type-to-openai" style="display:none;"><?php esc_html_e('Source → OpenAI', 'polytrans'); ?></span>
                            <span class="pair-type-badge pair-type-from-openai" style="display:none;"><?php esc_html_e('OpenAI → Target', 'polytrans'); ?></span>
                        </td>
                        <td>
                            <span class="translation-path-direct"><?php esc_html_e('Direct', 'polytrans'); ?></span>
                            <br><small class="translation-path-details">
                                <?php echo esc_html($source_name . ' → ' . $target_name); ?>
                            </small>
                        </td>
                        <td>
                            <select class="assistant-select"
                                id="assistant_select_<?php echo esc_attr($assistant_key); ?>"
                                name="openai_assistants[<?php echo esc_attr($assistant_key); ?>]"
                                style="width:100%;max-width:350px;"
                                data-pair="<?php echo esc_attr($assistant_key); ?>"
                                data-selected="<?php echo esc_attr($selected_assistant); ?>">
                                <option value=""><?php esc_html_e('Not selected', 'polytrans'); ?></option>
                                <!-- Options will be populated via JavaScript when assistants are loaded -->
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr class="no-relevant-pairs-message" style="display:none;">
                    <td colspan="4" style="text-align:center; color:#888; font-style:italic; padding:20px;">
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
}
