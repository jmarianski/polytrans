<?php

/**
 * Handles translation status tracking and updates.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Status_Manager
{
    /**
     * Updates the translation status to completed.
     * 
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     * @param int $new_post_id New translated post ID
     */
    public function update_status($original_post_id, $target_language, $new_post_id)
    {
        $status_key = '_polytrans_translation_status_' . $target_language;
        $log_key = '_polytrans_translation_log_' . $target_language;
        $langs_key = '_polytrans_translation_langs';

        // Ensure the language is in the scheduled languages list
        $scheduled_langs = get_post_meta($original_post_id, $langs_key, true);
        if (!is_array($scheduled_langs)) $scheduled_langs = [];
        if (!in_array($target_language, $scheduled_langs, true)) {
            $scheduled_langs[] = $target_language;
            update_post_meta($original_post_id, $langs_key, $scheduled_langs);
            error_log("[polytrans] Added $target_language to scheduled languages for post $original_post_id");
        }

        // Update status to finished (matches JavaScript expectation)
        update_post_meta($original_post_id, $status_key, 'finished');

        // Add completion log entry
        $log = get_post_meta($original_post_id, $log_key, true);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'timestamp' => time(),
            'msg' => sprintf(__('Translation completed. Post ID: %d', 'polytrans'), $new_post_id)
        ];

        update_post_meta($original_post_id, $log_key, $log);

        // Store the translated post ID reference (both keys for compatibility)
        update_post_meta($original_post_id, '_polytrans_translation_target_' . $target_language, $new_post_id);
        update_post_meta($original_post_id, '_polytrans_translation_post_id_' . $target_language, $new_post_id);

        error_log("[polytrans] Updated translation status for post $original_post_id -> $new_post_id (language: $target_language)");
    }

    /**
     * Marks a translation as failed.
     * 
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     * @param string $error_message Error message
     */
    public function mark_as_failed($original_post_id, $target_language, $error_message)
    {
        $status_key = '_polytrans_translation_status_' . $target_language;
        $log_key = '_polytrans_translation_log_' . $target_language;

        // Update status to failed
        update_post_meta($original_post_id, $status_key, 'failed');

        // Add failure log entry
        $log = get_post_meta($original_post_id, $log_key, true);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'timestamp' => time(),
            'msg' => sprintf(__('Translation failed: %s', 'polytrans'), $error_message)
        ];

        update_post_meta($original_post_id, $log_key, $log);

        error_log("[polytrans] Marked translation as failed for post $original_post_id (language: $target_language): $error_message");
    }

    /**
     * Gets the current translation status for a post and language.
     * 
     * @param int $post_id Post ID
     * @param string $language Language code
     * @return string Translation status
     */
    public function get_status($post_id, $language)
    {
        $status_key = '_polytrans_translation_status_' . $language;
        return get_post_meta($post_id, $status_key, true) ?: 'not_started';
    }

    /**
     * Gets the translation log for a post and language.
     * 
     * @param int $post_id Post ID
     * @param string $language Language code
     * @return array Translation log entries
     */
    public function get_log($post_id, $language)
    {
        $log_key = '_polytrans_translation_log_' . $language;
        $log = get_post_meta($post_id, $log_key, true);
        return is_array($log) ? $log : [];
    }

    /**
     * Clears translation status and logs for a post and language.
     * 
     * @param int $post_id Post ID
     * @param string $language Language code
     */
    public function clear_status($post_id, $language)
    {
        $status_key = '_polytrans_translation_status_' . $language;
        $log_key = '_polytrans_translation_log_' . $language;
        $target_key = '_polytrans_translation_target_' . $language;
        $post_id_key = '_polytrans_translation_post_id_' . $language;
        $review_key = '_polytrans_translation_needs_review_' . $language;

        delete_post_meta($post_id, $status_key);
        delete_post_meta($post_id, $log_key);
        delete_post_meta($post_id, $target_key);
        delete_post_meta($post_id, $post_id_key);
        delete_post_meta($post_id, $review_key);

        error_log("[polytrans] Cleared translation status for post $post_id (language: $language)");
    }
}
