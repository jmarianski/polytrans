<?php

/**
 * Handles metadata setup for translated posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Metadata_Manager
{
    /**
     * Sets up metadata for the translated post, including translation markers and copied meta.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $source_language Source language code
     * @param array $translated Translated content including meta
     */
    public function setup_metadata($new_post_id, $original_post_id, $source_language, array $translated)
    {
        $this->set_translation_markers($new_post_id, $original_post_id, $source_language);
        $this->copy_original_metadata($new_post_id, $original_post_id, $translated);
    }

    /**
     * Sets translation marker metadata on the new post.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $source_language Source language code
     */
    private function set_translation_markers($new_post_id, $original_post_id, $source_language)
    {
        update_post_meta($new_post_id, 'translated_by_machine', "true");
        update_post_meta($new_post_id, 'translated_by_human', "false");
        update_post_meta($new_post_id, 'polytrans_is_translation_target', 1);
        update_post_meta($new_post_id, 'polytrans_translation_source', $original_post_id);
        update_post_meta($new_post_id, 'polytrans_translation_lang', $source_language);

        PolyTrans_Logs_Manager::log("Set translation markers for post $new_post_id (source: $original_post_id, lang: $source_language)", "info");
    }

    /**
     * Copies and updates metadata from original post to translated post.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param array $translated Translated content including meta
     */
    private function copy_original_metadata($new_post_id, $original_post_id, array $translated)
    {
        $original_meta = get_post_meta($original_post_id);

        foreach ($original_meta as $key => $value) {
            // Skip translation status meta to avoid conflicts
            if ($this->should_skip_meta_key($key)) {
                continue;
            }

            if (isset($translated['meta'][$key])) {
                // Use translated value for specific keys (like rank_math)
                update_post_meta($new_post_id, $key, $translated['meta'][$key]);
            } else {
                // Use original value for other keys
                $meta_value = $this->process_meta_value($value);
                update_post_meta($new_post_id, $key, $meta_value);
            }
        }
    }

    /**
     * Determines if a meta key should be skipped during copying.
     * 
     * @param string $key Meta key
     * @return bool True if should skip
     */
    private function should_skip_meta_key($key)
    {
        $skip_keys = [
            '_polytrans_translation_status',
            '_edit_lock',
            '_edit_last',
        ];

        return in_array($key, $skip_keys, true);
    }

    /**
     * Processes meta value to handle serialized data and arrays properly.
     * 
     * @param mixed $value Meta value to process
     * @return mixed Processed meta value
     */
    private function process_meta_value($value)
    {
        if (is_array($value) && count($value) === 1) {
            $value = $value[0];
        }

        if (is_string($value) && @unserialize($value) !== false) {
            $value = unserialize($value);
        }

        return $value;
    }
}
