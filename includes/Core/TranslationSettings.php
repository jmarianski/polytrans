<?php

namespace PolyTrans\Core;

/**
 * Translation Settings Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class TranslationSettings
{

    private $langs;
    private $lang_names;
    private $statuses;
    
    /**
     * Get translation path rules with backward compatibility
     * Checks for 'translation_path_rules' first, falls back to 'openai_path_rules'
     */
    private static function get_path_rules($settings)
    {
        return $settings['translation_path_rules'] ?? $settings['openai_path_rules'] ?? [];
    }
    
    /**
     * Get assistants mapping with backward compatibility
     * Checks for 'assistants_mapping' first, falls back to 'openai_assistants'
     */
    private static function get_assistants_mapping($settings)
    {
        return $settings['assistants_mapping'] ?? $settings['openai_assistants'] ?? [];
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
        $this->lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];
        $this->statuses = [
            'publish' => __('Publish', 'polytrans'),
            'draft' => __('Draft', 'polytrans'),
            'pending' => __('Pending Review', 'polytrans'),
            'source' => __('Same as source', 'polytrans'),
        ];
        
        // Register universal AJAX endpoints
        // Note: Also registered in SettingsMenu::__construct() for early availability
        add_action('wp_ajax_polytrans_validate_provider_key', [$this, 'ajax_validate_provider_key']);
    }
    
    /**
     * Static wrapper for AJAX handler (called from SettingsMenu)
     */
    public static function ajax_validate_provider_key_static()
    {
        $instance = new self();
        $instance->ajax_validate_provider_key();
    }
    
    /**
     * Universal AJAX handler for validating provider API keys
     */
    public function ajax_validate_provider_key()
    {
        // Check nonce - accept multiple nonce types for compatibility
        $nonce_check = false;
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field($_POST['nonce']);
            // Try different nonce types (in order of preference):
            // 1. polytrans_nonce (from SettingsMenu - Universal JS)
            // 2. polytrans_settings (form nonce)
            // 3. polytrans_openai_nonce (backward compatibility)
            // 4. polytrans_workflows_nonce (backward compatibility)
            $nonce_check = wp_verify_nonce($nonce, 'polytrans_nonce') ||
                          wp_verify_nonce($nonce, 'polytrans_settings') ||
                          wp_verify_nonce($nonce, 'polytrans_openai_nonce') ||
                          wp_verify_nonce($nonce, 'polytrans_workflows_nonce');
        }
        
        if (!$nonce_check) {
            // Log for debugging
            error_log("PolyTrans: Nonce check failed. Nonce: " . ($_POST['nonce'] ?? 'not set') . ", Action: " . ($_POST['action'] ?? 'not set'));
            wp_send_json_error(__('Security check failed.', 'polytrans'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', 'polytrans'));
            return;
        }
        
        $provider_id = sanitize_text_field($_POST['provider_id'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($provider_id)) {
            wp_send_json_error(__('Provider ID is required.', 'polytrans'));
            return;
        }
        
        if (empty($api_key)) {
            wp_send_json_error(__('API key is required.', 'polytrans'));
            return;
        }
        
        // Get provider from registry
        $registry = \PolyTrans_Provider_Registry::get_instance();
        $provider = $registry->get_provider($provider_id);
        
        if (!$provider) {
            wp_send_json_error(sprintf(__('Provider "%s" not found.', 'polytrans'), $provider_id));
            return;
        }
        
        // Get settings provider
        $settings_provider_class = $provider->get_settings_provider_class();
        if (!$settings_provider_class || !class_exists($settings_provider_class)) {
            wp_send_json_error(sprintf(__('Settings provider not found for "%s".', 'polytrans'), $provider_id));
            return;
        }
        
        $settings_provider = new $settings_provider_class();
        
        // Try to validate using method or hook
        $is_valid = false;
        $error_message = '';
        
        // Method 1: Use validate_api_key() method if available
        if (method_exists($settings_provider, 'validate_api_key')) {
            try {
                $is_valid = $settings_provider->validate_api_key($api_key);
                if (!$is_valid) {
                    $error_message = __('Invalid API key.', 'polytrans');
                }
            } catch (\Exception $e) {
                error_log("PolyTrans: Failed to validate API key for {$provider_id}: " . $e->getMessage());
                $error_message = __('Validation failed due to an error: ', 'polytrans') . $e->getMessage();
                wp_send_json_error($error_message);
                return;
            }
        } else {
            // Method 2: Use hook for external plugins
            $is_valid = apply_filters("polytrans_validate_api_key_{$provider_id}", false, $api_key);
            
            // Fallback: basic check (not empty)
            if ($is_valid === false) {
                $is_valid = !empty($api_key);
                if (!$is_valid) {
                    $error_message = __('API key cannot be empty.', 'polytrans');
                }
            }
        }
        
        if ($is_valid) {
            wp_send_json_success(__('API key is valid!', 'polytrans'));
        } else {
            wp_send_json_error($error_message ?: __('Invalid API key.', 'polytrans'));
        }
    }

    /**
     * Render the settings page
     */
    public function render()
    {
        $settings = get_option('polytrans_settings', []);

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('polytrans_settings')) {
            $this->save_settings($settings);
        }

        $this->output_page($settings);
    }

    /**
     * Save settings
     */
    private function save_settings(&$settings)
    {
        // Verify nonce for security
        if (!check_admin_referer('polytrans_settings')) {
            wp_die(esc_html__('Security check failed.', 'polytrans'));
        }

        $registry = \PolyTrans_Provider_Registry::get_instance();

        // Handle enabled translation providers (checkboxes)
        $settings['enabled_translation_providers'] = isset($_POST['enabled_translation_providers']) 
            ? array_map('sanitize_text_field', wp_unslash($_POST['enabled_translation_providers'])) 
            : ['google']; // Default to Google if none selected
        
        // Keep backward compatibility: set translation_provider to first enabled provider
        $settings['translation_provider'] = !empty($settings['enabled_translation_providers']) 
            ? $settings['enabled_translation_providers'][0] 
            : 'google';
        $settings['translation_transport_mode'] = sanitize_text_field(wp_unslash($_POST['translation_transport_mode'] ?? 'external'));
        $settings['translation_endpoint'] = esc_url_raw(wp_unslash($_POST['translation_endpoint'] ?? ''));
        $settings['translation_receiver_endpoint'] = esc_url_raw(wp_unslash($_POST['translation_receiver_endpoint'] ?? ''));
        $settings['translation_receiver_secret'] = sanitize_text_field(wp_unslash($_POST['translation_receiver_secret'] ?? ''));
        $settings['translation_receiver_secret_method'] = sanitize_text_field(wp_unslash($_POST['translation_receiver_secret_method'] ?? 'header_bearer'));
        $settings['translation_receiver_secret_custom_header'] = sanitize_text_field(wp_unslash($_POST['translation_receiver_secret_custom_header'] ?? 'x-polytrans-secret'));
        $settings['edit_link_base_url'] = esc_url_raw(wp_unslash($_POST['edit_link_base_url'] ?? ''));
        $settings['enable_db_logging'] = isset($_POST['enable_db_logging']) ? '1' : '0';
        $settings['allowed_sources'] = isset($_POST['allowed_sources']) ? array_map('sanitize_text_field', wp_unslash($_POST['allowed_sources'])) : [];
        $settings['allowed_targets'] = isset($_POST['allowed_targets']) ? array_map('sanitize_text_field', wp_unslash($_POST['allowed_targets'])) : [];
        $settings['source_language'] = sanitize_text_field(wp_unslash($_POST['source_language'] ?? 'pl'));
        $settings['base_tags'] = sanitize_textarea_field(wp_unslash($_POST['base_tags'] ?? ''));

        // Handle provider-specific settings
        // IMPORTANT: Save settings for ALL providers with settings UI, not just the selected provider
        // This allows users to configure multiple providers (e.g., OpenAI for assistants, Google for default translation)
        $selected_provider = $registry->get_provider($settings['translation_provider']);
        $providers = $registry->get_providers();
        
        // Process settings for all providers that have settings UI
        foreach ($providers as $provider_id => $provider) {
            $settings_provider_class = $provider->get_settings_provider_class();
            if (!$settings_provider_class || !class_exists($settings_provider_class)) {
                continue;
            }
            
            $settings_provider = new $settings_provider_class();
            $provider_settings = $settings_provider->validate_settings($_POST);
            
            // For OpenAI: always save path rules and assistants (used by TranslationPathExecutor)
            // Also save API key and model if provided (needed for assistants and managed assistants)
            if ($provider_id === 'openai') {
                // Save all OpenAI settings (API key, model, path rules, assistants)
                if (isset($provider_settings['openai_api_key'])) {
                    $settings['openai_api_key'] = $provider_settings['openai_api_key'];
                }
                if (isset($provider_settings['openai_model'])) {
                    $settings['openai_model'] = $provider_settings['openai_model'];
                }
                
                // Save path rules in both formats (new universal + old OpenAI-specific for backward compatibility)
                if (isset($provider_settings['openai_path_rules'])) {
                    $path_rules = $provider_settings['openai_path_rules'];
                    $settings['translation_path_rules'] = $path_rules; // New universal name
                    $settings['openai_path_rules'] = $path_rules; // Old name for backward compatibility
                }
                
                // Save assistants mapping in both formats (new universal + old OpenAI-specific for backward compatibility)
                if (isset($provider_settings['openai_assistants'])) {
                    $assistants_mapping = $provider_settings['openai_assistants'];
                    
                    // Validate assistants using PathValidator before saving
                    $validation = \PolyTrans\Core\PathValidator::validate_assistants_mapping(
                        $assistants_mapping,
                        $settings
                    );
                    
                    if (!$validation['valid'] && !empty($validation['errors'])) {
                        // Show admin notice with validation errors
                        $error_messages = [];
                        foreach ($validation['errors'] as $pair => $error) {
                            $error_messages[] = "$pair: $error";
                        }
                        add_settings_error(
                            'polytrans_settings',
                            'path_validation_error',
                            __('Some provider/assistant mappings are invalid: ', 'polytrans') . implode('; ', $error_messages),
                            'error'
                        );
                    }
                    
                    // Save even if there are errors (user can fix them later)
                    $settings['assistants_mapping'] = $assistants_mapping; // New universal name
                    $settings['openai_assistants'] = $assistants_mapping; // Old name for backward compatibility
                }
            } else {
                // For other providers: merge all settings
                $settings = array_merge($settings, $provider_settings);
            }
        }

        foreach ($this->langs as $lang) {
            $settings[$lang] = [
                'status' => sanitize_text_field(wp_unslash($_POST['status'][$lang] ?? 'draft')),
                'reviewer' => sanitize_text_field(wp_unslash($_POST['reviewer'][$lang] ?? 'none')),
            ];
        }

        $settings['reviewer_email'] = wp_kses_post(wp_unslash($_POST['reviewer_email'] ?? ''));
        $settings['author_email'] = wp_kses_post(wp_unslash($_POST['author_email'] ?? ''));
        $settings['reviewer_email_title'] = wp_kses_post(wp_unslash($_POST['reviewer_email_title'] ?? ''));
        $settings['author_email_title'] = wp_kses_post(wp_unslash($_POST['author_email_title'] ?? ''));

        // Notification filters
        
        $settings['notification_allowed_domains'] = \PolyTrans\Core\NotificationFilter::sanitize_domains(
            wp_unslash($_POST['notification_allowed_domains'] ?? '')
        );

        update_option('polytrans_settings', $settings);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'polytrans') . '</p></div>';
    }

    /**
     * Output the settings page HTML
     */
    private function output_page($settings)
    {
        $registry = \PolyTrans_Provider_Registry::get_instance();
        $translation_provider = $settings['translation_provider'] ?? 'google';
        $enabled_providers = $settings['enabled_translation_providers'] ?? ['google'];
        $translation_endpoint = $settings['translation_endpoint'] ?? '';
        $translation_receiver_endpoint = $settings['translation_receiver_endpoint'] ?? '';
        $allowed_sources = $settings['allowed_sources'] ?? [];
        $allowed_targets = $settings['allowed_targets'] ?? [];
        $source_language = $settings['source_language'] ?? 'pl';
        $base_tags = $settings['base_tags'] ?? '';
        $reviewer_email = $settings['reviewer_email'] ?? '';
        $reviewer_email_title = $settings['reviewer_email_title'] ?? '';
        $author_email = $settings['author_email'] ?? '';
        $author_email_title = $settings['author_email_title'] ?? '';

        // Get available providers and their settings providers
        // Skip providers with empty settings UI (like Google)
        $providers = $registry->get_providers();
        $settings_providers = [];
        foreach ($providers as $provider_id => $provider) {
            $settings_provider_class = $provider->get_settings_provider_class();
            if ($settings_provider_class && class_exists($settings_provider_class)) {
                $settings_provider_instance = new $settings_provider_class();
                
                // Skip Google - it has no settings UI
                if ($provider_id === 'google') {
                    // Still enqueue assets if needed, but don't add to tabs
                    continue;
                }
                
                // Check if provider is used (enabled OR has API key configured)
                if (!$this->is_provider_used($provider_id, $settings_provider_instance, $settings, $enabled_providers)) {
                    continue; // Skip unused providers
                }
                
                $settings_providers[$provider_id] = $settings_provider_instance;
                // Always enqueue OpenAI assets since they're used in workflows regardless of main provider
                // For other providers, only enqueue if they're the selected provider
                if ($provider_id === 'openai' || $provider_id === $translation_provider) {
                    $settings_provider_instance->enqueue_assets();
                }
            }
        }

        ob_start();
?>
        <div class="wrap">
            <h1><?php esc_html_e('Translation Settings', 'polytrans'); ?></h1>
            <p><?php esc_html_e('Configure translation workflow for each language. You can specify the default post status after translation, assign a reviewer, and customize the emails sent to the reviewer and author.', 'polytrans'); ?></p>

            <!-- Tab Navigation -->
            <div class="nav-tab-wrapper">
                <a href="#provider-settings" class="nav-tab nav-tab-active" id="provider-tab"><?php esc_html_e('Translation Provider', 'polytrans'); ?></a>
                <a href="#basic-settings" class="nav-tab" id="basic-tab"><?php esc_html_e('Basic Settings', 'polytrans'); ?></a>
                <a href="#language-paths-settings" class="nav-tab" id="language-paths-tab"><?php esc_html_e('Language Paths', 'polytrans'); ?></a>
                <a href="#tag-settings" class="nav-tab" id="tag-tab"><?php esc_html_e('Tag Settings', 'polytrans'); ?></a>
                <a href="#email-settings" class="nav-tab" id="email-tab"><?php esc_html_e('Email Settings', 'polytrans'); ?></a>
                <?php foreach ($settings_providers as $provider_id => $settings_provider): ?>
                    <a href="#<?php echo esc_attr($provider_id); ?>-settings" class="nav-tab provider-settings-tab" id="<?php echo esc_attr($provider_id); ?>-tab">
                        <?php echo esc_html($settings_provider->get_tab_label()); ?>
                    </a>
                <?php endforeach; ?>
                <a href="#advanced-settings" class="nav-tab" id="advanced-tab"><?php esc_html_e('Advanced Settings', 'polytrans'); ?></a>
            </div>

            <form method="post">
                <?php wp_nonce_field('polytrans_settings'); ?>

                <!-- Translation Provider Tab -->
                <div id="provider-settings" class="tab-content active">
                    <div class="translation-provider-section">
                        <h2><?php esc_html_e('Enabled Translation Providers', 'polytrans'); ?></h2>
                        <p><?php esc_html_e('Select which translation services are available for use. You can enable multiple providers and assign specific providers or assistants to language pairs in the Language Paths tab.', 'polytrans'); ?></p>
                        <div style="margin-bottom:2em;">
                            <?php foreach ($providers as $provider_id => $provider): ?>
                                <label style="display:block;margin-bottom:0.5em;">
                                    <input type="checkbox" 
                                        name="enabled_translation_providers[]" 
                                        value="<?php echo esc_attr($provider_id); ?>" 
                                        <?php checked(in_array($provider_id, $enabled_providers), true); ?>
                                        class="provider-selection-checkbox">
                                    <strong><?php echo esc_html($provider->get_name()); ?></strong>&nbsp;
                                    <span style="color:#666;"><?php echo esc_html($provider->get_description()); ?></span>
                                    <?php if ($provider_id === 'openai'): ?>
                                        <span style="color:#888; font-style:italic;"> (<?php esc_html_e('provides assistants, not direct translation', 'polytrans'); ?>)</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Enable providers to make them available in Language Paths. Translation providers (like Google Translate) provide direct translation, while AI providers (like OpenAI, Claude, Gemini) provide AI assistants.', 'polytrans'); ?>
                        </p>
                    </div>
                </div>

                <!-- Basic Settings Tab -->
                <div id="basic-settings" class="tab-content" style="display:none;">
                    <h2><?php esc_html_e('Allowed Source Languages', 'polytrans'); ?></h2>
                    <p><?php esc_html_e('Select which source languages are allowed for automatic translation. Only posts in these languages will be considered as sources for translation.', 'polytrans'); ?></p>
                    <div class="language-grid">
                        <?php foreach ($this->langs as $i => $lang): ?>
                            <label>
                                <input type="checkbox" name="allowed_sources[]" value="<?php echo esc_attr($lang); ?>" <?php checked(in_array($lang, $allowed_sources)); ?>>
                                <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <h2><?php esc_html_e('Allowed Target Languages', 'polytrans'); ?></h2>
                    <p><?php esc_html_e('Select which target languages are allowed for automatic translation. Only these languages will be available as translation targets and shown in the configuration table below.', 'polytrans'); ?></p>
                    <div class="language-grid">
                        <?php foreach ($this->langs as $i => $lang): ?>
                            <label>
                                <input type="checkbox" name="allowed_targets[]" value="<?php echo esc_attr($lang); ?>" <?php checked(in_array($lang, $allowed_targets)); ?>>
                                <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php $this->render_language_config_table($settings); ?>
                </div>

                <!-- Language Paths Tab -->
                <div id="language-paths-settings" class="tab-content" style="display:none;">
                    <?php $this->render_language_pairs_settings($settings); ?>
                </div>

                <!-- Tag Settings Tab -->
                <div id="tag-settings" class="tab-content" style="display:none;">
                    <?php $this->render_tag_settings($source_language, $base_tags); ?>
                </div>

                <!-- Email Settings Tab -->
                <div id="email-settings" class="tab-content" style="display:none;">
                    <?php $this->render_email_settings($reviewer_email, $reviewer_email_title, $author_email, $author_email_title); ?>
                </div>

                <!-- Dynamic Provider Settings Tabs -->
                <?php foreach ($settings_providers as $provider_id => $settings_provider): ?>
                    <div id="<?php echo esc_attr($provider_id); ?>-settings" class="tab-content provider-settings-content" style="display:none;">
                        <?php 
                        // Try to use provider's custom UI, fallback to universal UI
                        ob_start();
                        try {
                            $settings_provider->render_settings_ui($settings, $this->langs, $this->lang_names);
                        } catch (\Exception $e) {
                            // Provider doesn't have custom UI or error occurred
                            ob_end_clean();
                            ob_start();
                        }
                        $custom_ui = ob_get_clean();
                        
                        // Check if provider returned meaningful output
                        // Empty string or just whitespace means use universal UI
                        if (!empty(trim($custom_ui))) {
                            // Provider has custom UI
                            echo $custom_ui;
                        } else {
                            // Use universal UI based on manifest
                            $this->render_universal_provider_ui($provider_id, $settings_provider, $settings);
                        }
                        ?>
                    </div>
                <?php endforeach; ?>

                <!-- Advanced Settings Tab -->
                <div id="advanced-settings" class="tab-content" style="display:none;">
                    <?php $this->render_advanced_settings($translation_endpoint, $translation_receiver_endpoint, $settings); ?>
                </div>

                <p><input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'polytrans'); ?>"></p>
            </form>

            <script>
                jQuery(document).ready(function($) {
                    // Handle provider selection changes

                    // Handle tab navigation
                    $('.nav-tab').on('click', function(e) {
                        e.preventDefault();
                        var targetTab = $(this).attr('href');

                        // Only proceed if target exists
                        if ($(targetTab).length === 0) {
                            return;
                        }

                        // Remove active class from all tabs and hide all content
                        $('.nav-tab').removeClass('nav-tab-active');
                        $('.tab-content').hide();

                        // Add active class to clicked tab and show target content
                        $(this).addClass('nav-tab-active');
                        $(targetTab).show();
                    });

                    // Handle secret method changes to show/hide custom header field
                    $('select[name="translation_receiver_secret_method"]').on('change', function() {
                        var selectedMethod = $(this).val();
                        var customHeaderSection = $('#custom-header-section');

                        if (selectedMethod === 'header_custom') {
                            customHeaderSection.show();
                        } else {
                            customHeaderSection.hide();
                        }
                    });

                    // Workflow step management
                    var workflowStepIndex = 0;

                    function renderWorkflowStep(step) {
                        var stepHtml = `
                            <div class="workflow-step" data-index="${step.index}" style="margin-bottom:1em;padding:1em;border:1px solid #ddd;border-radius:4px;">
                                <h4><?php esc_html_e('Workflow Step', 'polytrans'); ?> ${step.index + 1}</h4>
                                <div style="margin-bottom:0.5em;">
                                    <label><strong><?php esc_html_e('Action Type', 'polytrans'); ?></strong></label>
                                    <select name="workflow_steps[${step.index}][action]" style="width:100%">
                                        <option value="update_status"><?php esc_html_e('Update Post Status', 'polytrans'); ?></option>
                                        <option value="send_reviewer_notification"><?php esc_html_e('Send Reviewer Notification', 'polytrans'); ?></option>
                                        <option value="send_author_notification"><?php esc_html_e('Send Author Notification', 'polytrans'); ?></option>
                                        <option value="call_external_api"><?php esc_html_e('Call External API', 'polytrans'); ?></option>
                                    </select>
                                </div>
                                <div class="step-parameters" style="margin-bottom:0.5em;">
                                    <!-- Parameters for the action will be dynamically added here -->
                                </div>
                                <button type="button" class="button remove-workflow-step" style="background-color:#dc3232;color:white;"><?php esc_html_e('Remove Step', 'polytrans'); ?></button>
                            </div>
                        `;

                        $('#workflow-steps-container').append(stepHtml);

                        // Initialize parameters for the step
                        initWorkflowStepParameters(step);
                    }

                    function initWorkflowStepParameters(step) {
                        var action = step.action;

                        // Example: Initialize parameters for "Update Post Status" action
                        if (action === 'update_status') {
                            var statusSelect = `
                                <label><strong><?php esc_html_e('Post Status', 'polytrans'); ?></strong></label>
                                <select name="workflow_steps[${step.index}][status]" style="width:100%">
                                    <option value="publish"><?php esc_html_e('Publish', 'polytrans'); ?></option>
                                    <option value="draft"><?php esc_html_e('Draft', 'polytrans'); ?></option>
                                    <option value="pending"><?php esc_html_e('Pending Review', 'polytrans'); ?></option>
                                    <option value="source"><?php esc_html_e('Same as source', 'polytrans'); ?></option>
                                </select>
                            `;
                            $(`.step-parameters[data-index="${step.index}"]`).html(statusSelect);
                        }

                        // Add more parameter initializations for other actions as needed
                    }

                    // Add workflow step button click
                    $('#add-workflow-step').on('click', function() {
                        var newIndex = workflowStepIndex++;
                        renderWorkflowStep({
                            index: newIndex,
                            action: 'update_status'
                        }); // Default to "Update Post Status"
                    });

                    // Remove workflow step button click (delegated)
                    $(document).on('click', '.remove-workflow-step', function() {
                        $(this).closest('.workflow-step').remove();
                    });
                });
            </script>
        </div>
    <?php
        $output = ob_get_clean();
        echo $output;
    }

    /**
     * Render language configuration table
     */
    private function render_language_config_table($settings)
    {
    ?>
        <table class="widefat fixed striped" id="translation-settings-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Language', 'polytrans'); ?></th>
                    <th><?php esc_html_e('Post Status After Translation', 'polytrans'); ?></th>
                    <th><?php esc_html_e('Reviewer', 'polytrans'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $has_target = false;
                foreach ($this->langs as $i => $lang):
                    $has_target = true;
                    $row = $settings[$lang] ?? ['status' => 'pending', 'reviewer' => 'none'];
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?></strong></td>
                        <td>
                            <select name="status[<?php echo esc_attr($lang); ?>]">
                                <?php foreach ($this->statuses as $val => $label): ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($row['status'], $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <br><small><?php esc_html_e('Choose the status for posts after translation in this language. "Same as source" means the translation will be published if the source is published, or draft if the source is draft.', 'polytrans'); ?></small>
                        </td>
                        <td>
                            <?php
                            $reviewer_id = $row['reviewer'] ?? 'none';
                            $reviewer_user = ($reviewer_id && $reviewer_id !== 'none') ? get_user_by('id', $reviewer_id) : null;
                            $reviewer_label = $reviewer_user ? esc_attr($reviewer_user->display_name . ' (' . $reviewer_user->user_email . ')') : '';
                            ?>
                            <input type="text"
                                class="user-autocomplete-input"
                                id="reviewer_autocomplete_<?php echo esc_attr($lang); ?>"
                                name="reviewer_suggest[<?php echo esc_attr($lang); ?>]"
                                value="<?php echo esc_attr($reviewer_label); ?>"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e('Type to search user...', 'polytrans'); ?>"
                                style="width:100%;max-width:350px;"
                                data-user-autocomplete-for="#reviewer_hidden_<?php echo esc_attr($lang); ?>"
                                data-user-autocomplete-clear="#reviewer_clear_<?php echo esc_attr($lang); ?>">
                            <input type="hidden" name="reviewer[<?php echo esc_attr($lang); ?>]" id="reviewer_hidden_<?php echo esc_attr($lang); ?>" value="<?php echo esc_attr($reviewer_id); ?>">
                            <button type="button" class="button user-autocomplete-clear" id="reviewer_clear_<?php echo esc_attr($lang); ?>" data-lang="<?php echo esc_attr($lang); ?>" style="margin-left:0.5em;<?php if ($reviewer_id === 'none') echo 'display:none;'; ?>">&times;</button>
                            <br><small><?php esc_html_e('Select a reviewer for this language. "None" disables review.', 'polytrans'); ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$has_target): ?>
                    <tr>
                        <td colspan="3" style="text-align:center; color:#888;"><?php esc_html_e('No target languages selected. Please select at least one target language above.', 'polytrans'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php
    }

    /**
     * Render email settings section
     */
    private function render_email_settings($reviewer_email, $reviewer_email_title, $author_email, $author_email_title)
    {
        $settings = get_option('polytrans_settings', []);
        $notification_allowed_domains = $settings['notification_allowed_domains'] ?? '';
        $notification_allowed_domains_str = is_array($notification_allowed_domains) ? implode(', ', $notification_allowed_domains) : $notification_allowed_domains;
    ?>
        <!-- Email Templates Section -->
        <div class="translation-emails-row" style="display:flex;gap:2em;flex-wrap:wrap;">
            <div class="translation-email-col">
                <h2><?php esc_html_e('Reviewer Email Template', 'polytrans'); ?></h2>
                <label for="reviewer_email_title"><strong><?php esc_html_e('Email Subject (Reviewer)', 'polytrans'); ?></strong></label><br />
                <input type="text" id="reviewer_email_title" name="reviewer_email_title" value="<?php echo esc_attr($reviewer_email_title); ?>" style="width:100%" />
                <br><small><?php esc_html_e('Subject of the email sent to reviewer. You can use {title} for the post title.', 'polytrans'); ?></small><br><br>
                <?php
                $editor_id = 'reviewer_email';
                $editor_settings = [
                    'textarea_name' => 'reviewer_email',
                    'textarea_rows' => 6,
                    'media_buttons' => false,
                    'teeny' => true,
                    'quicktags' => true,
                ];
                wp_editor($reviewer_email, $editor_id, $editor_settings);
                ?>
                <small><?php esc_html_e('Email sent to reviewer when translation is ready for review. Use {link} for the edit link and {title} for the post title. Note: Edit links will use the "Edit Link Base URL" from Advanced Settings if configured, which is recommended for background processes.', 'polytrans'); ?></small>
            </div>
            <div class="translation-email-col">
                <h2><?php esc_html_e('Author Email Template (when translation is published)', 'polytrans'); ?></h2>
                <label for="author_email_title"><strong><?php esc_html_e('Email Subject (Author)', 'polytrans'); ?></strong></label><br />
                <input type="text" id="author_email_title" name="author_email_title" value="<?php echo esc_attr($author_email_title); ?>" style="width:100%" />
                <br><small><?php esc_html_e('Subject of the email sent to the author. You can use {title} for the post title.', 'polytrans'); ?></small><br><br>
                <?php
                $editor_id = 'author_email';
                $editor_settings['textarea_name'] = 'author_email';
                wp_editor($author_email, $editor_id, $editor_settings);
                ?>
                <small><?php esc_html_e('Email sent to the author when translation is published. Use {link} for the edit link and {title} for the post title. Note: Edit links will use the "Edit Link Base URL" from Advanced Settings if configured, which is recommended for background processes.', 'polytrans'); ?></small>
            </div>
        </div>

        <hr style="margin: 2em 0;">

        <!-- Notification Filters Section -->
        <div class="notification-filters-section" style="margin-bottom: 2em; padding: 1em; background: #f9f9f9; border-left: 4px solid #2271b1;">
            <h2><?php esc_html_e('Author Notification Filters', 'polytrans'); ?></h2>
            <p style="margin-top: 0;">
                <?php esc_html_e('Control which authors receive email notifications when translations are completed. This is useful to prevent sending emails to external authors (e.g., guest contributors).', 'polytrans'); ?>
                <br>
                <strong><?php esc_html_e('Note:', 'polytrans'); ?></strong> <?php esc_html_e('Reviewer notifications are always sent (reviewers are internal users from settings).', 'polytrans'); ?>
            </p>
            
            <div style="margin-bottom: 1.5em;">
                <label for="notification_allowed_domains"><strong><?php esc_html_e('Allowed Email Domains', 'polytrans'); ?></strong></label><br>
                <input type="text" name="notification_allowed_domains" id="notification_allowed_domains" value="<?php echo esc_attr($notification_allowed_domains_str); ?>" style="width: 100%; max-width: 500px;" placeholder="example.com, company.org">
                <br>
                <small>
                    <?php esc_html_e('Comma-separated list of allowed email domains (e.g., "example.com, company.org"). Only authors with emails from these domains will receive notifications. Leave empty to allow all domains.', 'polytrans'); ?>
                </small>
            </div>
            
            <p style="margin-bottom: 0; padding: 0.5em; background: #fff; border-left: 3px solid #72aee6;">
                <strong><?php esc_html_e('How it works:', 'polytrans'); ?></strong><br>
                <?php esc_html_e('If filter is set, only authors with emails from the specified domains will receive notifications.', 'polytrans'); ?><br>
                <?php esc_html_e('If no filter is set, all authors will receive notifications (default behavior).', 'polytrans'); ?>
            </p>
        </div>
    <?php
    }

    /**
     * Render advanced settings section
     */
    private function render_advanced_settings($translation_endpoint, $translation_receiver_endpoint, $settings)
    {
    ?>
        <h1><?php esc_html_e('Advanced Integration Settings', 'polytrans'); ?></h1>

        <h2><?php esc_html_e('Transport Options', 'polytrans'); ?></h2>
        <select name="translation_transport_mode" style="width:100%">
            <?php $transport_mode = $settings['translation_transport_mode'] ?? 'external'; ?>
            <option value="external" <?php selected($transport_mode, 'external'); ?>><?php esc_html_e('External Server (Default)', 'polytrans'); ?></option>
            <option value="internal" <?php selected($transport_mode, 'internal'); ?>><?php esc_html_e('Internal (Local Endpoint)', 'polytrans'); ?></option>
        </select>
        <div style="margin-top:8px;">
            <small>
                <?php esc_html_e('Choose how to handle translation transport:', 'polytrans'); ?><br>
                <strong><?php esc_html_e('External Server:', 'polytrans'); ?></strong> <?php esc_html_e('Sends data to the external endpoints specified below and expects the server to send data back to the specified receiver endpoint.', 'polytrans'); ?><br>
                <strong><?php esc_html_e('Internal:', 'polytrans'); ?></strong> <?php esc_html_e('Uses spawned processes to stay in the same server and achieve asynchronity. May not work in certain environments.', 'polytrans'); ?>
            </small>
        </div><br><br>

        <h2><?php esc_html_e('Translation Endpoint', 'polytrans'); ?></h2>
        <input type="url" name="translation_endpoint" value="<?php echo esc_attr($translation_endpoint); ?>" style="width:100%" placeholder="https://example.com/translate-endpoint" />
        <br><small><?php esc_html_e('Specify the URL of the translation endpoint. The system will send JSON with source_language, target_language, title, and text to this endpoint.', 'polytrans'); ?></small><br><br>

        <h2><?php esc_html_e('Translation Receiver Endpoint', 'polytrans'); ?></h2>
        <input type="url" name="translation_receiver_endpoint" value="<?php echo esc_attr($translation_receiver_endpoint); ?>" style="width:100%" placeholder="https://example.com/receive-endpoint" />
        <br><small><?php esc_html_e('Specify the URL of the translation receiver endpoint. This is where translated data will be sent (e.g., http://localhost:9008/wp-json/polytrans/v1/translation/receive-post).', 'polytrans'); ?></small><br><br>

        <h2><?php esc_html_e('Translation Receiver Secret', 'polytrans'); ?></h2>
        <div style="display:flex;gap:0.5em;align-items:center;max-width:600px;">
            <input type="text" id="translation-receiver-secret" name="translation_receiver_secret" value="<?php echo esc_attr($settings['translation_receiver_secret'] ?? ''); ?>" data-initial="<?php echo esc_attr($settings['translation_receiver_secret'] ?? ''); ?>" style="width:100%" placeholder="<?php esc_attr_e('Enter secret for receiver authentication', 'polytrans'); ?>" autocomplete="off" />
            <button type="button" id="generate-translation-secret" class="button" style="white-space:nowrap;"><?php esc_html_e('Generate Secret', 'polytrans'); ?></button>
        </div>
        <br><small><?php esc_html_e('This secret will be used to authenticate translation requests. Keep it private!', 'polytrans'); ?></small><br><br>

        <h2><?php esc_html_e('How to Pass Secret', 'polytrans'); ?></h2>
        <select name="translation_receiver_secret_method" style="width:100%">
            <?php $method = $settings['translation_receiver_secret_method'] ?? 'header_bearer'; ?>
            <option value="none" <?php selected($method, 'none'); ?>><?php esc_html_e('None (do not send secret)', 'polytrans'); ?></option>
            <option value="get_param" <?php selected($method, 'get_param'); ?>><?php esc_html_e('GET parameter (?secret=...)', 'polytrans'); ?></option>
            <option value="header_bearer" <?php selected($method, 'header_bearer'); ?>><?php esc_html_e('HTTP Header: Authorization: Bearer ...', 'polytrans'); ?></option>
            <option value="header_custom" <?php selected($method, 'header_custom'); ?>><?php esc_html_e('HTTP Header: Custom Header Name', 'polytrans'); ?></option>
            <option value="post_param" <?php selected($method, 'post_param'); ?>><?php esc_html_e('POST body field (JSON: secret)', 'polytrans'); ?></option>
        </select>
        <br><small><?php esc_html_e('Choose how the secret should be sent to the receiver endpoint. Select "None" to disable secret sending/checking.', 'polytrans'); ?></small><br><br>

        <div id="custom-header-section" style="<?php echo ($method !== 'header_custom') ? 'display:none;' : ''; ?>">
            <h2><?php esc_html_e('Custom Header Name', 'polytrans'); ?></h2>
            <input type="text" name="translation_receiver_secret_custom_header" value="<?php echo esc_attr($settings['translation_receiver_secret_custom_header'] ?? 'x-polytrans-secret'); ?>" style="width:100%;max-width:400px;" placeholder="x-polytrans-secret" />
            <br><small><?php esc_html_e('Specify the custom header name to use when sending the secret. Default is "x-polytrans-secret".', 'polytrans'); ?></small><br><br>
        </div>

        <h2><?php esc_html_e('Edit Link Base URL', 'polytrans'); ?></h2>
        <input type="url" name="edit_link_base_url" value="<?php echo esc_attr($settings['edit_link_base_url'] ?? ''); ?>" style="width:100%" placeholder="https://example.com/wp-admin" />
        <br><small><?php esc_html_e('Base URL for edit links in email notifications (e.g., https://example.com/wp-admin). If left empty, the system will attempt to use the default WordPress admin URL. This is useful when notifications are sent from background processes or external services that don\'t have proper WordPress context.', 'polytrans'); ?></small><br><br>

        <h2><?php esc_html_e('Logging Options', 'polytrans'); ?></h2>
        <div class="translation-logging-options">
            <?php $enable_db_logging = isset($settings['enable_db_logging']) ? (bool)$settings['enable_db_logging'] : true; ?>
            <p>
                <input type="checkbox" id="enable-db-logging" name="enable_db_logging" value="1" <?php checked($enable_db_logging); ?>>
                <label for="enable-db-logging"><strong><?php esc_html_e('Enable Database Logging', 'polytrans'); ?></strong></label>
            </p>
            <small>
                <?php esc_html_e('When enabled, logs will be stored in the database table. When disabled, logs will only be written to the WordPress error log and post meta. Disabling database logging can improve performance and reduce database size, but makes viewing logs from the admin panel more limited. Warning: Logs from internal processes can only log to database, will never show up in stderr.', 'polytrans'); ?>
            </small>
        </div><br>
    <?php
    }

    /**
     * Render tag settings section
     */
    private function render_tag_settings($source_language, $base_tags)
    {
    ?>
        <h2><?php esc_html_e('Source (Main) Language', 'polytrans'); ?></h2>
        <p><?php esc_html_e('Select the primary language that will appear in the first column of tag translations. This language will be considered the source for tag translations.', 'polytrans'); ?></p>
        <select name="source_language" style="width:100%;max-width:300px;">
            <?php foreach ($this->langs as $i => $lang): ?>
                <option value="<?php echo esc_attr($lang); ?>" <?php selected($source_language, $lang); ?>>
                    <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <h2><?php esc_html_e('Base Tags List', 'polytrans'); ?></h2>
        <p><?php esc_html_e('Enter the tags that you want to manage for translation (one per line or comma separated). These tags will appear in the tag translation table where you can set translations for each language.', 'polytrans'); ?></p>
        <label for="base-tags-textarea"><strong><?php esc_html_e('Tags to translate:', 'polytrans'); ?></strong></label><br />
        <textarea id="base-tags-textarea" name="base_tags" style="width:100%;min-height:150px;font-family:monospace;" placeholder="<?php esc_attr_e('Enter tags separated by new lines or commas...', 'polytrans'); ?>"><?php echo esc_textarea($base_tags); ?></textarea>
        <br><small><?php esc_html_e('These tags will be used for automatic translation and tag mapping between languages. You can add tags that you want to translate now or that you want to use in future automatic translations.', 'polytrans'); ?></small>
<?php
    }

    /**
     * Render Language Paths settings tab
     */
    private function render_language_pairs_settings($settings)
    {
        $assistants_mapping = self::get_assistants_mapping($settings);
        $path_rules = self::get_path_rules($settings);

        ?>
        <h2><?php esc_html_e('Translation Path Rules', 'polytrans'); ?></h2>
        <p class="description">
            <?php esc_html_e('Define routing rules for translations. Use intermediate languages for better quality (e.g., PL → EN → FR instead of direct PL → FR).', 'polytrans'); ?>
        </p>

        <div id="path-rules-container">
            <?php $this->render_path_rules_table($path_rules); ?>
        </div>

        <button type="button" id="add-path-rule" class="button" style="margin-top: 10px;">
            <?php esc_html_e('Add Rule', 'polytrans'); ?>
        </button>

        <hr style="margin: 30px 0;">

        <h2><?php esc_html_e('Provider/Assistant Mapping', 'polytrans'); ?></h2>
        <p class="description">
            <?php esc_html_e('Select which translation provider or assistant to use for each language pair. You can choose from translation providers (like Google Translate) or AI assistants (Managed or Provider API assistants).', 'polytrans'); ?>
        </p>

        <div id="assistants-loading" style="display:none; padding: 10px; background: #f0f0f1; margin: 10px 0;">
            <p><em><?php esc_html_e('Loading providers and assistants...', 'polytrans'); ?></em></p>
        </div>

        <div id="assistants-error" style="display:none; padding: 10px; background: #f8d7da; color: #721c24; margin: 10px 0;">
            <p><?php esc_html_e('Unable to load providers and assistants. Please check your API keys in provider configuration tabs if you want to use AI assistants.', 'polytrans'); ?></p>
        </div>

        <div id="assistants-mapping-container">
            <?php $this->render_assistant_mapping_table($assistants_mapping); ?>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Function to update visual representation of path rule
            function updatePathRuleVisual($row) {
                var source = $row.find('.path-rule-source').val();
                var target = $row.find('.path-rule-target').val();
                var intermediate = $row.find('.path-rule-intermediate').val() || '';
                
                var sourceText = source === 'all' ? '<?php echo esc_js(__('All', 'polytrans')); ?>' : $row.find('.path-rule-source option:selected').text();
                var targetText = target === 'all' ? '<?php echo esc_js(__('All', 'polytrans')); ?>' : $row.find('.path-rule-target option:selected').text();
                var intermediateText = intermediate === '' ? '<?php echo esc_js(__('None (Direct)', 'polytrans')); ?>' : $row.find('.path-rule-intermediate option:selected').text();
                
                var $visual = $row.find('.path-rule-visual');
                if (intermediate === '') {
                    $visual.html('<span class="path-rule-direct">' + sourceText + ' → ' + targetText + '</span>');
                } else {
                    $visual.html(
                        '<span>' + sourceText + '</span>' +
                        '<span class="path-rule-arrow">→</span>' +
                        '<span class="path-rule-intermediate">' + intermediateText + '</span>' +
                        '<span class="path-rule-arrow">→</span>' +
                        '<span>' + targetText + '</span>'
                    );
                }
            }
            
            // Update visual for existing rules on change
            $(document).on('change', '.path-rule-source, .path-rule-target, .path-rule-intermediate', function() {
                var $row = $(this).closest('.path-rule-row');
                updatePathRuleVisual($row);
            });
            
            // Initialize visuals for existing rules
            $('.path-rule-row').each(function() {
                updatePathRuleVisual($(this));
            });
            
            // Add path rule
            $('#add-path-rule').on('click', function() {
                var ruleIndex = $('#path-rules-container tbody tr').length;
                var newRow = `
                    <tr class="path-rule-row">
                        <td>
                            <select name="openai_path_rules[${ruleIndex}][source]" class="path-rule-source path-rule-select" required>
                                <option value="all"><?php esc_html_e('All', 'polytrans'); ?></option>
                                <?php foreach ($this->langs as $i => $lang): ?>
                                <option value="<?php echo esc_attr($lang); ?>">
                                    <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="openai_path_rules[${ruleIndex}][target]" class="path-rule-target path-rule-select" required>
                                <option value="all"><?php esc_html_e('All', 'polytrans'); ?></option>
                                <?php foreach ($this->langs as $i => $lang): ?>
                                <option value="<?php echo esc_attr($lang); ?>">
                                    <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="openai_path_rules[${ruleIndex}][intermediate]" class="path-rule-intermediate path-rule-select">
                                <option value=""><?php esc_html_e('None (Direct)', 'polytrans'); ?></option>
                                <?php foreach ($this->langs as $i => $lang): ?>
                                <option value="<?php echo esc_attr($lang); ?>">
                                    <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="path-rule-visual"></div>
                        </td>
                        <td>
                            <button type="button" class="button remove-rule"><?php esc_html_e('Remove', 'polytrans'); ?></button>
                        </td>
                    </tr>
                `;
                $('#path-rules-container tbody').append(newRow);
                
                // Initialize visual for new row
                var $newRow = $('#path-rules-container tbody tr').last();
                updatePathRuleVisual($newRow);
                
                // Trigger filtering after adding new rule
                if (window.PolyTransLanguagePaths && window.PolyTransLanguagePaths.updateLanguagePairVisibility) {
                    window.PolyTransLanguagePaths.updateLanguagePairVisibility();
                }
            });

            // Remove path rule
            $(document).on('click', '.remove-rule', function() {
                $(this).closest('tr').remove();
                // Trigger filtering after removing rule
                if (window.PolyTransLanguagePaths && window.PolyTransLanguagePaths.updateLanguagePairVisibility) {
                    window.PolyTransLanguagePaths.updateLanguagePairVisibility();
                }
            });

            // Trigger filtering when path rule values change
            $(document).on('change', '.path-rule-source, .path-rule-target, .path-rule-intermediate', function() {
                if (window.PolyTransLanguagePaths && window.PolyTransLanguagePaths.updateLanguagePairVisibility) {
                    window.PolyTransLanguagePaths.updateLanguagePairVisibility();
                }
            });

            // Initial filtering on page load
            if (window.PolyTransLanguagePaths && window.PolyTransLanguagePaths.updateLanguagePairVisibility) {
                window.PolyTransLanguagePaths.updateLanguagePairVisibility();
            }
        });
        </script>
        <?php
    }

    /**
     * Render assistant mapping table
     */
    private function render_assistant_mapping_table($assistants_mapping)
    {
        if (empty($this->langs)) {
            echo '<p><em>' . esc_html__('No languages configured. Please configure languages in Basic Settings first.', 'polytrans') . '</em></p>';
            return;
        }

        $language_pairs = $this->get_language_pairs();

        if (empty($language_pairs)) {
            echo '<p><em>' . esc_html__('No language pairs available.', 'polytrans') . '</em></p>';
            return;
        }

        ?>
        <table class="widefat fixed striped" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 30%;"><?php esc_html_e('Language Pair', 'polytrans'); ?></th>
                    <th style="width: 70%;"><?php esc_html_e('Assistant', 'polytrans'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($language_pairs as $pair): ?>
                    <?php
                    $source_name = $this->get_language_name($pair['source']);
                    $target_name = $this->get_language_name($pair['target']);
                    $assistant_key = $pair['key'];
                    $selected_assistant = $assistants_mapping[$assistant_key] ?? '';
                    ?>
                    <tr class="language-pair-row"
                        data-source="<?php echo esc_attr($pair['source']); ?>"
                        data-target="<?php echo esc_attr($pair['target']); ?>"
                        data-pair="<?php echo esc_attr($assistant_key); ?>">
                        <td>
                            <strong><?php echo esc_html($source_name); ?> → <?php echo esc_html($target_name); ?></strong>
                        </td>
                        <td>
                            <!-- Hidden input to preserve the actual value -->
                            <input type="hidden"
                                name="openai_assistants[<?php echo esc_attr($assistant_key); ?>]"
                                value="<?php echo esc_attr($selected_assistant); ?>"
                                class="openai-assistant-hidden"
                                data-pair="<?php echo esc_attr($assistant_key); ?>" />

                            <!-- Visible select for user interaction -->
                            <select class="openai-assistant-select"
                                data-pair="<?php echo esc_attr($assistant_key); ?>"
                                data-selected="<?php echo esc_attr($selected_assistant); ?>"
                                style="width: 100%; max-width: 500px;">
                                <option value=""><?php esc_html_e('Loading providers/assistants...', 'polytrans'); ?></option>
                            </select>

                            <?php if (!empty($selected_assistant)): ?>
                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                    <?php echo esc_html(sprintf(__('Current: %s', 'polytrans'), $selected_assistant)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render path rules table
     */
    private function render_path_rules_table($rules)
    {
        ?>
        <style>
            .path-rules-container {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 0;
                margin-top: 10px;
            }
            #path-rules-table {
                margin: 0;
                border: none;
            }
            #path-rules-table thead th {
                background: #f6f7f7;
                font-weight: 600;
                padding: 12px 15px;
                border-bottom: 2px solid #c3c4c7;
            }
            #path-rules-table tbody td {
                padding: 15px;
                vertical-align: middle;
            }
            #path-rules-table .path-rule-row {
                border-bottom: 1px solid #e5e5e5;
            }
            #path-rules-table .path-rule-row:last-child {
                border-bottom: none;
            }
            #path-rules-table .path-rule-row:hover {
                background: #f9f9f9;
            }
            .path-rule-select {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 14px;
                background: #fff;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .path-rule-select:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            .path-rule-visual {
                display: inline-block;
                font-size: 14px;
                color: #50575e;
                margin-top: 8px;
                line-height: 1.6;
            }
            .path-rule-visual > span {
                display: inline-block;
                margin: 0 4px;
            }
            .path-rule-arrow {
                color: #2271b1;
                font-weight: bold;
            }
            .path-rule-intermediate {
                color: #d63638;
                font-weight: 600;
            }
            .path-rule-direct {
                color: #00a32a;
                font-style: italic;
            }
            .remove-rule {
                background: #d63638;
                color: #fff;
                border-color: #d63638;
            }
            .remove-rule:hover {
                background: #b32d2e;
                border-color: #b32d2e;
            }
            #add-path-rule {
                margin-top: 15px;
                background: #2271b1;
                color: #fff;
                border-color: #2271b1;
            }
            #add-path-rule:hover {
                background: #135e96;
                border-color: #135e96;
            }
        </style>
        <div class="path-rules-container">
            <table class="widefat fixed" id="path-rules-table">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e('Source Language', 'polytrans'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Target Language', 'polytrans'); ?></th>
                        <th style="width: 35%;"><?php esc_html_e('Intermediate Language', 'polytrans'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Actions', 'polytrans'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rules)): ?>
                        <?php foreach ($rules as $index => $rule): ?>
                            <?php
                            $source_val = $rule['source'] ?? 'all';
                            $target_val = $rule['target'] ?? 'all';
                            $intermediate_val = $rule['intermediate'] ?? '';
                            $source_name = $source_val === 'all' ? __('All', 'polytrans') : ($this->lang_names[array_search($source_val, $this->langs)] ?? strtoupper($source_val));
                            $target_name = $target_val === 'all' ? __('All', 'polytrans') : ($this->lang_names[array_search($target_val, $this->langs)] ?? strtoupper($target_val));
                            $intermediate_name = empty($intermediate_val) ? __('None (Direct)', 'polytrans') : ($this->lang_names[array_search($intermediate_val, $this->langs)] ?? strtoupper($intermediate_val));
                            ?>
                            <tr class="path-rule-row">
                                <td>
                                    <select name="openai_path_rules[<?php echo esc_attr($index); ?>][source]" class="path-rule-source path-rule-select" required>
                                        <option value="all" <?php selected($source_val, 'all'); ?>><?php esc_html_e('All', 'polytrans'); ?></option>
                                        <?php foreach ($this->langs as $i => $lang): ?>
                                            <option value="<?php echo esc_attr($lang); ?>" <?php selected($source_val, $lang); ?>>
                                                <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="openai_path_rules[<?php echo esc_attr($index); ?>][target]" class="path-rule-target path-rule-select" required>
                                        <option value="all" <?php selected($target_val, 'all'); ?>><?php esc_html_e('All', 'polytrans'); ?></option>
                                        <?php foreach ($this->langs as $i => $lang): ?>
                                            <option value="<?php echo esc_attr($lang); ?>" <?php selected($target_val, $lang); ?>>
                                                <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="openai_path_rules[<?php echo esc_attr($index); ?>][intermediate]" class="path-rule-intermediate path-rule-select">
                                        <option value="" <?php selected(empty($intermediate_val)); ?>><?php esc_html_e('None (Direct)', 'polytrans'); ?></option>
                                        <?php foreach ($this->langs as $i => $lang): ?>
                                            <option value="<?php echo esc_attr($lang); ?>" <?php selected($intermediate_val, $lang); ?>>
                                                <?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="path-rule-visual">
                                        <?php if (empty($intermediate_val)): ?>
                                            <span class="path-rule-direct"><?php echo esc_html($source_name); ?> → <?php echo esc_html($target_name); ?></span>
                                        <?php else: ?>
                                            <span><?php echo esc_html($source_name); ?></span>
                                            <span class="path-rule-arrow">→</span>
                                            <span class="path-rule-intermediate"><?php echo esc_html($intermediate_name); ?></span>
                                            <span class="path-rule-arrow">→</span>
                                            <span><?php echo esc_html($target_name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="button remove-rule"><?php esc_html_e('Remove', 'polytrans'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #646970;">
                                <em><?php esc_html_e('No path rules defined. Click "Add Rule" to create one.', 'polytrans'); ?></em>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get language pairs
     */
    private function get_language_pairs()
    {
        $pairs = [];
        foreach ($this->langs as $source) {
            foreach ($this->langs as $target) {
                if ($source !== $target) {
                    $pairs[] = [
                        'source' => $source,
                        'target' => $target,
                        'key' => $source . '_to_' . $target
                    ];
                }
            }
        }
        return $pairs;
    }

    /**
     * Get language name
     */
    private function get_language_name($code)
    {
        $index = array_search($code, $this->langs);
        if ($index !== false && isset($this->lang_names[$index])) {
            return $this->lang_names[$index];
        }
        return strtoupper($code);
    }
    
    /**
     * Check if provider settings should be shown (only if enabled)
     * 
     * @param string $provider_id Provider ID
     * @param \PolyTrans\Providers\SettingsProviderInterface $settings_provider Settings provider instance
     * @param array $settings Current settings
     * @param array $enabled_providers List of enabled provider IDs
     * @return bool True if provider settings should be shown
     */
    private function is_provider_used($provider_id, $settings_provider, $settings, $enabled_providers)
    {
        // Show settings only if provider is enabled
        // If disabled, settings are hidden even if provider is used in paths/assistants
        return in_array($provider_id, $enabled_providers);
    }
    
    /**
     * Render universal provider settings UI based on manifest
     * This is used when provider doesn't implement custom render_settings_ui()
     * 
     * @param string $provider_id Provider ID
     * @param \PolyTrans\Providers\SettingsProviderInterface $settings_provider Settings provider instance
     * @param array $settings Current settings
     */
    private function render_universal_provider_ui($provider_id, $settings_provider, $settings)
    {
        $manifest = $settings_provider->get_provider_manifest($settings);
        $capabilities = $manifest['capabilities'] ?? [];
        $api_key_setting = $manifest['api_key_setting'] ?? null;
        $has_chat_or_assistants = in_array('chat', $capabilities) || in_array('assistants', $capabilities);
        
        // Get API key value if setting key is defined
        $api_key = '';
        if ($api_key_setting) {
            $api_key = $settings[$api_key_setting] ?? '';
        }
        
        // Get default model setting key (provider_id + '_model')
        $model_setting_key = $provider_id . '_model';
        $default_model = $settings[$model_setting_key] ?? '';
        
        ?>
        <div class="universal-provider-config-section" data-provider-id="<?php echo esc_attr($provider_id); ?>">
            <h2><?php echo esc_html($settings_provider->get_tab_label()); ?></h2>
            <?php if ($settings_provider->get_tab_description()): ?>
                <p><?php echo esc_html($settings_provider->get_tab_description()); ?></p>
            <?php endif; ?>
            
            <?php if ($api_key_setting): ?>
                <!-- API Key Section -->
                <div class="provider-api-key-section" style="margin-top:2em;">
                    <h3><?php esc_html_e('API Key', 'polytrans'); ?></h3>
                    <div style="display:flex;gap:0.5em;align-items:center;max-width:600px;">
                        <input type="password"
                               data-provider="<?php echo esc_attr($provider_id); ?>"
                               data-field="api-key"
                               id="<?php echo esc_attr($provider_id); ?>-api-key"
                               name="<?php echo esc_attr($api_key_setting); ?>"
                               value="<?php echo esc_attr($api_key); ?>"
                               style="width:100%"
                               placeholder="<?php esc_attr_e('Enter your API key', 'polytrans'); ?>"
                               autocomplete="off" />
                        <button type="button"
                                data-provider="<?php echo esc_attr($provider_id); ?>"
                                data-action="validate-key"
                                class="button"><?php esc_html_e('Validate', 'polytrans'); ?></button>
                        <button type="button"
                                data-provider="<?php echo esc_attr($provider_id); ?>"
                                data-action="toggle-visibility"
                                class="button">👁</button>
                    </div>
                    <div data-provider="<?php echo esc_attr($provider_id); ?>" data-field="validation-message" style="margin-top:0.5em;"></div>
                    <small><?php 
                        printf(
                            esc_html__('Enter your %s API key. It will be validated before saving.', 'polytrans'),
                            esc_html($settings_provider->get_tab_label())
                        ); 
                    ?></small>
                </div>
            <?php endif; ?>
            
            <?php 
            // Check if provider supports assistants API
            $has_assistants_api = in_array('assistants', $capabilities);
            $has_chat = in_array('chat', $capabilities);
            
            // Show notice if provider has chat but no assistants API
            if ($has_chat && !$has_assistants_api): ?>
                <div class="notice notice-info" style="margin-top:2em;">
                    <p>
                        <strong><?php esc_html_e('Note:', 'polytrans'); ?></strong>
                        <?php 
                        printf(
                            esc_html__('%s does not have a dedicated Assistants API. To use %s for AI-powered translations, please create a "Managed Assistant" in the Assistants menu. Managed Assistants allow you to configure system prompts and user message templates for %s.', 'polytrans'),
                            esc_html($settings_provider->get_tab_label()),
                            esc_html($settings_provider->get_tab_label()),
                            esc_html($settings_provider->get_tab_label())
                        );
                        ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=polytrans-assistants&action=new')); ?>" class="button button-secondary">
                            <?php esc_html_e('Create Managed Assistant', 'polytrans'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($has_chat_or_assistants): ?>
                <!-- Model Selection Section -->
                <div class="provider-model-section" style="margin-top:2em;">
                    <h3><?php esc_html_e('Default Model', 'polytrans'); ?></h3>
                    <div style="display:flex;gap:0.5em;align-items:center;max-width:600px;">
                        <select data-provider="<?php echo esc_attr($provider_id); ?>"
                                data-field="model"
                                data-selected-model="<?php echo esc_attr($default_model); ?>"
                                id="<?php echo esc_attr($provider_id); ?>-model"
                                name="<?php echo esc_attr($model_setting_key); ?>"
                                style="flex:1;">
                            <option value=""><?php esc_html_e('Loading models...', 'polytrans'); ?></option>
                        </select>
                        <button type="button"
                                data-provider="<?php echo esc_attr($provider_id); ?>"
                                data-action="refresh-models"
                                class="button"><?php esc_html_e('Refresh', 'polytrans'); ?></button>
                    </div>
                    <div data-provider="<?php echo esc_attr($provider_id); ?>" data-field="model-message" style="margin-top:0.5em;"></div>
                    <small><?php 
                        printf(
                            esc_html__('Default %s model to use for translations and AI Assistant steps.', 'polytrans'),
                            esc_html($settings_provider->get_tab_label())
                        ); 
                    ?></small>
                </div>
            <?php endif; ?>
            
            <?php
            // Allow providers to add custom fields via filter
            do_action("polytrans_render_provider_settings_{$provider_id}", $settings, $manifest);
            ?>
        </div>
        <?php
    }
}
