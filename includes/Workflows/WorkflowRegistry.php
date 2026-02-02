<?php

declare(strict_types=1);

namespace PolyTrans\Workflows;

use PolyTrans\Workflows\Steps\WorkflowStepInterface;
use PolyTrans\Workflows\Steps\LegacyStepAdapter;
use PolyTrans\Workflows\Services\TaxonomyResolver;
use PolyTrans\Workflows\Services\TaxonomyResolverInterface;

/**
 * Registry for workflow steps and services.
 *
 * Manages step registration, including automatic wrapping of legacy steps.
 * Provides service container for dependency injection.
 *
 * @since 1.8.0
 */
class WorkflowRegistry
{
    /**
     * @var self|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var WorkflowExecutor The executor instance
     */
    private WorkflowExecutor $executor;

    /**
     * @var array<string, object> Registered services
     */
    private array $services = [];

    /**
     * @var array<string, array> Legacy step metadata
     */
    private array $legacy_step_meta = [];

    /**
     * Private constructor (singleton).
     */
    private function __construct()
    {
        $this->executor = new WorkflowExecutor();
        $this->register_default_services();
        $this->register_default_steps();
    }

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the workflow executor.
     *
     * @return WorkflowExecutor
     */
    public function get_executor(): WorkflowExecutor
    {
        return $this->executor;
    }

    /**
     * Register a workflow step.
     *
     * @param WorkflowStepInterface $step Step instance
     */
    public function register_step(WorkflowStepInterface $step): void
    {
        $this->executor->register_step($step);
    }

    /**
     * Register a legacy step with automatic adapter wrapping.
     *
     * @param string $class_name Legacy step class name
     * @param bool $external_compatible Whether step works with virtual context
     * @param array $required_paths Data paths required for execution
     * @return bool True if registered successfully
     */
    public function register_legacy_step(
        string $class_name,
        bool $external_compatible = true,
        array $required_paths = []
    ): bool {
        $adapter = LegacyStepAdapter::from_class($class_name, $external_compatible, $required_paths);

        if ($adapter === null) {
            return false;
        }

        $this->executor->register_step($adapter);
        $this->legacy_step_meta[$adapter->get_id()] = [
            'class' => $class_name,
            'external_compatible' => $external_compatible,
            'required_paths' => $required_paths,
        ];

        return true;
    }

    /**
     * Register a service for dependency injection.
     *
     * @param string $name Service identifier
     * @param object $service Service instance
     */
    public function register_service(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Get a registered service.
     *
     * @param string $name Service identifier
     * @return object|null Service or null if not found
     */
    public function get_service(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Check if a service is registered.
     *
     * @param string $name Service identifier
     * @return bool True if service exists
     */
    public function has_service(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * Get all registered services.
     *
     * @return array<string, object>
     */
    public function get_services(): array
    {
        return $this->services;
    }

    /**
     * Inject all registered services into a context.
     *
     * @param Context\WorkflowContextInterface $context
     */
    public function inject_services(Context\WorkflowContextInterface $context): void
    {
        if (!method_exists($context, 'register_service')) {
            return;
        }

        foreach ($this->services as $name => $service) {
            $context->register_service($name, $service);
        }
    }

    /**
     * Get step metadata for UI.
     *
     * @return array Step information for UI rendering
     */
    public function get_steps_for_ui(): array
    {
        $steps = [];

        foreach ($this->executor->get_steps() as $id => $step) {
            $steps[$id] = [
                'id' => $step->get_id(),
                'name' => $step->get_name(),
                'description' => $step->get_description(),
                'external_compatible' => $step->is_external_compatible(),
                'required_services' => $step->get_required_services(),
                'required_paths' => $step->get_required_paths(),
                'is_legacy' => isset($this->legacy_step_meta[$id]),
            ];
        }

        return $steps;
    }

    /**
     * Register default services.
     */
    private function register_default_services(): void
    {
        // Register TaxonomyResolver (will be no-op if Polylang not available)
        $this->register_service('TaxonomyResolver', new TaxonomyResolver());

        // Register Logger service (wrapper around LogsManager if available)
        // Only when running in WordPress context
        if (function_exists('add_action') && class_exists(\PolyTrans\Core\LogsManager::class)) {
            $this->register_service('Logger', new class {
                public function log(string $message, string $level = 'info', array $context = []): void
                {
                    \PolyTrans\Core\LogsManager::log($message, $level, $context);
                }
            });
        }
    }

    /**
     * Register default workflow steps.
     */
    private function register_default_steps(): void
    {
        // Register new native steps
        $this->register_step(new Steps\ResolveTaxonomyStep());

        // Only register legacy steps when running in WordPress context
        if (!function_exists('add_action')) {
            return;
        }

        // Register legacy steps with adapters
        $legacy_steps = [
            [
                'class' => \PolyTrans\PostProcessing\Steps\AiAssistantStep::class,
                'external_compatible' => true, // Works on content, doesn't need DB
                'required_paths' => [], // Depends on step config
            ],
            [
                'class' => \PolyTrans\PostProcessing\Steps\ManagedAssistantStep::class,
                'external_compatible' => true,
                'required_paths' => [],
            ],
            [
                'class' => \PolyTrans\PostProcessing\Steps\PredefinedAssistantStep::class,
                'external_compatible' => true,
                'required_paths' => [],
            ],
        ];

        foreach ($legacy_steps as $config) {
            if (class_exists($config['class'])) {
                $this->register_legacy_step(
                    $config['class'],
                    $config['external_compatible'],
                    $config['required_paths']
                );
            }
        }

        // Allow plugins to register additional steps
        do_action('polytrans_register_workflow_steps', $this);
    }

    /**
     * Reset the singleton (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
