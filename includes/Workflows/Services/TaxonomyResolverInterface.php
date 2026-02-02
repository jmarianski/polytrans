<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Services;

/**
 * Interface for taxonomy term resolution.
 *
 * Resolves taxonomy terms from source language to target language equivalents.
 *
 * @since 1.8.0
 */
interface TaxonomyResolverInterface
{
    /**
     * Resolve a single taxonomy term to target language equivalent.
     *
     * @param string $taxonomy Taxonomy name ('category', 'post_tag', or custom)
     * @param array $term Term data with at least 'slug' or 'id'
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @return TaxonomyResolution Resolution result
     */
    public function resolve(
        string $taxonomy,
        array $term,
        string $source_lang,
        string $target_lang
    ): TaxonomyResolution;

    /**
     * Resolve multiple terms in batch.
     *
     * @param string $taxonomy Taxonomy name
     * @param array<array> $terms Array of term data
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @return array<TaxonomyResolution> Array of resolutions
     */
    public function resolve_batch(
        string $taxonomy,
        array $terms,
        string $source_lang,
        string $target_lang
    ): array;

    /**
     * Check if resolver is available.
     *
     * May return false if Polylang is not active or taxonomy is not translatable.
     *
     * @param string $taxonomy Taxonomy name
     * @return bool True if resolver can handle this taxonomy
     */
    public function is_available(string $taxonomy): bool;
}

/**
 * Result of taxonomy term resolution.
 */
class TaxonomyResolution
{
    /**
     * Resolution status constants.
     */
    public const STATUS_MATCHED = 'matched';          // Found exact translation
    public const STATUS_UNRESOLVED = 'unresolved';    // Term exists but no translation for target lang
    public const STATUS_UNKNOWN = 'unknown';          // Term not found in system
    public const STATUS_PASSTHROUGH = 'passthrough';  // Resolver disabled or N/A

    /**
     * @var array Original term data from payload
     */
    public array $original;

    /**
     * @var array|null Resolved term data or null if not found
     */
    public ?array $resolved;

    /**
     * @var string Resolution status
     */
    public string $status;

    /**
     * @var string|null Optional message (e.g., "No DE translation found")
     */
    public ?string $message;

    /**
     * Constructor.
     *
     * @param array $original Original term data
     * @param array|null $resolved Resolved term data
     * @param string $status Resolution status
     * @param string|null $message Optional message
     */
    public function __construct(
        array $original,
        ?array $resolved,
        string $status,
        ?string $message = null
    ) {
        $this->original = $original;
        $this->resolved = $resolved;
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * Create a matched resolution.
     */
    public static function matched(array $original, array $resolved): self
    {
        return new self($original, $resolved, self::STATUS_MATCHED);
    }

    /**
     * Create an unresolved resolution (term exists but no translation).
     */
    public static function unresolved(array $original, ?string $message = null): self
    {
        return new self($original, null, self::STATUS_UNRESOLVED, $message);
    }

    /**
     * Create an unknown resolution (term not in system).
     */
    public static function unknown(array $original): self
    {
        return new self($original, null, self::STATUS_UNKNOWN, 'Term not found in system');
    }

    /**
     * Create a passthrough resolution (no processing).
     */
    public static function passthrough(array $original): self
    {
        return new self($original, $original, self::STATUS_PASSTHROUGH);
    }

    /**
     * Check if resolution was successful (has resolved data).
     */
    public function is_resolved(): bool
    {
        return $this->status === self::STATUS_MATCHED && $this->resolved !== null;
    }

    /**
     * Get the effective term data (resolved if available, otherwise original).
     */
    public function get_effective(): array
    {
        return $this->resolved ?? $this->original;
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function to_array(): array
    {
        return [
            'original' => $this->original,
            'resolved' => $this->resolved,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
