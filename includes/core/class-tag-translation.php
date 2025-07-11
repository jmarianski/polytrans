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

        // Load tag list from settings instead of separate option
        $settings = get_option('polytrans_settings', []);
        $tag_list_raw = $settings['base_tags'] ?? '';
        $source_language = $settings['source_language'] ?? 'pl';
        $tag_names = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $tag_list_raw)));

        // Get tag objects by name, ensure all tags from the list are present
        $tags = [];
        if (!empty($tag_names)) {
            foreach ($tag_names as $tag_name) {
                $tag = $this->get_term_by_name_and_lang($tag_name, $source_language);
                if (!$tag) {
                    // Create the source language tag if it doesn't exist
                    $new_tag = wp_insert_term($tag_name, 'post_tag', [
                        'slug' => sanitize_title($tag_name) . '-' . $source_language
                    ]);
                    if (!is_wp_error($new_tag)) {
                        $tag = get_term($new_tag['term_id']);
                        // Set language to source language if Polylang is available
                        if (function_exists('pll_set_term_language')) {
                            pll_set_term_language($tag->term_id, $source_language);
                        }
                    } else {
                        // tag exists, we need to ensure it's in the correct language
                        $tag = get_term_by('name', $tag_name, 'post_tag');
                        if (function_exists('pll_set_term_language')) {
                            pll_set_term_language($tag->term_id, $source_language);
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
                <p><?php esc_html_e('This view lets you manage translations for the tags specified in the Translation Settings. You can set translations for each language, and use the import/export functionality to manage translations in bulk.', 'polytrans-translation'); ?></p>
                <p><strong><?php esc_html_e('Note:', 'polytrans-translation'); ?></strong> <?php printf(esc_html__('To add or remove tags from the translation list, please go to %s.', 'polytrans-translation'), '<a href="' . admin_url('admin.php?page=polytrans') . '">' . esc_html__('Translation Settings → Tag Settings', 'polytrans-translation') . '</a>'); ?></p>
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
                        <th><?php
                            $source_lang_name = '';
                            if (function_exists('pll_languages_list')) {
                                $lang_names = pll_languages_list(['fields' => 'name']);
                            } else {
                                $lang_names = ['Polish', 'English', 'Italian'];
                            }
                            foreach ($langs as $i => $lang) {
                                if ($lang === $source_language) {
                                    $source_lang_name = $lang_names[$i] ?? strtoupper($lang);
                                    break;
                                }
                            }
                            echo esc_html($source_lang_name . ' ' . __('Tag', 'polytrans-translation'));
                            ?></th>
                        <?php foreach ($langs as $lang): ?>
                            <?php if ($lang === $source_language) continue; ?>
                            <th><?php echo esc_html(strtoupper($lang)) . ' ' . esc_html__('Translation', 'polytrans-translation'); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?php echo esc_html($tag->name); ?></td>
                            <?php foreach ($langs as $lang): ?>
                                <?php if ($lang === $source_language) continue; ?>
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

        // Get languages and settings
        if (function_exists('pll_languages_list')) {
            $langs = pll_languages_list(['fields' => 'slug']);
        } else {
            $langs = ['pl', 'en', 'it'];
        }

        $settings = get_option('polytrans_settings', []);
        $source_language = $settings['source_language'] ?? 'pl';


        // Get tag list from settings
        $tag_list_raw = $settings['base_tags'] ?? '';
        $tag_names = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $tag_list_raw)));

        if (empty($tag_names)) {
            error_log('Export CSV: No tags found');
            wp_die('No tags found to export', 400);
        }

        error_log('Export CSV: Found ' . count($tag_names) . ' tags');

        $csv_data = [];
        $csv_data[] = array_merge([strtoupper($source_language)], array_map('strtoupper', array_filter($langs, function ($lang) use ($source_language) {
            return $lang !== $source_language;
        })));

        foreach ($tag_names as $tag_name) {
            $tag = get_term_by('name', $tag_name, 'post_tag');
            if ($tag) {
                $row = [$tag->name];
                foreach ($langs as $lang) {
                    if ($lang === $source_language) continue;
                    $translated_term_id = function_exists('pll_get_term') ? pll_get_term($tag->term_id, $lang) : null;
                    $translation = $translated_term_id ? get_term($translated_term_id) : null;
                    $row[] = $translation ? $translation->name : '';
                }
                $csv_data[] = $row;
            }
        }

        // Output CSV
        error_log('Export CSV: Outputting CSV with ' . count($csv_data) . ' rows');
        header('Content-Type: text/csv; charset=utf-8');
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

        $csv_content =  $_POST['csv'] ?? '';
        if (empty($csv_content)) {
            wp_send_json_error(['message' => __('No CSV data provided.', 'polytrans-translation')]);
        }

        // Get source language from settings
        $settings = get_option('polytrans_settings', []);
        $source_language = $settings['source_language'] ?? 'pl';

        // Use a more robust CSV parsing that handles newlines inside cells
        $temp_data = str_replace(["\r\n", "\r"], "\n", $csv_content);
        $temp_lines = explode("\n", $temp_data);

        foreach ($temp_lines as $i => $line) {
            $temp_lines[$i] = str_getcsv($line, ",", '"', "\\");
        }


        $csv_data = $temp_lines;

        if (empty($csv_data) || count($csv_data) < 2) {
            wp_send_json_error(['message' => __('Invalid CSV format or no data rows.', 'polytrans-translation')]);
        }

        $header = $csv_data[0];
        $imported_count = 0;

        for ($i = 1; $i < count($csv_data); $i++) {
            $row = $csv_data[$i];
            if (empty($row[0])) continue;

            $source_tag_name = trim(stripslashes($row[0]), " \t\n\r\0\x0B\"");
            error_log("Processing tag: " . $source_tag_name);
            $tag = $this->get_term_by_name_and_lang($source_tag_name, $source_language);

            if (!$tag) {
                $new_tag = wp_insert_term($source_tag_name, 'post_tag', [
                    'slug' => sanitize_title($source_tag_name) . '-' . $source_language
                ]);
                if (is_wp_error($new_tag)) continue;
                $tag = get_term($new_tag['term_id']);
                if (function_exists('pll_set_term_language')) {
                    pll_set_term_language($tag->term_id, $source_language);
                }
            }

            // Process translations
            for ($j = 1; $j < count($header) && $j < count($row); $j++) {
                $lang = strtolower(trim($header[$j]));
                $translation_name = str_replace('"', '', stripslashes($row[$j]));


                error_log("Processing translation: header='" . $header[$j] . "', lang='$lang', translation='$translation_name'");

                // Skip if empty translation or if this is the source language column (contains " tag" at the end)
                if (empty($translation_name) || preg_match('/\s+tag\s*$/i', $header[$j])) {
                    error_log("Skipping: empty or source column");
                    continue;
                }

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

    private function get_term_by_name_and_lang(string $tag_name, $lang = 'pl')
    {
        $terms = get_terms(array(
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
            'name'       => $tag_name,
            'lang'       => $lang
        ));
        return !empty($terms) ? $terms[0] : null;
    }
}
