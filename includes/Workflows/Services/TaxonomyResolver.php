<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Services;

/**
 * Taxonomy resolver using Polylang for term translation lookup.
 *
 * Provides read-only access to taxonomy translation relationships.
 *
 * @since 1.8.0
 */
class TaxonomyResolver implements TaxonomyResolverInterface
{
    /**
     * @var bool Whether Polylang is available
     */
    private bool $polylang_available;

    /**
     * @var array<string, bool> Cache of translatable taxonomies
     */
    private array $translatable_cache = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->polylang_available = function_exists('pll_get_term_translations');
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(
        string $taxonomy,
        array $term,
        string $source_lang,
        string $target_lang
    ): TaxonomyResolution {
        // No Polylang = passthrough
        if (!$this->polylang_available) {
            return TaxonomyResolution::passthrough($term);
        }

        // Taxonomy not translatable = passthrough
        if (!$this->is_available($taxonomy)) {
            return TaxonomyResolution::passthrough($term);
        }

        // Same language = passthrough
        if ($source_lang === $target_lang) {
            return TaxonomyResolution::passthrough($term);
        }

        // Try to find the source term
        $source_term = $this->find_term($taxonomy, $term, $source_lang);

        if (!$source_term) {
            return TaxonomyResolution::unknown($term);
        }

        // Get translations
        $translations = pll_get_term_translations($source_term->term_id);

        if (!isset($translations[$target_lang])) {
            return TaxonomyResolution::unresolved(
                $term,
                sprintf('No %s translation for term "%s"', strtoupper($target_lang), $term['slug'] ?? $term['name'] ?? 'unknown')
            );
        }

        // Get the target term
        $target_term_id = $translations[$target_lang];
        $target_term = get_term($target_term_id, $taxonomy);

        if (!$target_term || is_wp_error($target_term)) {
            return TaxonomyResolution::unresolved(
                $term,
                sprintf('Target term ID %d not found', $target_term_id)
            );
        }

        // Build resolved data
        $resolved = [
            'id' => $target_term->term_id,
            'slug' => $target_term->slug,
            'name' => $target_term->name,
            'taxonomy' => $target_term->taxonomy,
        ];

        return TaxonomyResolution::matched($term, $resolved);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve_batch(
        string $taxonomy,
        array $terms,
        string $source_lang,
        string $target_lang
    ): array {
        $results = [];

        foreach ($terms as $term) {
            $results[] = $this->resolve($taxonomy, $term, $source_lang, $target_lang);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function is_available(string $taxonomy): bool
    {
        if (!$this->polylang_available) {
            return false;
        }

        // Check cache
        if (isset($this->translatable_cache[$taxonomy])) {
            return $this->translatable_cache[$taxonomy];
        }

        // Check if taxonomy is translatable in Polylang
        $translatable = false;

        if (function_exists('pll_is_translated_taxonomy')) {
            $translatable = pll_is_translated_taxonomy($taxonomy);
        }

        $this->translatable_cache[$taxonomy] = $translatable;

        return $translatable;
    }

    /**
     * Find a term by various identifiers.
     *
     * @param string $taxonomy Taxonomy name
     * @param array $term Term data (may have id, slug, or name)
     * @param string $lang Language code
     * @return \WP_Term|null Found term or null
     */
    private function find_term(string $taxonomy, array $term, string $lang): ?\WP_Term
    {
        // Try by ID first
        if (!empty($term['id'])) {
            $found = get_term((int) $term['id'], $taxonomy);
            if ($found && !is_wp_error($found)) {
                // Verify language matches
                if ($this->term_has_language($found->term_id, $lang)) {
                    return $found;
                }
            }
        }

        // Try by slug
        if (!empty($term['slug'])) {
            $found = get_term_by('slug', $term['slug'], $taxonomy);
            if ($found && !is_wp_error($found)) {
                if ($this->term_has_language($found->term_id, $lang)) {
                    return $found;
                }
                // If slug exists but wrong language, try to find the source language version
                return $this->find_term_in_language($found->term_id, $lang);
            }
        }

        // Try by name
        if (!empty($term['name'])) {
            $found = get_term_by('name', $term['name'], $taxonomy);
            if ($found && !is_wp_error($found)) {
                if ($this->term_has_language($found->term_id, $lang)) {
                    return $found;
                }
                return $this->find_term_in_language($found->term_id, $lang);
            }
        }

        return null;
    }

    /**
     * Check if a term is in the specified language.
     *
     * @param int $term_id Term ID
     * @param string $lang Language code
     * @return bool True if term is in the language
     */
    private function term_has_language(int $term_id, string $lang): bool
    {
        if (!function_exists('pll_get_term_language')) {
            return true; // Assume match if no Polylang
        }

        $term_lang = pll_get_term_language($term_id);
        return $term_lang === $lang;
    }

    /**
     * Find a term's translation in specific language.
     *
     * @param int $term_id Any translation's term ID
     * @param string $lang Target language code
     * @return \WP_Term|null Term in target language or null
     */
    private function find_term_in_language(int $term_id, string $lang): ?\WP_Term
    {
        if (!function_exists('pll_get_term_translations')) {
            return null;
        }

        $translations = pll_get_term_translations($term_id);

        if (!isset($translations[$lang])) {
            return null;
        }

        $target_term = get_term($translations[$lang]);

        if (!$target_term || is_wp_error($target_term)) {
            return null;
        }

        return $target_term;
    }
}
