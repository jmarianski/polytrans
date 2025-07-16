<?php

/**
 * Post Data Provider
 * 
 * Provides original and translated post data to workflow execution context.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Post_Data_Provider implements PolyTrans_Variable_Provider_Interface
{
    /**
     * Get the provider identifier
     */
    public function get_provider_id()
    {
        return 'post_data';
    }

    /**
     * Get the provider name
     */
    public function get_provider_name()
    {
        return __('Post Data Provider', 'polytrans');
    }

    /**
     * Get variables provided by this provider
     */
    public function get_variables($context)
    {
        $variables = [];

        // Handle test context with direct post objects
        if (isset($context['original_post']) && is_array($context['original_post'])) {
            $variables['original_post'] = $context['original_post'];
        }
        // Handle real context with post IDs
        elseif (isset($context['original_post_id'])) {
            $original_post = get_post($context['original_post_id']);
            if ($original_post) {
                $variables['original_post'] = $this->format_post_data($original_post);
            }
        }

        // Handle test context with direct post objects
        if (isset($context['translated_post']) && is_array($context['translated_post'])) {
            $variables['translated_post'] = $context['translated_post'];
        }
        // Handle real context with post IDs
        elseif (isset($context['translated_post_id'])) {
            $translated_post = get_post($context['translated_post_id']);
            if ($translated_post) {
                $variables['translated_post'] = $this->format_post_data($translated_post);
            }
        }

        // Add convenience aliases for common fields
        if (isset($variables['translated_post'])) {
            $variables['title'] = $variables['translated_post']['title'] ?? '';
            $variables['content'] = $variables['translated_post']['content'] ?? '';
            $variables['excerpt'] = $variables['translated_post']['excerpt'] ?? '';
            $variables['author_name'] = $variables['translated_post']['author_name'] ?? '';
            $variables['date'] = $variables['translated_post']['date'] ?? '';
        }

        return $variables;
    }

    /**
     * Get list of variable names this provider can supply
     */
    public function get_available_variables()
    {
        return [
            // Convenience aliases for most common fields
            'title',
            'content',
            'excerpt',
            'author_name',
            'date',
            // Full post objects
            'original_post',
            'original_post.title',
            'original_post.content',
            'original_post.excerpt',
            'original_post.slug',
            'original_post.status',
            'original_post.type',
            'original_post.author_id',
            'original_post.author_name',
            'original_post.date',
            'original_post.modified',
            'original_post.categories',
            'original_post.tags',
            'translated_post',
            'translated_post.title',
            'translated_post.content',
            'translated_post.excerpt',
            'translated_post.slug',
            'translated_post.status',
            'translated_post.type',
            'translated_post.author_id',
            'translated_post.author_name',
            'translated_post.date',
            'translated_post.modified',
            'translated_post.categories',
            'translated_post.tags'
        ];
    }

    /**
     * Check if provider can supply variables for given context
     */
    public function can_provide($context)
    {
        return isset($context['original_post_id']) ||
            isset($context['translated_post_id']) ||
            isset($context['original_post']) ||
            isset($context['translated_post']);
    }

    /**
     * Get variable documentation for UI display
     */
    public function get_variable_documentation()
    {
        return [
            // Convenience aliases (most commonly used)
            'title' => [
                'description' => __('Post title (convenience alias)', 'polytrans'),
                'example' => '{title}'
            ],
            'content' => [
                'description' => __('Post content/body (convenience alias)', 'polytrans'),
                'example' => '{content}'
            ],
            'excerpt' => [
                'description' => __('Post excerpt (convenience alias)', 'polytrans'),
                'example' => '{excerpt}'
            ],
            'author_name' => [
                'description' => __('Post author name (convenience alias)', 'polytrans'),
                'example' => '{author_name}'
            ],
            'date' => [
                'description' => __('Post date (convenience alias)', 'polytrans'),
                'example' => '{date}'
            ],
            // Full post objects
            'original_post' => [
                'description' => __('Complete original post object', 'polytrans'),
                'example' => '{original_post.title} - {original_post.content}'
            ],
            'original_post.title' => [
                'description' => __('Original post title', 'polytrans'),
                'example' => '{original_post.title}'
            ],
            'original_post.content' => [
                'description' => __('Original post content/body', 'polytrans'),
                'example' => '{original_post.content}'
            ],
            'original_post.excerpt' => [
                'description' => __('Original post excerpt', 'polytrans'),
                'example' => '{original_post.excerpt}'
            ],
            'original_post.categories' => [
                'description' => __('Original post categories (array)', 'polytrans'),
                'example' => '{original_post.categories}'
            ],
            'original_post.tags' => [
                'description' => __('Original post tags (array)', 'polytrans'),
                'example' => '{original_post.tags}'
            ],
            'translated_post' => [
                'description' => __('Complete translated post object', 'polytrans'),
                'example' => '{translated_post.title} - {translated_post.content}'
            ],
            'translated_post.title' => [
                'description' => __('Translated post title', 'polytrans'),
                'example' => '{translated_post.title}'
            ],
            'translated_post.content' => [
                'description' => __('Translated post content/body', 'polytrans'),
                'example' => '{translated_post.content}'
            ],
            'translated_post.excerpt' => [
                'description' => __('Translated post excerpt', 'polytrans'),
                'example' => '{translated_post.excerpt}'
            ],
            'original_post.meta.{key}' => [
                'description' => __('Original post meta field (replace {key} with actual meta key)', 'polytrans'),
                'example' => '{original_post.meta.seo_title} or {original_post.meta.custom_field}'
            ],
            'translated_post.meta.{key}' => [
                'description' => __('Translated post meta field (replace {key} with actual meta key)', 'polytrans'),
                'example' => '{translated_post.meta.seo_title} or {translated_post.meta.custom_field}'
            ]
        ];
    }

    /**
     * Format post data for variable context
     * 
     * @param WP_Post $post Post object
     * @return array Formatted post data
     */
    private function format_post_data($post)
    {
        // Get author information
        $author = get_userdata($post->post_author);

        // Get categories
        $categories = [];
        $post_categories = get_the_category($post->ID);
        foreach ($post_categories as $category) {
            $categories[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug
            ];
        }

        // Get tags
        $tags = [];
        $post_tags = get_the_tags($post->ID);
        if ($post_tags) {
            foreach ($post_tags as $tag) {
                $tags[] = [
                    'id' => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug
                ];
            }
        }

        // Get featured image
        $featured_image = null;
        if (has_post_thumbnail($post->ID)) {
            $featured_image = [
                'id' => get_post_thumbnail_id($post->ID),
                'url' => get_the_post_thumbnail_url($post->ID, 'full'),
                'alt' => get_post_meta(get_post_thumbnail_id($post->ID), '_wp_attachment_image_alt', true)
            ];
        }

        // Get ALL post meta as meta object
        $all_meta = get_post_meta($post->ID);
        $meta_data = [];

        foreach ($all_meta as $key => $values) {
            // For single values, store directly; for arrays, store as array
            if (count($values) === 1) {
                $meta_data[$key] = maybe_unserialize($values[0]);
            } else {
                $meta_data[$key] = array_map('maybe_unserialize', $values);
            }
        }

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'author_id' => $post->post_author,
            'author_name' => $author ? $author->display_name : '',
            'author_email' => $author ? $author->user_email : '',
            'date' => $post->post_date,
            'date_gmt' => $post->post_date_gmt,
            'modified' => $post->post_modified,
            'modified_gmt' => $post->post_modified_gmt,
            'parent_id' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'categories' => $categories,
            'tags' => $tags,
            'meta' => $meta_data,
            'featured_image' => $featured_image,
            'permalink' => get_permalink($post->ID),
            'edit_link' => get_edit_post_link($post->ID),
            'word_count' => str_word_count(strip_tags($post->post_content)),
            'character_count' => strlen($post->post_content)
        ];
    }
}
