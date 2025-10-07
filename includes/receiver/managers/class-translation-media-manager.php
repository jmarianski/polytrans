<?php

/**
 * Handles featured image translation and media attachment management.
 * Creates translated versions of featured images with proper Polylang relationships.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Media_Manager
{
    /**
     * Sets up the featured image for the translated post.
     * Creates a translated media attachment if needed and links it properly.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     * @param array|null $translated_image_data Translated featured image metadata
     */
    public function setup_featured_image($new_post_id, $original_post_id, $target_language, $translated_image_data)
    {
        // If no featured image data, nothing to do
        if (!$translated_image_data || !isset($translated_image_data['id'])) {
            PolyTrans_Logs_Manager::log("No featured image data to process", "debug", [
                'source' => 'translation_media_manager',
                'original_post_id' => $original_post_id,
                'translated_post_id' => $new_post_id
            ]);
            return;
        }

        $original_attachment_id = $translated_image_data['id'];

        // Check if original attachment exists
        if (!get_post($original_attachment_id)) {
            PolyTrans_Logs_Manager::log("Original attachment not found", "warning", [
                'source' => 'translation_media_manager',
                'attachment_id' => $original_attachment_id,
                'translated_post_id' => $new_post_id
            ]);
            return;
        }

        PolyTrans_Logs_Manager::log("Processing featured image translation", "info", [
            'source' => 'translation_media_manager',
            'original_attachment_id' => $original_attachment_id,
            'original_post_id' => $original_post_id,
            'translated_post_id' => $new_post_id,
            'target_language' => $target_language
        ]);

        // Get or create the translated attachment
        $translated_attachment_id = $this->get_or_create_translated_attachment(
            $original_attachment_id,
            $target_language,
            $translated_image_data
        );

        if (!$translated_attachment_id) {
            PolyTrans_Logs_Manager::log("Failed to create/get translated attachment", "error", [
                'source' => 'translation_media_manager',
                'original_attachment_id' => $original_attachment_id,
                'target_language' => $target_language
            ]);
            return;
        }

        // Set the translated attachment as the featured image for the translated post
        set_post_thumbnail($new_post_id, $translated_attachment_id);

        PolyTrans_Logs_Manager::log("Featured image translation completed successfully", "info", [
            'source' => 'translation_media_manager',
            'original_attachment_id' => $original_attachment_id,
            'translated_attachment_id' => $translated_attachment_id,
            'translated_post_id' => $new_post_id
        ]);
    }

    /**
     * Gets existing translated attachment or creates a new one.
     * 
     * @param int $original_attachment_id Original attachment ID
     * @param string $target_language Target language code
     * @param array $translated_data Translated image metadata
     * @return int|null Translated attachment ID or null on failure
     */
    private function get_or_create_translated_attachment($original_attachment_id, $target_language, $translated_data)
    {
        // Check if Polylang is available
        if (!function_exists('pll_get_post')) {
            PolyTrans_Logs_Manager::log("Polylang not available, cannot create media translation", "warning", [
                'source' => 'translation_media_manager'
            ]);
            // Fallback: just use the original attachment
            return $original_attachment_id;
        }

        // Check if a translation already exists
        $existing_translation = pll_get_post($original_attachment_id, $target_language);

        if ($existing_translation) {
            PolyTrans_Logs_Manager::log("Found existing attachment translation", "info", [
                'source' => 'translation_media_manager',
                'original_attachment_id' => $original_attachment_id,
                'existing_translation_id' => $existing_translation,
                'target_language' => $target_language
            ]);

            // Update the existing translation with new metadata
            $this->update_attachment_metadata($existing_translation, $translated_data);
            return $existing_translation;
        }

        // Create a new translated attachment
        return $this->create_translated_attachment($original_attachment_id, $target_language, $translated_data);
    }

    /**
     * Creates a new translated attachment as a duplicate of the original.
     * 
     * @param int $original_attachment_id Original attachment ID
     * @param string $target_language Target language code
     * @param array $translated_data Translated image metadata
     * @return int|null New attachment ID or null on failure
     */
    private function create_translated_attachment($original_attachment_id, $target_language, $translated_data)
    {
        $original_attachment = get_post($original_attachment_id);

        if (!$original_attachment) {
            return null;
        }

        // Get the original file path
        $original_file = get_attached_file($original_attachment_id);

        if (!$original_file || !file_exists($original_file)) {
            PolyTrans_Logs_Manager::log("Original attachment file not found", "error", [
                'source' => 'translation_media_manager',
                'attachment_id' => $original_attachment_id,
                'file_path' => $original_file
            ]);
            return null;
        }

        // Get the upload directory
        $upload_dir = wp_upload_dir();

        // Create a new filename with language suffix
        $file_info = pathinfo($original_file);
        $new_filename = $file_info['filename'] . '-' . $target_language . '.' . $file_info['extension'];
        $new_file = $upload_dir['path'] . '/' . $new_filename;

        // Copy the file (only if it doesn't already exist)
        if (!file_exists($new_file)) {
            if (!copy($original_file, $new_file)) {
                PolyTrans_Logs_Manager::log("Failed to copy attachment file", "error", [
                    'source' => 'translation_media_manager',
                    'original_file' => $original_file,
                    'new_file' => $new_file
                ]);
                return null;
            }
        }

        // Prepare the attachment data with translated metadata
        $attachment_data = [
            'post_mime_type' => $original_attachment->post_mime_type,
            'post_title'     => $translated_data['title'] ?? $original_attachment->post_title,
            'post_content'   => $translated_data['description'] ?? $original_attachment->post_content,
            'post_excerpt'   => $translated_data['caption'] ?? $original_attachment->post_excerpt,
            'post_status'    => 'inherit',
            'post_parent'    => 0 // Will be set when attached to a post
        ];

        // Insert the attachment
        $new_attachment_id = wp_insert_attachment($attachment_data, $new_file);

        if (is_wp_error($new_attachment_id)) {
            PolyTrans_Logs_Manager::log("Failed to insert attachment", "error", [
                'source' => 'translation_media_manager',
                'error' => $new_attachment_id->get_error_message()
            ]);
            return null;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($new_attachment_id, $new_file);
        wp_update_attachment_metadata($new_attachment_id, $attach_data);

        // Update alt text if provided
        if (!empty($translated_data['alt'])) {
            update_post_meta($new_attachment_id, '_wp_attachment_image_alt', $translated_data['alt']);
        }

        // Set the language for the new attachment
        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($new_attachment_id, $target_language);
        }

        // Link the attachments as translations
        if (function_exists('pll_save_post_translations')) {
            $translations = function_exists('pll_get_post_translations') ?
                pll_get_post_translations($original_attachment_id) : [];

            $translations[$target_language] = $new_attachment_id;
            pll_save_post_translations($translations);
        }

        PolyTrans_Logs_Manager::log("Created new translated attachment", "info", [
            'source' => 'translation_media_manager',
            'original_attachment_id' => $original_attachment_id,
            'new_attachment_id' => $new_attachment_id,
            'target_language' => $target_language,
            'file' => $new_filename
        ]);

        return $new_attachment_id;
    }

    /**
     * Updates the metadata of an existing attachment.
     * 
     * @param int $attachment_id Attachment ID to update
     * @param array $translated_data Translated metadata
     */
    private function update_attachment_metadata($attachment_id, $translated_data)
    {
        // Prepare update data
        $update_data = [
            'ID' => $attachment_id
        ];

        if (!empty($translated_data['title'])) {
            $update_data['post_title'] = $translated_data['title'];
        }

        if (!empty($translated_data['caption'])) {
            $update_data['post_excerpt'] = $translated_data['caption'];
        }

        if (!empty($translated_data['description'])) {
            $update_data['post_content'] = $translated_data['description'];
        }

        // Update the attachment post
        if (count($update_data) > 1) { // More than just ID
            wp_update_post($update_data);
        }

        // Update alt text
        if (!empty($translated_data['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $translated_data['alt']);
        }

        PolyTrans_Logs_Manager::log("Updated translated attachment metadata", "info", [
            'source' => 'translation_media_manager',
            'attachment_id' => $attachment_id,
            'updated_fields' => array_keys($translated_data)
        ]);
    }
}
