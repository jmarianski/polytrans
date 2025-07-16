<?php

/**
 * Post Autocomplete Class
 * Handles post search functionality for workflow testing
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Post_Autocomplete
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
        add_action('wp_ajax_polytrans_search_posts', [$this, 'ajax_search_posts']);
    }

    /**
     * AJAX handler for post search
     */
    public function ajax_search_posts()
    {
        check_ajax_referer('polytrans_workflows_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'any');

        if (strlen($search) < 2) {
            wp_send_json_success(['posts' => []]);
        }

        $args = [
            's' => $search,
            'post_type' => $post_type === 'any' ? ['post', 'page'] : [$post_type],
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 20,
            'orderby' => 'relevance',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_polytrans_original_post_id',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_polytrans_original_post_id',
                    'value' => '',
                    'compare' => '='
                ]
            ]
        ];

        $posts = get_posts($args);

        $results = [];
        foreach ($posts as $post) {
            // Get post meta for additional context
            $post_meta = get_post_meta($post->ID);
            $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($post->post_content, 20);

            // Check if this is a translated post
            $original_post_id = get_post_meta($post->ID, '_polytrans_original_post_id', true);
            $is_translation = !empty($original_post_id);

            // Get some interesting meta fields for testing
            $seo_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
            $custom_fields = [];

            // Include some common meta fields that might be useful for testing
            $common_meta_keys = [
                '_yoast_wpseo_title',
                '_yoast_wpseo_metadesc',
                'custom_field_example',
                '_featured_text',
                '_subtitle'
            ];

            foreach ($common_meta_keys as $meta_key) {
                $meta_value = get_post_meta($post->ID, $meta_key, true);
                if (!empty($meta_value)) {
                    $custom_fields[$meta_key] = $meta_value;
                }
            }

            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $excerpt,
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'post_date' => $post->post_date,
                'is_translation' => $is_translation,
                'original_post_id' => $original_post_id,
                'meta' => $custom_fields,
                'label' => $post->post_title . ' (' . ucfirst($post->post_type) . ' #' . $post->ID . ')' . ($is_translation ? ' [Translation]' : ''),
                'description' => wp_trim_words($excerpt, 15) . '...'
            ];
        }

        wp_send_json_success(['posts' => $results]);
    }

    /**
     * Get post data for testing context
     */
    public function get_post_data($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        // Get all meta data
        $post_meta = get_post_meta($post_id);
        $meta_data = [];

        foreach ($post_meta as $key => $values) {
            // Skip WordPress internal meta and arrays
            if (strpos($key, '_wp_') === 0 || strpos($key, '_edit_') === 0) {
                continue;
            }

            $meta_data[$key] = is_array($values) && count($values) === 1 ? $values[0] : $values;
        }

        return [
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'post_date' => $post->post_date,
            'post_author' => $post->post_author,
            'meta' => $meta_data
        ];
    }
}
