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
    public function handle_notifications($new_post_id, $original_post_id, $target_language)
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

            return $edit_link;
        } else {
            // Fall back to WordPress default (may not work in background processes)
            $edit_link = get_edit_post_link($post_id);
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

    /**
     * Sends notification email to the reviewer.
     * 
     * @param int $new_post_id New translated post ID
     * @param string $target_language Target language code
     * @param string $reviewer_user_id Reviewer id
     */
    private function send_reviewer_notification($new_post_id, $target_language, $reviewer_user_id)
    {
        $reviewer = get_user_by('id', $reviewer_user_id);
        if (!$reviewer) {
            PolyTrans_Logs_Manager::log("Reviewer not found: $reviewer_user_id", "info");
            return;
        }

        $post = get_post($new_post_id);
        if (!$post) {
            PolyTrans_Logs_Manager::log("Post not found for notification: $new_post_id", "info");
            return;
        }

        $settings = get_option('polytrans_settings', []);
        $email_subject = isset($settings['reviewer_email_title']) ?
            $settings['reviewer_email_title'] : 'Translation Review Required';

        $email_body = isset($settings['reviewer_email']) ?
            $settings['reviewer_email'] : 'A translation is ready for review.';

        // Replace placeholders
        $placeholders = $this->get_post_placeholders($post, $target_language);

        $email_subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_subject);
        $email_body = str_replace(array_keys($placeholders), array_values($placeholders), $email_body);

        $sent = wp_mail(
            $reviewer->user_email,
            $email_subject,
            $email_body,
            ['Content-Type: text/html; charset=UTF-8']
        );

        if ($sent) {
            PolyTrans_Logs_Manager::log("Sent reviewer notification to {$reviewer->user_email} for post $new_post_id", "info");
        } else {
            PolyTrans_Logs_Manager::log("Failed to send reviewer notification for post $new_post_id", "warning", [
                'post_id' => $new_post_id,
                'reviewer_email' => $reviewer->user_email,
                'error' => isset($GLOBALS['phpmailer']) && $GLOBALS['phpmailer']->ErrorInfo ? $GLOBALS['phpmailer']->ErrorInfo : 'Unknown error'
            ]);
        }
    }
}
