<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Context;

/**
 * Interface for workflow execution context.
 *
 * Provides unified access to data regardless of storage backend.
 * Can operate on real WordPress posts (DatabaseContext) or pure JSON (VirtualContext).
 *
 * @since 1.8.0
 */
interface WorkflowContextInterface
{
    /**
     * Get value at path using dot notation.
     *
     * Examples:
     * - get('post.title')
     * - get('post.content')
     * - get('meta.custom_field')
     * - get('taxonomy.categories')
     *
     * @param string $path Dot-notation path to value
     * @return mixed Value at path or null if not found
     */
    public function get(string $path): mixed;

    /**
     * Set value at path using dot notation.
     *
     * @param string $path Dot-notation path
     * @param mixed $value Value to set
     */
    public function set(string $path, mixed $value): void;

    /**
     * Check if path exists in data.
     *
     * @param string $path Dot-notation path
     * @return bool True if path exists (even if value is null)
     */
    public function has(string $path): bool;

    /**
     * Delete value at path.
     *
     * @param string $path Dot-notation path
     */
    public function delete(string $path): void;

    /**
     * Export all data as array.
     *
     * @return array Complete data structure
     */
    public function export(): array;

    /**
     * Check if this is a virtual (non-persistent) context.
     *
     * Virtual contexts operate on JSON data without WordPress side effects.
     *
     * @return bool True if virtual, false if backed by database
     */
    public function is_virtual(): bool;

    /**
     * Get source language code.
     *
     * @return string ISO language code (e.g., 'en', 'de', 'pl')
     */
    public function get_source_language(): string;

    /**
     * Get target language code.
     *
     * @return string ISO language code
     */
    public function get_target_language(): string;

    /**
     * Get a registered service by name.
     *
     * Services provide read-only access to system data (taxonomy, users, etc.)
     *
     * @param string $name Service identifier
     * @return object|null Service instance or null if not registered
     */
    public function get_service(string $name): ?object;

    /**
     * Check if a service is available.
     *
     * @param string $name Service identifier
     * @return bool True if service is registered
     */
    public function has_service(string $name): bool;

    /**
     * Get post ID if available.
     *
     * @return int|null Post ID for DatabaseContext, null for VirtualContext
     */
    public function get_post_id(): ?int;
}
