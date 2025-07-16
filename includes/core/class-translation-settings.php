<?php

/**
 * Translation Settings Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class polytrans_settings
{

    private $langs;
    private $lang_names;
    private $statuses;

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

        $registry = PolyTrans_Provider_Registry::get_instance();

        $settings['translation_provider'] = sanitize_text_field(wp_unslash($_POST['translation_provider'] ?? 'google'));
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
        $selected_provider = $registry->get_provider($settings['translation_provider']);
        if ($selected_provider) {
            $settings_provider_class = $selected_provider->get_settings_provider_class();
            if ($settings_provider_class && class_exists($settings_provider_class)) {
                $settings_provider = new $settings_provider_class();
                $provider_settings = $settings_provider->validate_settings($_POST);
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

        update_option('polytrans_settings', $settings);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'polytrans') . '</p></div>';
    }

    /**
     * Output the settings page HTML
     */
    private function output_page($settings)
    {
        $registry = PolyTrans_Provider_Registry::get_instance();
        $translation_provider = $settings['translation_provider'] ?? 'google';
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
        $providers = $registry->get_providers();
        $settings_providers = [];
        foreach ($providers as $provider_id => $provider) {
            $settings_provider_class = $provider->get_settings_provider_class();
            if ($settings_provider_class && class_exists($settings_provider_class)) {
                $settings_providers[$provider_id] = new $settings_provider_class();
                // Enqueue assets for the selected provider
                if ($provider_id === $translation_provider) {
                    $settings_providers[$provider_id]->enqueue_assets();
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
                <a href="#tag-settings" class="nav-tab" id="tag-tab"><?php esc_html_e('Tag Settings', 'polytrans'); ?></a>
                <a href="#email-settings" class="nav-tab" id="email-tab"><?php esc_html_e('Email Settings', 'polytrans'); ?></a>
                <?php foreach ($settings_providers as $provider_id => $settings_provider): ?>
                    <a href="#<?php echo esc_attr($provider_id); ?>-settings" class="nav-tab provider-settings-tab" id="<?php echo esc_attr($provider_id); ?>-tab" style="<?php echo ($translation_provider !== $provider_id) ? 'display:none;' : ''; ?>">
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
                        <h2><?php esc_html_e('Translation Provider', 'polytrans'); ?></h2>
                        <p><?php esc_html_e('Choose which translation service to use for automatic translations.', 'polytrans'); ?></p>
                        <div style="margin-bottom:2em;">
                            <?php foreach ($providers as $provider_id => $provider): ?>
                                <label style="display:block;margin-bottom:0.5em;">
                                    <input type="radio" name="translation_provider" value="<?php echo esc_attr($provider_id); ?>" <?php checked($translation_provider, $provider_id); ?> class="provider-selection-radio">
                                    <strong><?php echo esc_html($provider->get_name()); ?></strong>&nbsp;
                                    <span style="color:#666;"><?php echo esc_html($provider->get_description()); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
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
                        <?php $settings_provider->render_settings_ui($settings, $this->langs, $this->lang_names); ?>
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
                    $('.provider-selection-radio').on('change', function() {
                        var selectedProvider = $(this).val();

                        // Hide all provider settings tabs and content
                        $('.provider-settings-tab').hide();
                        $('.provider-settings-content').hide();

                        // Show the tab and content for the selected provider (if it exists)
                        var providerTab = $('#' + selectedProvider + '-tab');

                        if (providerTab.length) {
                            providerTab.show();
                        }
                    });

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
    ?>
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
                <strong><?php esc_html_e('Internal:', 'polytrans'); ?></strong> <?php esc_html_e('Uses local endpoints via the site URL, bypassing the need for external services. Data will be sent to self via the site\'s WordPress REST API.', 'polytrans'); ?>
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
                <?php esc_html_e('When enabled, logs will be stored in the database table. When disabled, logs will only be written to the WordPress error log and post meta. Disabling database logging can improve performance and reduce database size, but makes viewing logs from the admin panel more limited.', 'polytrans'); ?>
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
}
