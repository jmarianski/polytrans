<?php

/**
 * Translation Notifications Class
 * Handles email notifications for translation workflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Notifications
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
        // Constructor is intentionally empty - methods are called directly from main class
    }

    /**
     * Handle post status transitions for notifications
     */
    public function handle_post_status_transition($new_status, $old_status, $post)
    {
        if ($new_status === 'inherit') {
            // Skip, as it mostly applies to revisions
            return;
        }
        PolyTrans_Logs_Manager::log("transition_post_status: post {$post->ID} from $old_status to $new_status", "info");

        // Only for posts that are translation targets
        if (!get_post_meta($post->ID, 'polytrans_is_translation_target', true)) {
            return;
        }

        // Only when moving from pending/pending_review to publish or draft
        if (!in_array($old_status, ['pending', 'draft']) || !in_array($new_status, ['publish', 'draft', 'future'])) {
            return;
        }

        // Only if not already notified
        if (get_post_meta($post->ID, '_polytrans_reviewed_notified', true)) {
            return;
        }

        // Get language and reviewer for this translation
        $lang = get_post_meta($post->ID, 'polytrans_translation_lang', true);
        $settings = get_option('polytrans_settings', []);
        $reviewer_id = $settings[$lang]['reviewer'] ?? '';
        if (!$reviewer_id) {
            return;
        }

        // Only notify if current user is reviewer
        if (get_current_user_id() != $reviewer_id) {
            return;
        }

        // Get original post and author
        $original_post_id = get_post_meta($post->ID, 'polytrans_translation_source', true);
        $author_id = get_post_field('post_author', $original_post_id);
        $author = get_user_by('id', $author_id);
        if (!$author) {
            PolyTrans_Logs_Manager::log("No author found for original post $original_post_id (translation {$post->ID})", "info");
            return;
        }

        // Prepare email
        $subject = $settings['author_email_title'] ?? 'Your translation is reviewed: {title}';
        $body = $settings['author_email'] ?? 'Your translation has been reviewed: {link}';
        $subject = str_replace('{title}', get_the_title($post->ID), $subject);
        $body = str_replace(['{link}', '{title}'], [get_edit_post_link($post->ID, 'edit'), get_the_title($post->ID)], $body);

        PolyTrans_Logs_Manager::log("Sending review notification to author {$author->user_email} for post {$post->ID} (original $original_post_id)", "info");
        PolyTrans_Logs_Manager::log("Email subject: $subject", "info");
        PolyTrans_Logs_Manager::log("Email body: $body", "info");

        // Send email
        wp_mail($author->user_email, $subject, $body);

        // Mark as notified
        update_post_meta($post->ID, '_polytrans_reviewed_notified', 1);
        PolyTrans_Logs_Manager::log("Marked as notified for post {$post->ID}", "info");
    }

    /**
     * Send reviewer notification email
     */
    public function send_reviewer_notification($post_id, $reviewer_id, $lang)
    {
        $settings = get_option('polytrans_settings', []);
        $reviewer = get_user_by('id', $reviewer_id);

        if (!$reviewer) {
            PolyTrans_Logs_Manager::log("No reviewer found with ID $reviewer_id", "info");
            return false;
        }

        $subject = $settings['reviewer_email_title'] ?? 'Translation ready for review: {title}';
        $body = $settings['reviewer_email'] ?? 'A translation is ready for your review: {link}';
        $subject = str_replace('{title}', get_the_title($post_id), $subject);
        $body = str_replace(['{link}', '{title}'], [get_edit_post_link($post_id, 'edit'), get_the_title($post_id)], $body);

        PolyTrans_Logs_Manager::log("Sending reviewer notification to {$reviewer->user_email} for post $post_id in language $lang", "info");

        return wp_mail($reviewer->user_email, $subject, $body);
    }
}
