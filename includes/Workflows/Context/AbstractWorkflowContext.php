<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Context;

/**
 * Abstract base class for workflow contexts.
 *
 * Provides common functionality for dot-notation path handling and service management.
 *
 * @since 1.8.0
 */
abstract class AbstractWorkflowContext implements WorkflowContextInterface
{
    /**
     * @var array Data storage
     */
    protected array $data = [];

    /**
     * @var string Source language code
     */
    protected string $source_language;

    /**
     * @var string Target language code
     */
    protected string $target_language;

    /**
     * @var array<string, object> Registered services
     */
    protected array $services = [];

    /**
     * Constructor.
     *
     * @param array $data Initial data
     * @param string $source_language Source language code
     * @param string $target_language Target language code
     */
    public function __construct(array $data, string $source_language, string $target_language)
    {
        $this->data = $data;
        $this->source_language = $source_language;
        $this->target_language = $target_language;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): mixed
    {
        return $this->get_by_path($this->data, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $path, mixed $value): void
    {
        $this->set_by_path($this->data, $path, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $path): bool
    {
        return $this->has_path($this->data, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): void
    {
        $this->delete_by_path($this->data, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function export(): array
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function get_source_language(): string
    {
        return $this->source_language;
    }

    /**
     * {@inheritdoc}
     */
    public function get_target_language(): string
    {
        return $this->target_language;
    }

    /**
     * {@inheritdoc}
     */
    public function get_service(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function has_service(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * Register a service.
     *
     * @param string $name Service identifier
     * @param object $service Service instance
     */
    public function register_service(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Get value from array using dot notation path.
     *
     * @param array $array Source array
     * @param string $path Dot-notation path (e.g., 'post.title', 'meta.0.key')
     * @return mixed Value or null if not found
     */
    protected function get_by_path(array $array, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Set value in array using dot notation path.
     *
     * Creates intermediate arrays as needed.
     *
     * @param array &$array Target array (by reference)
     * @param string $path Dot-notation path
     * @param mixed $value Value to set
     */
    protected function set_by_path(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Check if path exists in array.
     *
     * @param array $array Source array
     * @param string $path Dot-notation path
     * @return bool True if path exists
     */
    protected function has_path(array $array, string $path): bool
    {
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete value at path.
     *
     * @param array &$array Target array (by reference)
     * @param string $path Dot-notation path
     */
    protected function delete_by_path(array &$array, string $path): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                unset($current[$key]);
                return;
            }

            if (!isset($current[$key]) || !is_array($current[$key])) {
                return;
            }

            $current = &$current[$key];
        }
    }
}
