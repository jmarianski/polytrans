<?php

/**
 * Handles language setup and status configuration for translated posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Language_Manager
{
    /**
     * Sets up language assignment and status for the translated post.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     */
    public function setup_language_and_status($new_post_id, $original_post_id, $target_language)
    {
        $this->assign_language($new_post_id, $target_language);
        $this->setup_translation_relationship($new_post_id, $original_post_id, $target_language);
        $this->configure_post_status($new_post_id, $target_language);
    }

    /**
     * Assigns the target language to the new post using Polylang.
     * 
     * @param int $new_post_id New translated post ID
     * @param string $target_language Target language code
     */
    private function assign_language($new_post_id, $target_language)
    {
        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($new_post_id, $target_language);
            PolyTrans_Logs_Manager::log("[polytrans] Set language for post $new_post_id to $target_language", "info");
        } else {
            PolyTrans_Logs_Manager::log("[polytrans] Polylang not available, skipping language assignment for post $new_post_id", "info");
        }
    }

    /**
     * Sets up translation relationships between original and translated posts.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     */
    private function setup_translation_relationship($new_post_id, $original_post_id, $target_language)
    {
        if (function_exists('pll_save_post_translations')) {
            // Get existing translations for the original post
            $translations = function_exists('pll_get_post_translations') ?
                pll_get_post_translations($original_post_id) : [];

            // Add the new translation
            $translations[$target_language] = $new_post_id;

            // Save the translation relationships
            pll_save_post_translations($translations);
            PolyTrans_Logs_Manager::log("[polytrans] Set up translation relationship: post $new_post_id as $target_language translation of $original_post_id", "info");
        }

        // Also update our internal tracking
        update_post_meta($original_post_id, '_polytrans_translation_target_' . $target_language, $new_post_id);
    }

    /**
     * Configures the post status based on translation settings.
     * 
     * @param int $new_post_id New translated post ID
     * @param string $target_language Target language code
     */
    private function configure_post_status($new_post_id, $target_language)
    {
        $settings = get_option('polytrans_settings', []);
        $lang_settings = isset($settings[$target_language]) ? $settings[$target_language] : [];
        $desired_status = isset($lang_settings['status']) ? $lang_settings['status'] : 'draft';

        // Handle "same as source" status
        if ($desired_status === 'source') {
            $original_post_id = get_post_meta($new_post_id, 'polytrans_translation_source', true);
            if ($original_post_id) {
                $original_post = get_post($original_post_id);
                if ($original_post) {
                    $desired_status = $original_post->post_status;
                }
            }
        }

        // Update the post status
        wp_update_post([
            'ID' => $new_post_id,
            'post_status' => $desired_status
        ]);

        PolyTrans_Logs_Manager::log("[polytrans] Set post status for $new_post_id to $desired_status (language: $target_language)", "info");
    }
}
