<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Steps;

use PolyTrans\Workflows\Context\WorkflowContextInterface;

/**
 * Abstract base class for workflow steps.
 *
 * Provides common functionality and sensible defaults.
 *
 * @since 1.8.0
 */
abstract class AbstractWorkflowStep implements WorkflowStepInterface
{
    /**
     * {@inheritdoc}
     */
    abstract public function get_id(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function get_name(): string;

    /**
     * {@inheritdoc}
     */
    public function get_description(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * Default: compatible with virtual context.
     * Override to return false for steps that require database access.
     */
    public function is_external_compatible(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Default: no required services.
     */
    public function get_required_services(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * Default: no required paths.
     */
    public function get_required_paths(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    abstract public function execute(WorkflowContextInterface $context, array $config): void;

    /**
     * {@inheritdoc}
     *
     * Default: no validation.
     */
    public function validate_config(array $config): array
    {
        return [];
    }

    /**
     * Check if step can execute in given context.
     *
     * Validates:
     * - Virtual compatibility
     * - Required services availability
     * - Required paths existence
     *
     * @param WorkflowContextInterface $context Execution context
     * @return array{can_execute: bool, reason: ?string} Execution eligibility
     */
    public function can_execute(WorkflowContextInterface $context): array
    {
        // Check virtual compatibility
        if ($context->is_virtual() && !$this->is_external_compatible()) {
            return [
                'can_execute' => false,
                'reason' => sprintf(
                    'Step "%s" is not compatible with virtual/external context',
                    $this->get_id()
                ),
            ];
        }

        // Check required services
        foreach ($this->get_required_services() as $service) {
            if (!$context->has_service($service)) {
                return [
                    'can_execute' => false,
                    'reason' => sprintf(
                        'Step "%s" requires service "%s" which is not available',
                        $this->get_id(),
                        $service
                    ),
                ];
            }
        }

        // Check required paths
        foreach ($this->get_required_paths() as $path) {
            if (!$context->has($path)) {
                return [
                    'can_execute' => false,
                    'reason' => sprintf(
                        'Step "%s" requires data at path "%s" which is missing',
                        $this->get_id(),
                        $path
                    ),
                ];
            }
        }

        return ['can_execute' => true, 'reason' => null];
    }

    /**
     * Log a message (if logging service is available).
     *
     * @param WorkflowContextInterface $context
     * @param string $message
     * @param string $level 'info', 'warning', 'error'
     */
    protected function log(WorkflowContextInterface $context, string $message, string $level = 'info'): void
    {
        $logger = $context->get_service('Logger');
        if ($logger && method_exists($logger, 'log')) {
            $logger->log(
                sprintf('[%s] %s', $this->get_id(), $message),
                $level,
                ['step' => $this->get_id()]
            );
        }
    }
}
