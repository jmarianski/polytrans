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
        if ($new_status === 'inherit' || $new_status === $old_status) {
            // Skip, as it mostly applies to revisions or new things
            return;
        }

        // Only if not already notified
        if (get_post_meta($post->ID, '_polytrans_author_notified', true)) {
            // already notofied, not sending anything
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

        // Get language and reviewer for this translation
        $lang = get_post_meta($post->ID, 'polytrans_translation_lang', true);
        $settings = get_option('polytrans_settings', []);
        $reviewer_id = $settings[$lang]['reviewer'] ?? '';
        if (!$reviewer_id) {
            return;
        }

        // Only notify if current user is reviewer
        if (get_current_user_id() != $reviewer_id) {
            PolyTrans_Logs_Manager::log("Not notifying, current user is not the reviewer for post {$post->ID}", "info");
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

        $placeholders = $this->get_post_placeholders($post, $lang);

        $email_subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $email_body = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        PolyTrans_Logs_Manager::log("Sending review notification to author {$author->user_email} for post {$post->ID} (original $original_post_id)", "info");

        // Send email
        wp_mail($author->user_email, $email_subject, $email_body);

        // Mark as notified
        update_post_meta($post->ID, '_polytrans_author_notified', 1);
        PolyTrans_Logs_Manager::log("Marked as notified for post {$post->ID}", "info");
    }


    private function get_post_placeholders($post, $target_language)
    {
        $settings = get_option('polytrans_settings', []);
        $edit_link = $this->get_edit_link($post->ID, $settings);

        return [
            '{title}' => $post->post_title,
            '{language}' => $target_language,
            '{link}' => $edit_link,
            '{edit_link}' => $edit_link,
            '{author_name}' => get_the_author_meta('display_name', $post->post_author)
        ];
    }
    /**
     * Generate edit link for a post, using custom base URL if configured
     * 
     * @param int $post_id Post ID
     * @param array $settings Plugin settings
     * @return string Edit link URL
     */
    private function get_edit_link($post_id, $settings)
    {
        $edit_link_base_url = $settings['edit_link_base_url'] ?? '';

        if (!empty($edit_link_base_url)) {
            // Use custom base URL for edit links
            $edit_link = rtrim($edit_link_base_url, '/') . '/post.php?post=' . $post_id . '&action=edit';
            PolyTrans_Logs_Manager::log("Using custom edit link base URL: $edit_link", "info", [
                'post_id' => $post_id,
                'base_url' => $edit_link_base_url
            ]);
            return $edit_link;
        } else {
            // Fall back to WordPress default (may not work in background processes)
            $edit_link = get_edit_post_link($post_id, 'edit');
            if (!$edit_link) {
                // If get_edit_post_link fails (background context), construct manually
                $edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
                PolyTrans_Logs_Manager::log("get_edit_post_link failed, using admin_url fallback: $edit_link", "info", [
                    'post_id' => $post_id
                ]);
            } else {
                PolyTrans_Logs_Manager::log("Using WordPress generated edit link: $edit_link", "info", [
                    'post_id' => $post_id
                ]);
            }
            return $edit_link;
        }
    }
}
