<?php

/**
 * Translation Scheduler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Scheduler
{

    private static $instance = null;
    private $settings;
    private $langs;
    private $lang_names;

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
        $this->settings = get_option('polytrans_settings', []);
        $this->langs = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : ['pl', 'en', 'it'];
        $this->lang_names = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'name']) : ['Polish', 'English', 'Italian'];
    }

    /**
     * Render scheduler meta box
     */
    public function render($post)
    {
        error_log('[polytrans] Rendering translation scheduler meta box for post ' . $post->ID);
        $current_lang = function_exists('pll_get_post_language') ? pll_get_post_language($post->ID) : ($this->langs[0] ?? 'pl');
        $allowed_sources = $this->settings['allowed_sources'] ?? $this->langs;
        $allowed_targets = $this->settings['allowed_targets'] ?? $this->langs;
        $reviewer = $this->settings[$current_lang]['reviewer'] ?? 'none';

        // Use new per-language meta structure
        $langs_key = '_polytrans_translation_langs';
        $scheduled_langs = get_post_meta($post->ID, $langs_key, true);
        if (!is_array($scheduled_langs)) $scheduled_langs = [];

        // Block translation if this post is already a translation target
        $is_translation_target = get_post_meta($post->ID, 'polytrans_is_translation_target', true);
        if ($is_translation_target) {
            $original_id = get_post_meta($post->ID, 'polytrans_translation_source', true);
            $original_url = $original_id ? get_edit_post_link($original_id, 'edit') : '';
            echo '<div><p>';
            echo esc_html__('This post is already a translation. Original post', 'polytrans');
            if ($original_url) {
                echo ' <a href="' . esc_url($original_url) . '" target="_blank">' . esc_html__('here', 'polytrans') . '</a>';
            }
            echo '</p></div>';
            return;
        }
?>
        <div id="translation-scheduler-box">
            <div class="polytrans-controls">
                <label for="polytrans-scope"><strong><?php esc_html_e('Translation Scope', 'polytrans'); ?></strong></label><br>
                <select name="polytrans-scope" id="polytrans-scope" style="width:100%">
                    <option value="local"><?php esc_html_e('Local (no translation)', 'polytrans'); ?></option>
                    <option value="regional" <?php disabled(!in_array($current_lang, $allowed_sources)); ?>><?php esc_html_e('Regional', 'polytrans'); ?></option>
                    <option value="global" <?php disabled(!in_array($current_lang, $allowed_sources)); ?>><?php esc_html_e('Global', 'polytrans'); ?></option>
                </select>
                <div id="polytrans-scheduler-options" style="margin-top:1em;display:none;">
                    <div id="polytrans-target-langs-row" style="display:none;">
                        <label for="polytrans-target-langs"><strong><?php esc_html_e('Target Languages', 'polytrans'); ?></strong></label><br>
                        <select name="polytrans-target-langs[]" id="polytrans-target-langs" multiple style="width:100%">
                            <?php foreach ($this->langs as $i => $lang):
                                if ($lang === $current_lang || !in_array($lang, $allowed_targets)) continue; ?>
                                <option value="<?php echo esc_attr($lang); ?>"><?php echo esc_html($this->lang_names[$i] ?? strtoupper($lang)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small><?php esc_html_e('Hold Ctrl (Windows/Linux) or Cmd (Mac) to select multiple languages.', 'polytrans'); ?></small>
                    </div>
                    <div style="margin-top:1em;">
                        <label><input type="checkbox" id="polytrans-needs-review" name="polytrans-needs-review" <?php checked($reviewer !== 'none'); ?>> <?php esc_html_e('Needs review', 'polytrans'); ?></label>
                    </div>
                </div>
                <button type="button" class="button button-primary" id="polytrans-translate-btn" style="margin-top:1em;width:100%"><?php esc_html_e('Translate', 'polytrans'); ?></button>
            </div>
            <div id="polytrans-translate-status" style="margin-top:0.5em;">
                <ul id="polytrans-merged-list">
                    <?php foreach ($this->langs as $i => $lang):
                        if (!in_array($lang, $allowed_targets)) continue;
                        $lang_name = $this->lang_names[$i] ?? strtoupper($lang);
                        $is_scheduled = in_array($lang, $scheduled_langs, true);
                        $status_key = '_polytrans_translation_status_' . $lang;
                        $current_status = get_post_meta($post->ID, $status_key, true);
                        $is_started = $current_status === 'started';
                        $is_finished = $current_status === 'finished';

                        // Get edit URL for finished translations
                        $target_post_id_key = '_polytrans_translation_target_' . $lang;
                        $edit_post_id = get_post_meta($post->ID, $target_post_id_key, true);
                        $edit_url = $is_finished && $edit_post_id ? esc_url(str_replace('__ID__', $edit_post_id, admin_url('post.php?post=__ID__&action=edit'))) : '#';
                    ?>
                        <li id="polytrans-merged-<?php echo esc_attr($lang); ?>" style="display:<?php echo ($is_scheduled && ($is_started || $is_finished)) ? 'flex' : 'none'; ?>;margin-bottom:0.5em;align-items:center;">
                            <span class="polytrans-loader" style="<?php echo $is_started ? '' : 'display:none;'; ?>">
                                <span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span>
                            </span>
                            <span class="polytrans-check" style="<?php echo $is_finished ? '' : 'display:none;'; ?>">
                                <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            </span>
                            <span class="polytrans-merged-label">
                                <strong><?php echo esc_html($lang_name); ?></strong>
                                <small></small>
                            </span>
                            <a href="<?php echo $edit_url; ?>" class="polytrans-edit-btn" target="_blank" style="<?php echo $is_finished ? '' : 'display:none;'; ?>" title="<?php esc_attr_e('Edit translation', 'polytrans'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </a>
                            <button type="button" class="polytrans-clear-translation" data-lang="<?php echo esc_attr($lang); ?>" data-post-id="<?php echo esc_attr($post->ID); ?>" title="<?php esc_attr_e('Clear this translation', 'polytrans'); ?>">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
        // Enqueue styles for translation scheduler
        wp_enqueue_style(
            'polytrans-scheduler',
            plugin_dir_url(__FILE__) . '../../assets/css/scheduler/translation-scheduler.css',
            array(),
            '1.0.0'
        );
        ?>
<?php
    }
}
