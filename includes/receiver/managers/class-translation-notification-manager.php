<?php

/**
 * Handles email notifications for translated posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Notification_Manager
{
    /**
     * Handles notifications for translation completion.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     * @param array $translated Translated content data
     * @return string Final post status
     */
    public function handle_notifications($new_post_id, $original_post_id, $target_language, array $translated)
    {
        $settings = get_option('polytrans_settings', []);
        $lang_settings = isset($settings[$target_language]) ? $settings[$target_language] : [];
        $needs_review = get_post_meta($original_post_id, '_polytrans_translation_needs_review_' . $target_language, true);

        if ($needs_review === '1' && !empty($lang_settings['reviewer'])) {
            $this->send_reviewer_notification($new_post_id, $target_language, $lang_settings['reviewer']);
            return 'pending_review';
        }

        return 'completed';
    }

    /**
     * Sends notification email to the reviewer.
     * 
     * @param int $new_post_id New translated post ID
     * @param string $target_language Target language code
     * @param string $reviewer_user_login Reviewer username
     */
    private function send_reviewer_notification($new_post_id, $target_language, $reviewer_user_login)
    {
        $reviewer = get_user_by('login', $reviewer_user_login);
        if (!$reviewer) {
            PolyTrans_Logs_Manager::log("[polytrans] Reviewer not found: $reviewer_user_login", "info");
            return;
        }

        $post = get_post($new_post_id);
        if (!$post) {
            PolyTrans_Logs_Manager::log("[polytrans] Post not found for notification: $new_post_id", "info");
            return;
        }

        $settings = get_option('polytrans_settings', []);
        $email_subject = isset($settings['reviewer_email_title']) ?
            $settings['reviewer_email_title'] : 'Translation Review Required';

        $email_body = isset($settings['reviewer_email']) ?
            $settings['reviewer_email'] : 'A translation is ready for review.';

        // Replace placeholders
        $placeholders = [
            '{post_title}' => $post->post_title,
            '{language}' => $target_language,
            '{edit_link}' => get_edit_post_link($new_post_id),
            '{reviewer_name}' => $reviewer->display_name
        ];

        $email_subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_subject);
        $email_body = str_replace(array_keys($placeholders), array_values($placeholders), $email_body);

        $sent = wp_mail($reviewer->user_email, $email_subject, $email_body);

        if ($sent) {
            PolyTrans_Logs_Manager::log("[polytrans] Sent reviewer notification to {$reviewer->user_email} for post $new_post_id", "info");
        } else {
            PolyTrans_Logs_Manager::log("[polytrans] Failed to send reviewer notification for post $new_post_id", "info");
        }
    }

    /**
     * Sends notification to the original post author when translation is published.
     * 
     * @param int $new_post_id New translated post ID
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language code
     */
    public function send_author_notification($new_post_id, $original_post_id, $target_language)
    {
        $original_post = get_post($original_post_id);
        $author = get_user_by('ID', $original_post->post_author);

        if (!$author) {
            return;
        }

        $settings = get_option('polytrans_settings', []);
        $email_subject = isset($settings['author_email_title']) ?
            $settings['author_email_title'] : 'Translation Published';

        $email_body = isset($settings['author_email']) ?
            $settings['author_email'] : 'Your translation has been published.';

        $post = get_post($new_post_id);
        $placeholders = [
            '{post_title}' => $post->post_title,
            '{language}' => $target_language,
            '{view_link}' => get_permalink($new_post_id),
            '{author_name}' => $author->display_name
        ];

        $email_subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_subject);
        $email_body = str_replace(array_keys($placeholders), array_values($placeholders), $email_body);

        wp_mail($author->user_email, $email_subject, $email_body);
    }
}
