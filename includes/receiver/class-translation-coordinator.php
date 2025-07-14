<?php

/**
 * Coordinates the translation process by orchestrating all translation managers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Coordinator
{
    private $validator;
    private $post_creator;
    private $metadata_manager;
    private $taxonomy_manager;
    private $language_manager;
    private $notification_manager;
    private $status_manager;

    public function __construct()
    {
        $this->validator = new PolyTrans_Translation_Request_Validator();
        $this->post_creator = new PolyTrans_Translation_Post_Creator();
        $this->metadata_manager = new PolyTrans_Translation_Metadata_Manager();
        $this->taxonomy_manager = new PolyTrans_Translation_Taxonomy_Manager();
        $this->language_manager = new PolyTrans_Translation_Language_Manager();
        $this->notification_manager = new PolyTrans_Translation_Notification_Manager();
        $this->status_manager = new PolyTrans_Translation_Status_Manager();
    }

    /**
     * Processes a complete translation request.
     * 
     * @param array $params Translation request parameters
     * @return array Result with success status and data
     */
    public function process_translation(array $params)
    {
        try {
            // Validate the request
            $validation_result = $this->validator->validate($params);
            if (is_wp_error($validation_result)) {
                return [
                    'success' => false,
                    'error' => $validation_result->get_error_message(),
                    'code' => $validation_result->get_error_code()
                ];
            }

            $translated = $params['translated'];
            $source_language = $params['source_language'];
            $target_language = $params['target_language'];
            $original_post_id = $params['original_post_id'];

            PolyTrans_Logs_Manager::log("[polytrans] Starting translation processing for post $original_post_id: $source_language -> $target_language", "info");

            // Create the translated post
            $new_post_id = $this->post_creator->create_post($translated, $original_post_id);
            if (is_wp_error($new_post_id)) {
                $this->status_manager->mark_as_failed($original_post_id, $target_language, $new_post_id->get_error_message());
                return [
                    'success' => false,
                    'error' => 'Could not create translated post: ' . $new_post_id->get_error_message()
                ];
            }

            PolyTrans_Logs_Manager::log("[polytrans] Created NEW translated post $new_post_id from original $original_post_id", "info");

            // Setup all post properties and relationships
            $this->setup_translated_post($new_post_id, $original_post_id, $source_language, $target_language, $translated);

            // Handle notifications and get final status
            $final_status = $this->notification_manager->handle_notifications(
                $new_post_id,
                $original_post_id,
                $target_language
            );

            // Update translation status
            $this->status_manager->update_status($original_post_id, $target_language, $new_post_id);

            PolyTrans_Logs_Manager::log("[polytrans] Translation processing completed successfully for post $new_post_id", "info");

            return [
                'success' => true,
                'created_post_id' => $new_post_id,
                'status' => $final_status
            ];
        } catch (Exception $e) {
            $error_message = 'Translation processing failed: ' . $e->getMessage();
            PolyTrans_Logs_Manager::log("[polytrans] $error_message", "info");

            if (isset($original_post_id) && isset($target_language)) {
                $this->status_manager->mark_as_failed($original_post_id, $target_language, $error_message);
            }

            return [
                'success' => false,
                'error' => $error_message
            ];
        }
    }

    /**
     * Sets up all properties for the translated post.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $source_language Source language code
     * @param string $target_language Target language code
     * @param array $translated Translated content data
     */
    private function setup_translated_post(
        $new_post_id,
        $original_post_id,
        $source_language,
        $target_language,
        $translated
    ) {
        // Setup metadata (translation markers and copied meta)
        $this->metadata_manager->setup_metadata($new_post_id, $original_post_id, $source_language, $translated);

        // Setup taxonomies (categories and tags)
        $this->taxonomy_manager->setup_taxonomies($new_post_id, $original_post_id, $target_language);

        // Setup language and status
        $this->language_manager->setup_language_and_status($new_post_id, $original_post_id, $target_language);
    }

    /**
     * Gets the status manager for external access.
     * 
     * @return PolyTrans_Translation_Status_Manager
     */
    public function get_status_manager()
    {
        return $this->status_manager;
    }
}
