<?php

/**
 * Language Pairs Menu
 * Manages assistant mapping and translation path rules
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Language_Pairs_Menu
{
    /**
     * Initialize the menu
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu()
    {
        add_submenu_page(
            'polytrans',
            __('Language Pairs', 'polytrans'),
            __('Language Pairs', 'polytrans'),
            'manage_options',
            'polytrans-language-pairs',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets($hook)
    {
        if ($hook !== 'polytrans_page_polytrans-language-pairs') {
            return;
        }

        // Reuse OpenAI integration JS for assistant loading
        wp_enqueue_script(
            'polytrans-openai-integration',
            POLYTRANS_PLUGIN_URL . 'assets/js/translator/openai-integration.js',
            ['jquery'],
            POLYTRANS_VERSION,
            true
        );

        wp_localize_script('polytrans-openai-integration', 'polytransOpenAI', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polytrans_openai_nonce'),
        ]);
    }

    /**
     * Render the page
     */
    public static function render_page()
    {
        $settings = get_option('polytrans_settings', []);
        $openai_assistants = $settings['openai_assistants'] ?? [];
        $openai_path_rules = $settings['openai_path_rules'] ?? [];
        $languages = $settings['languages'] ?? [];
        $language_names = $settings['language_names'] ?? [];

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Language Pairs & Translation Routes', 'polytrans'); ?></h1>
            <p class="description">
                <?php esc_html_e('Configure which assistants to use for each language pair and define translation routing rules.', 'polytrans'); ?>
            </p>

            <form method="post" action="options.php">
                <?php
                settings_fields('polytrans_settings');
                ?>

                <input type="hidden" name="polytrans_settings[languages]" value="<?php echo esc_attr(wp_json_encode($languages)); ?>">
                <input type="hidden" name="polytrans_settings[language_names]" value="<?php echo esc_attr(wp_json_encode($language_names)); ?>">

                <!-- Assistant Mapping Section -->
                <div class="postbox" style="margin-top: 20px;">
                    <div class="postbox-header">
                        <h2><?php esc_html_e('Assistant Mapping', 'polytrans'); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Select which assistant (Managed or OpenAI API) to use for each language pair.', 'polytrans'); ?>
                        </p>

                        <div id="assistants-loading" style="display:none; padding: 10px; background: #f0f0f1; margin: 10px 0;">
                            <p><em><?php esc_html_e('Loading assistants...', 'polytrans'); ?></em></p>
                        </div>

                        <div id="assistants-error" style="display:none; padding: 10px; background: #f8d7da; color: #721c24; margin: 10px 0;">
                            <p><?php esc_html_e('Unable to load assistants. Please check your OpenAI API key in Settings.', 'polytrans'); ?></p>
                        </div>

                        <div id="assistants-mapping-container">
                            <?php self::render_assistant_mapping_table($languages, $language_names, $openai_assistants); ?>
                        </div>
                    </div>
                </div>

                <!-- Translation Path Rules Section -->
                <div class="postbox" style="margin-top: 20px;">
                    <div class="postbox-header">
                        <h2><?php esc_html_e('Translation Path Rules', 'polytrans'); ?></h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Define routing rules for translations. Use intermediate languages for better quality (e.g., PL → EN → FR instead of direct PL → FR).', 'polytrans'); ?>
                        </p>

                        <div id="path-rules-container">
                            <?php self::render_path_rules_table($openai_path_rules, $languages, $language_names); ?>
                        </div>

                        <button type="button" id="add-path-rule" class="button">
                            <?php esc_html_e('Add Rule', 'polytrans'); ?>
                        </button>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Initialize OpenAI integration for assistant loading
            if (typeof OpenAIManager !== 'undefined') {
                OpenAIManager.init();
            }

            // Add path rule
            $('#add-path-rule').on('click', function() {
                var ruleIndex = $('#path-rules-container tbody tr').length;
                var newRow = `
                    <tr>
                        <td>
                            <select name="openai_path_rules[${ruleIndex}][source]" required>
                                <option value="all"><?php esc_html_e('All', 'polytrans'); ?></option>
                                <?php foreach ($languages as $i => $lang): ?>
                                <option value="<?php echo esc_attr($lang); ?>">
                                    <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="openai_path_rules[${ruleIndex}][target]" required>
                                <option value="all"><?php esc_html_e('All', 'polytrans'); ?></option>
                                <?php foreach ($languages as $i => $lang): ?>
                                <option value="<?php echo esc_attr($lang); ?>">
                                    <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="openai_path_rules[${ruleIndex}][intermediate]">
                                <option value=""><?php esc_html_e('None (Direct)', 'polytrans'); ?></option>
                                <?php foreach ($languages as $i => $lang): ?>
                                <option value="<?php echo esc_attr($lang); ?>">
                                    <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button remove-rule"><?php esc_html_e('Remove', 'polytrans'); ?></button>
                        </td>
                    </tr>
                `;
                $('#path-rules-container tbody').append(newRow);
            });

            // Remove path rule
            $(document).on('click', '.remove-rule', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Render assistant mapping table
     */
    private static function render_assistant_mapping_table($languages, $language_names, $openai_assistants)
    {
        if (empty($languages)) {
            echo '<p><em>' . esc_html__('No languages configured. Please configure languages in Settings first.', 'polytrans') . '</em></p>';
            return;
        }

        $language_pairs = self::get_language_pairs($languages);

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
                    $source_name = self::get_language_name($pair['source'], $languages, $language_names);
                    $target_name = self::get_language_name($pair['target'], $languages, $language_names);
                    $assistant_key = $pair['key'];
                    $selected_assistant = $openai_assistants[$assistant_key] ?? '';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($source_name); ?> → <?php echo esc_html($target_name); ?></strong>
                        </td>
                        <td>
                            <!-- Hidden input to preserve the actual value -->
                            <input type="hidden"
                                name="polytrans_settings[openai_assistants][<?php echo esc_attr($assistant_key); ?>]"
                                value="<?php echo esc_attr($selected_assistant); ?>"
                                class="openai-assistant-hidden"
                                data-pair="<?php echo esc_attr($assistant_key); ?>" />

                            <!-- Visible select for user interaction -->
                            <select class="openai-assistant-select"
                                data-pair="<?php echo esc_attr($assistant_key); ?>"
                                data-selected="<?php echo esc_attr($selected_assistant); ?>"
                                style="width: 100%; max-width: 500px;">
                                <option value=""><?php esc_html_e('Loading assistants...', 'polytrans'); ?></option>
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
    private static function render_path_rules_table($rules, $languages, $language_names)
    {
        ?>
        <table class="widefat fixed striped" id="path-rules-table" style="margin-top: 10px;">
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
                        <tr>
                            <td>
                                <select name="openai_path_rules[<?php echo esc_attr($index); ?>][source]" required>
                                    <option value="all" <?php selected($rule['source'], 'all'); ?>><?php esc_html_e('All', 'polytrans'); ?></option>
                                    <?php foreach ($languages as $i => $lang): ?>
                                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($rule['source'], $lang); ?>>
                                            <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="openai_path_rules[<?php echo esc_attr($index); ?>][target]" required>
                                    <option value="all" <?php selected($rule['target'], 'all'); ?>><?php esc_html_e('All', 'polytrans'); ?></option>
                                    <?php foreach ($languages as $i => $lang): ?>
                                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($rule['target'], $lang); ?>>
                                            <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="openai_path_rules[<?php echo esc_attr($index); ?>][intermediate]">
                                    <option value="" <?php selected(empty($rule['intermediate'])); ?>><?php esc_html_e('None (Direct)', 'polytrans'); ?></option>
                                    <?php foreach ($languages as $i => $lang): ?>
                                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($rule['intermediate'] ?? '', $lang); ?>>
                                            <?php echo esc_html($language_names[$i] ?? strtoupper($lang)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button remove-rule"><?php esc_html_e('Remove', 'polytrans'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4"><em><?php esc_html_e('No path rules defined. Click "Add Rule" to create one.', 'polytrans'); ?></em></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get language pairs
     */
    private static function get_language_pairs($languages)
    {
        $pairs = [];
        foreach ($languages as $source) {
            foreach ($languages as $target) {
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
    private static function get_language_name($code, $languages, $language_names)
    {
        $index = array_search($code, $languages);
        if ($index !== false && isset($language_names[$index])) {
            return $language_names[$index];
        }
        return strtoupper($code);
    }
}

