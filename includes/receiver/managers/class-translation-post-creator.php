<?php

/**
 * Handles creation of translated WordPress posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Post_Creator
{
    /**
     * Creates a new WordPress post with the translated content.
     * 
     * @param array $translated Translated content data
     * @param int $original_post_id Original post ID
     * @return int|WP_Error New post ID or WP_Error on failure
     */
    public function create_post(array $translated, $original_post_id)
    {
        $original_post_type = get_post_type($original_post_id);
        if (!$original_post_type) {
            return new WP_Error('invalid_post_type', 'Could not determine original post type');
        }

        $postarr = [
            'post_title'   => $this->sanitize_title(isset($translated['title']) ? $translated['title'] : ''),
            'post_content' => $this->sanitize_content(isset($translated['content']) ? $translated['content'] : ''),
            'post_excerpt' => $this->sanitize_excerpt(isset($translated['excerpt']) ? $translated['excerpt'] : ''),
            'post_status'  => 'pending', // Will be updated later based on settings
            'post_type'    => $original_post_type,
        ];

        $new_post_id = wp_insert_post($postarr);

        if (is_wp_error($new_post_id)) {
            PolyTrans_Logs_Manager::log('[polytrans] Failed to create translated post: ' . $new_post_id->get_error_message(), "error");
            return $new_post_id;
        }

        return $new_post_id;
    }

    /**
     * Sanitizes the post title.
     * 
     * @param string $title Raw title
     * @return string Sanitized title
     */
    private function sanitize_title($title)
    {
        return sanitize_text_field($title);
    }

    /**
     * Sanitizes the post content.
     * 
     * @param string $content Raw content
     * @return string Sanitized content
     */
    private function sanitize_content($content)
    {
        // WordPress will handle content sanitization during wp_insert_post
        return $content;
    }

    /**
     * Sanitizes the post excerpt.
     * 
     * @param string $excerpt Raw excerpt
     * @return string Sanitized excerpt
     */
    private function sanitize_excerpt($excerpt)
    {
        return sanitize_textarea_field($excerpt);
    }
}
