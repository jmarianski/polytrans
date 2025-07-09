<?php

/**
 * Tag Translation Class
 * Handles tag translation management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Tag_Translation
{

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_polytrans_save_tag_list', [$this, 'ajax_save_tag_list']);
        add_action('wp_ajax_polytrans_save_tag_translation', [$this, 'ajax_save_tag_translation']);
        add_action('wp_ajax_polytrans_search_tags', [$this, 'ajax_search_tags']);
        add_action('wp_ajax_polytrans_export_tag_csv', [$this, 'ajax_export_tag_csv']);
        add_action('wp_ajax_polytrans_import_tag_csv', [$this, 'ajax_import_tag_csv']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'edit.php', // Parent: Posts
            __('Tag Translations', 'polytrans-translation'),
            __('Tag Translations', 'polytrans-translation'),
            'manage_options',
            'polytrans-tag-translation',
            [$this, 'admin_page'],
            11
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'posts_page_polytrans-tag-translation') return;

        $plugin_url = POLYTRANS_PLUGIN_URL;
        wp_enqueue_script('polytrans-tag-translation', $plugin_url . 'assets/js/core/tag-translation-admin.js', ['jquery'], POLYTRANS_VERSION, true);
        wp_enqueue_style('polytrans-tag-translation', $plugin_url . 'assets/css/core/tag-translation-admin.css', [], POLYTRANS_VERSION);

        wp_localize_script('polytrans-tag-translation', 'PolyTransTagTranslation', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polytrans_tag_translation'),
        ]);
    }

    /**
     * Admin page callback
     */
    public function admin_page()
    {
        // Get languages (Polylang or fallback)
        if (function_exists('pll_languages_list')) {
            $langs = pll_languages_list(['fields' => 'slug']);
        } else {
            $langs = ['pl', 'en', 'it'];
        }

        // Load tag list from option
        $tag_list_raw = get_option('polytrans_tag_translation_list', '');
        $tag_names = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $tag_list_raw)));

        // Get tag objects by name, ensure all tags from the list are present
        $tags = [];
        if (!empty($tag_names)) {
            foreach ($tag_names as $tag_name) {
                $tag = get_term_by('name', $tag_name, 'post_tag');
                if (!$tag) {
                    // Create the Polish tag if it doesn't exist
                    $new_tag = wp_insert_term($tag_name, 'post_tag');
                    if (!is_wp_error($new_tag)) {
                        $tag = get_term($new_tag['term_id']);
                        // Set language to Polish if Polylang is available
                        if (function_exists('pll_set_term_language')) {
                            pll_set_term_language($tag->term_id, 'pl');
                        }
                    }
                }
                if ($tag) $tags[] = $tag;
            }
        }

?>
        <div class="wrap">
            <h1><?php esc_html_e('Tag Translations', 'polytrans-translation'); ?></h1>

            <div class="tag-translation-admin-desc">
                <p><?php esc_html_e('This view lets you manage the list of tags that are used for automatic translation and tag mapping between languages. The tags you enter below are not only those you want to translate now, but also those you want to use in automatic translations across the site.', 'polytrans-translation'); ?></p>
            </div>

            <!-- Tag list textarea -->
            <button id="toggle-tag-list" class="button" style="margin-bottom:1em;"><?php esc_html_e('Show/Hide Tag List', 'polytrans-translation'); ?></button>
            <div id="tag-list-area" style="display:none;">
                <label for="tag-list-textarea"><strong><?php esc_html_e('Tags to translate (one per line or comma separated):', 'polytrans-translation'); ?></strong></label><br />
                <textarea id="tag-list-textarea"><?php echo esc_textarea($tag_list_raw); ?></textarea>
                <button id="save-tag-list" class="button button-secondary"><?php esc_html_e('Save Tag List', 'polytrans-translation'); ?></button>
                <span id="tag-list-saved"><?php esc_html_e('Saved!', 'polytrans-translation'); ?></span>
            </div>

            <!-- Export/Import controls -->
            <div>
                <button id="export-tag-csv" class="button button-primary"><?php esc_html_e('Export CSV', 'polytrans-translation'); ?></button>
                <button id="show-import-csv" class="button"><?php esc_html_e('Import CSV', 'polytrans-translation'); ?></button>
                <span id="import-csv-area">
                    <input type="file" id="import-csv-file" accept=".csv,text/csv" />
                    <button id="import-csv-submit" class="button button-secondary"><?php esc_html_e('Import', 'polytrans-translation'); ?></button>
                    <button id="import-csv-cancel" class="button"><?php esc_html_e('Cancel', 'polytrans-translation'); ?></button>
                </span>
            </div>

            <table class="widefat fixed striped" id="tag-translation-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Polish Tag', 'polytrans-translation'); ?></th>
                        <?php foreach ($langs as $lang): ?>
                            <?php if ($lang === 'pl') continue; ?>
                            <th><?php echo esc_html(strtoupper($lang)) . ' ' . esc_html__('Translation', 'polytrans-translation'); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?php echo esc_html($tag->name); ?></td>
                            <?php foreach ($langs as $lang): ?>
                                <?php if ($lang === 'pl') continue; ?>
                                <?php
                                $translated_term_id = function_exists('pll_get_term') ? pll_get_term($tag->term_id, $lang) : null;
                                $translation = $translated_term_id ? get_term($translated_term_id) : null;
                                $translation_name = $translation ? $translation->name : '';
                                $translation_id = $translation ? $translation->term_id : '';
                                ?>
                                <td>
                                    <input style="width:100%"
                                        type="text"
                                        data-tag="<?php echo esc_attr($tag->term_id); ?>"
                                        data-lang="<?php echo esc_attr($lang); ?>"
                                        data-translation-id="<?php echo esc_attr($translation_id); ?>"
                                        value="<?php echo esc_attr($translation_name); ?>"
                                        class="tag-translation-input"
                                        placeholder="<?php esc_attr_e('Enter or select tag...', 'polytrans-translation'); ?>" />
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php
    }

    /**
     * AJAX handler for saving tag list
     */
    public function ajax_save_tag_list()
    {
        check_ajax_referer('polytrans_tag_translation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        $tag_list = sanitize_textarea_field($_POST['tag_list'] ?? '');
        update_option('polytrans_tag_translation_list', $tag_list);

        wp_send_json_success(['message' => __('Tag list saved successfully.', 'polytrans-translation')]);
    }

    /**
     * AJAX handler for saving tag translation
     */
    public function ajax_save_tag_translation()
    {
        check_ajax_referer('polytrans_tag_translation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        $tag_id = intval($_POST['tag_id'] ?? 0);
        $lang = sanitize_text_field($_POST['lang'] ?? '');
        $translation_name = sanitize_text_field($_POST['value'] ?? '');

        if (!$tag_id || !$lang) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'polytrans-translation')]);
        }

        // Handle translation logic based on whether Polylang is available
        if (function_exists('pll_set_term_language') && function_exists('pll_save_term_translations')) {
            // Create or update translation using Polylang
            if (empty($translation_name)) {
                // Remove translation
                $translations = pll_get_term_translations($tag_id);
                unset($translations[$lang]);
                pll_save_term_translations($translations);
            } else {
                // Create/update translation
                $existing_term = get_term_by('name', $translation_name, 'post_tag');
                if ($existing_term) {
                    $translation_id = $existing_term->term_id;
                } else {
                    $new_term = wp_insert_term($translation_name, 'post_tag');
                    if (is_wp_error($new_term)) {
                        wp_send_json_error(['message' => $new_term->get_error_message()]);
                    }
                    $translation_id = $new_term['term_id'];
                }

                pll_set_term_language($translation_id, $lang);
                $translations = pll_get_term_translations($tag_id);
                $translations[$lang] = $translation_id;
                pll_save_term_translations($translations);
            }
        } else {
            // Fallback: just create the term if it doesn't exist
            if (!empty($translation_name)) {
                $existing_term = get_term_by('name', $translation_name, 'post_tag');
                if (!$existing_term) {
                    wp_insert_term($translation_name, 'post_tag');
                }
            }
        }

        wp_send_json_success(['message' => __('Translation saved successfully.', 'polytrans-translation')]);
    }

    /**
     * AJAX handler for searching tags
     */
    public function ajax_search_tags()
    {
        check_ajax_referer('polytrans_tag_translation', 'nonce');

        $search = sanitize_text_field($_POST['search'] ?? '');
        $lang = sanitize_text_field($_POST['lang'] ?? '');

        if (strlen($search) < 2) {
            wp_send_json_success(['tags' => []]);
        }

        $args = [
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'search' => $search,
            'number' => 20,
        ];

        $terms = get_terms($args);
        $results = [];

        foreach ($terms as $term) {
            $results[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }

        wp_send_json_success(['tags' => $results]);
    }

    /**
     * AJAX handler for exporting tag translations as CSV
     */
    public function ajax_export_tag_csv()
    {
        error_log('Export CSV called');
        
        // Check if user is logged in and has permissions
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            error_log('Export CSV: Unauthorized');
            wp_die('Unauthorized', 403);
        }

        // Verify nonce if provided
        if (isset($_GET['nonce']) && !wp_verify_nonce($_GET['nonce'], 'polytrans_tag_translation')) {
            error_log('Export CSV: Invalid nonce');
            wp_die('Invalid nonce', 403);
        }

        // Get languages
        if (function_exists('pll_languages_list')) {
            $langs = pll_languages_list(['fields' => 'slug']);
        } else {
            $langs = ['pl', 'en', 'it'];
        }

        // Get tag list
        $tag_list_raw = get_option('polytrans_tag_translation_list', '');
        $tag_names = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $tag_list_raw)));

        if (empty($tag_names)) {
            error_log('Export CSV: No tags found');
            wp_die('No tags found to export', 400);
        }

        error_log('Export CSV: Found ' . count($tag_names) . ' tags');

        $csv_data = [];
        $csv_data[] = array_merge(['Polish Tag'], array_map('strtoupper', array_filter($langs, function($lang) { return $lang !== 'pl'; })));

        foreach ($tag_names as $tag_name) {
            $tag = get_term_by('name', $tag_name, 'post_tag');
            if ($tag) {
                $row = [$tag->name];
                foreach ($langs as $lang) {
                    if ($lang === 'pl') continue;
                    $translated_term_id = function_exists('pll_get_term') ? pll_get_term($tag->term_id, $lang) : null;
                    $translation = $translated_term_id ? get_term($translated_term_id) : null;
                    $row[] = $translation ? $translation->name : '';
                }
                $csv_data[] = $row;
            }
        }

        // Output CSV
        error_log('Export CSV: Outputting CSV with ' . count($csv_data) . ' rows');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tag-translations.csv"');
        $output = fopen('php://output', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    /**
     * AJAX handler for importing tag translations from CSV
     */
    public function ajax_import_tag_csv()
    {
        check_ajax_referer('polytrans_tag_translation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        $csv_content = sanitize_textarea_field($_POST['csv'] ?? '');
        if (empty($csv_content)) {
            wp_send_json_error(['message' => __('No CSV data provided.', 'polytrans-translation')]);
        }

        $lines = str_getcsv($csv_content, "\n");
        if (empty($lines)) {
            wp_send_json_error(['message' => __('Invalid CSV format.', 'polytrans-translation')]);
        }

        $header = str_getcsv($lines[0]);
        $imported_count = 0;

        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            if (empty($row[0])) continue;

            $polish_tag_name = trim($row[0]);
            $tag = get_term_by('name', $polish_tag_name, 'post_tag');
            
            if (!$tag) {
                $new_tag = wp_insert_term($polish_tag_name, 'post_tag');
                if (is_wp_error($new_tag)) continue;
                $tag = get_term($new_tag['term_id']);
                if (function_exists('pll_set_term_language')) {
                    pll_set_term_language($tag->term_id, 'pl');
                }
            }

            // Process translations
            for ($j = 1; $j < count($header) && $j < count($row); $j++) {
                $lang = strtolower(trim($header[$j]));
                $translation_name = trim($row[$j]);
                
                if (empty($translation_name) || $lang === 'polish tag') continue;

                // Create or update translation
                if (function_exists('pll_set_term_language') && function_exists('pll_save_term_translations')) {
                    $existing_term = get_term_by('name', $translation_name, 'post_tag');
                    if ($existing_term) {
                        $translation_id = $existing_term->term_id;
                    } else {
                        $new_term = wp_insert_term($translation_name, 'post_tag');
                        if (is_wp_error($new_term)) continue;
                        $translation_id = $new_term['term_id'];
                    }

                    pll_set_term_language($translation_id, $lang);
                    $translations = pll_get_term_translations($tag->term_id);
                    $translations[$lang] = $translation_id;
                    pll_save_term_translations($translations);
                }
            }
            $imported_count++;
        }

        wp_send_json_success(['message' => sprintf(__('%d tag translations imported successfully.', 'polytrans-translation'), $imported_count)]);
    }
}
