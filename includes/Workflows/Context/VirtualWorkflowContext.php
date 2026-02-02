<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Context;

/**
 * Virtual workflow context for pure JSON transformation.
 *
 * Operates entirely on in-memory data without WordPress side effects.
 * Used for Translation Only mode and external translation processing.
 *
 * @since 1.8.0
 */
class VirtualWorkflowContext extends AbstractWorkflowContext
{
    /**
     * {@inheritdoc}
     */
    public function is_virtual(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get_post_id(): ?int
    {
        return null;
    }

    /**
     * Create context from translation payload.
     *
     * Expected payload structure:
     * ```
     * {
     *     "post": {
     *         "title": "...",
     *         "content": "...",
     *         "excerpt": "..."
     *     },
     *     "meta": {
     *         "custom_field": "value"
     *     },
     *     "taxonomy": {
     *         "categories": [...],
     *         "tags": [...]
     *     },
     *     "source_language": "en",
     *     "target_language": "de"
     * }
     * ```
     *
     * @param array $payload Translation payload
     * @return self
     */
    public static function from_payload(array $payload): self
    {
        $source_lang = $payload['source_language'] ?? $payload['source_lang'] ?? 'en';
        $target_lang = $payload['target_language'] ?? $payload['target_lang'] ?? '';

        return new self($payload, $source_lang, $target_lang);
    }

    /**
     * Create context with explicit languages.
     *
     * @param array $data Initial data
     * @param string $source_language Source language code
     * @param string $target_language Target language code
     * @return self
     */
    public static function create(array $data, string $source_language, string $target_language): self
    {
        return new self($data, $source_language, $target_language);
    }

    /**
     * Get a copy of the context with merged data.
     *
     * Does not modify the original context.
     *
     * @param array $data Data to merge
     * @return self New context instance
     */
    public function with_data(array $data): self
    {
        $merged = array_replace_recursive($this->data, $data);
        $new_context = new self($merged, $this->source_language, $this->target_language);

        // Copy services
        foreach ($this->services as $name => $service) {
            $new_context->register_service($name, $service);
        }

        return $new_context;
    }

    /**
     * Get only the modified/transformed data sections.
     *
     * Useful for returning just the parts that workflows touched.
     *
     * @param array $original_data Original data before transformation
     * @return array Changed data sections
     */
    public function get_changes(array $original_data): array
    {
        return $this->array_diff_recursive($this->data, $original_data);
    }

    /**
     * Recursively diff two arrays.
     *
     * @param array $array1 New array
     * @param array $array2 Original array
     * @return array Differences
     */
    private function array_diff_recursive(array $array1, array $array2): array
    {
        $diff = [];

        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                $diff[$key] = $value;
            } elseif (is_array($value) && is_array($array2[$key])) {
                $nested_diff = $this->array_diff_recursive($value, $array2[$key]);
                if (!empty($nested_diff)) {
                    $diff[$key] = $nested_diff;
                }
            } elseif ($value !== $array2[$key]) {
                $diff[$key] = $value;
            }
        }

        return $diff;
    }
}
