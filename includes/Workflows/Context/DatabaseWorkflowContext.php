<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Context;

/**
 * Database-backed workflow context for real WordPress posts.
 *
 * Wraps a WordPress post and provides unified access via dot notation.
 * Changes can be buffered and committed atomically.
 *
 * @since 1.8.0
 */
class DatabaseWorkflowContext extends AbstractWorkflowContext
{
    /**
     * @var int WordPress post ID
     */
    private int $post_id;

    /**
     * @var array Buffered changes to post fields
     */
    private array $post_changes = [];

    /**
     * @var array Buffered changes to meta fields
     */
    private array $meta_changes = [];

    /**
     * @var bool Whether to auto-commit changes
     */
    private bool $auto_commit = false;

    /**
     * Constructor.
     *
     * @param int $post_id WordPress post ID
     * @param string $source_language Source language code
     * @param string $target_language Target language code
     * @param bool $auto_commit Whether to commit changes immediately
     */
    public function __construct(
        int $post_id,
        string $source_language,
        string $target_language,
        bool $auto_commit = false
    ) {
        $this->post_id = $post_id;
        $this->auto_commit = $auto_commit;

        // Load initial data from database
        $data = $this->load_post_data();

        parent::__construct($data, $source_language, $target_language);
    }

    /**
     * {@inheritdoc}
     */
    public function is_virtual(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function get_post_id(): ?int
    {
        return $this->post_id;
    }

    /**
     * {@inheritdoc}
     *
     * Intercepts post and meta changes for buffering.
     */
    public function set(string $path, mixed $value): void
    {
        parent::set($path, $value);

        // Track changes for commit
        $parts = explode('.', $path);

        if ($parts[0] === 'post' && isset($parts[1])) {
            $field = $parts[1];
            $this->post_changes[$field] = $value;

            if ($this->auto_commit) {
                $this->commit_post_field($field, $value);
            }
        } elseif ($parts[0] === 'meta' && isset($parts[1])) {
            $meta_key = $parts[1];
            $this->meta_changes[$meta_key] = $value;

            if ($this->auto_commit) {
                $this->commit_meta_field($meta_key, $value);
            }
        }
    }

    /**
     * Commit all buffered changes to database.
     *
     * @return bool True if all changes committed successfully
     */
    public function commit(): bool
    {
        $success = true;

        // Commit post changes
        if (!empty($this->post_changes)) {
            $post_data = ['ID' => $this->post_id];

            foreach ($this->post_changes as $field => $value) {
                $wp_field = $this->map_field_to_wp($field);
                if ($wp_field) {
                    $post_data[$wp_field] = $value;
                }
            }

            if (count($post_data) > 1) {
                $result = wp_update_post($post_data, true);
                if (is_wp_error($result)) {
                    $success = false;
                } else {
                    $this->post_changes = [];
                }
            }
        }

        // Commit meta changes
        foreach ($this->meta_changes as $key => $value) {
            $result = update_post_meta($this->post_id, $key, $value);
            if ($result !== false) {
                unset($this->meta_changes[$key]);
            } else {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if there are uncommitted changes.
     *
     * @return bool True if changes pending
     */
    public function has_changes(): bool
    {
        return !empty($this->post_changes) || !empty($this->meta_changes);
    }

    /**
     * Get pending changes summary.
     *
     * @return array Changes summary
     */
    public function get_pending_changes(): array
    {
        return [
            'post' => $this->post_changes,
            'meta' => $this->meta_changes,
        ];
    }

    /**
     * Discard all uncommitted changes and reload from database.
     */
    public function rollback(): void
    {
        $this->post_changes = [];
        $this->meta_changes = [];
        $this->data = $this->load_post_data();
    }

    /**
     * Create context from post ID with auto-detection of languages.
     *
     * @param int $post_id WordPress post ID
     * @param bool $auto_commit Whether to auto-commit changes
     * @return self|null Context or null if post not found
     */
    public static function from_post(int $post_id, bool $auto_commit = false): ?self
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        // Try to detect languages from Polylang
        $source_lang = '';
        $target_lang = '';

        if (function_exists('pll_get_post_language')) {
            $target_lang = pll_get_post_language($post_id, 'slug') ?: '';

            // Try to find source from translation group
            if (function_exists('pll_get_post_translations')) {
                $translations = pll_get_post_translations($post_id);
                // Assume first different language is source (heuristic)
                foreach ($translations as $lang => $trans_id) {
                    if ($lang !== $target_lang && $trans_id !== $post_id) {
                        $source_lang = $lang;
                        break;
                    }
                }
            }
        }

        // Fallback: check post meta for polytrans source
        if (empty($source_lang)) {
            $source_lang = get_post_meta($post_id, '_polytrans_source_language', true) ?: 'en';
        }

        return new self($post_id, $source_lang, $target_lang, $auto_commit);
    }

    /**
     * Load post data from database.
     *
     * @return array Post data in context format
     */
    private function load_post_data(): array
    {
        $post = get_post($this->post_id);

        if (!$post) {
            return [];
        }

        $data = [
            'post' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'author' => $post->post_author,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'slug' => $post->post_name,
                'parent' => $post->post_parent,
            ],
            'meta' => [],
            'taxonomy' => [
                'categories' => [],
                'tags' => [],
            ],
        ];

        // Load all post meta
        $meta = get_post_meta($this->post_id);
        foreach ($meta as $key => $values) {
            // Skip internal WordPress meta
            if (str_starts_with($key, '_')) {
                continue;
            }
            $data['meta'][$key] = count($values) === 1 ? $values[0] : $values;
        }

        // Load categories
        $categories = wp_get_post_categories($this->post_id, ['fields' => 'all']);
        if (!is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $data['taxonomy']['categories'][] = [
                    'id' => $cat->term_id,
                    'slug' => $cat->slug,
                    'name' => $cat->name,
                ];
            }
        }

        // Load tags
        $tags = wp_get_post_tags($this->post_id, ['fields' => 'all']);
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $data['taxonomy']['tags'][] = [
                    'id' => $tag->term_id,
                    'slug' => $tag->slug,
                    'name' => $tag->name,
                ];
            }
        }

        return $data;
    }

    /**
     * Commit a single post field.
     *
     * @param string $field Field name
     * @param mixed $value Field value
     */
    private function commit_post_field(string $field, mixed $value): void
    {
        $wp_field = $this->map_field_to_wp($field);
        if ($wp_field) {
            wp_update_post([
                'ID' => $this->post_id,
                $wp_field => $value,
            ]);
        }
    }

    /**
     * Commit a single meta field.
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     */
    private function commit_meta_field(string $key, mixed $value): void
    {
        update_post_meta($this->post_id, $key, $value);
    }

    /**
     * Map context field name to WordPress post field.
     *
     * @param string $field Context field name
     * @return string|null WordPress field name or null
     */
    private function map_field_to_wp(string $field): ?string
    {
        $map = [
            'title' => 'post_title',
            'content' => 'post_content',
            'excerpt' => 'post_excerpt',
            'status' => 'post_status',
            'slug' => 'post_name',
            'author' => 'post_author',
            'parent' => 'post_parent',
            'date' => 'post_date',
        ];

        return $map[$field] ?? null;
    }
}
