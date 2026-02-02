<?php

declare(strict_types=1);

namespace PolyTrans\Workflows;

use PolyTrans\Workflows\Context\WorkflowContextInterface;
use PolyTrans\Workflows\Steps\WorkflowStepInterface;
use PolyTrans\Workflows\Steps\AbstractWorkflowStep;

/**
 * Executes workflow steps on a context.
 *
 * @since 1.8.0
 */
class WorkflowExecutor
{
    /**
     * @var array<string, WorkflowStepInterface> Registered steps
     */
    private array $steps = [];

    /**
     * @var bool Whether to continue on step failure
     */
    private bool $continue_on_error = true;

    /**
     * @var bool Whether to skip incompatible steps silently
     */
    private bool $skip_incompatible = true;

    /**
     * Register a workflow step.
     *
     * @param WorkflowStepInterface $step Step instance
     */
    public function register_step(WorkflowStepInterface $step): void
    {
        $this->steps[$step->get_id()] = $step;
    }

    /**
     * Get a registered step by ID.
     *
     * @param string $step_id Step identifier
     * @return WorkflowStepInterface|null Step or null if not found
     */
    public function get_step(string $step_id): ?WorkflowStepInterface
    {
        return $this->steps[$step_id] ?? null;
    }

    /**
     * Get all registered steps.
     *
     * @return array<string, WorkflowStepInterface>
     */
    public function get_steps(): array
    {
        return $this->steps;
    }

    /**
     * Set error handling behavior.
     *
     * @param bool $continue If true, continue executing remaining steps on error
     */
    public function set_continue_on_error(bool $continue): void
    {
        $this->continue_on_error = $continue;
    }

    /**
     * Set incompatibility handling behavior.
     *
     * @param bool $skip If true, skip incompatible steps silently
     */
    public function set_skip_incompatible(bool $skip): void
    {
        $this->skip_incompatible = $skip;
    }

    /**
     * Execute a workflow on a context.
     *
     * @param WorkflowContextInterface $context Execution context
     * @param array $workflow Workflow definition with steps
     * @return WorkflowResult Execution result
     */
    public function execute(WorkflowContextInterface $context, array $workflow): WorkflowResult
    {
        $result = new WorkflowResult();
        $steps_config = $workflow['steps'] ?? [];

        foreach ($steps_config as $index => $step_config) {
            $step_id = $step_config['type'] ?? $step_config['id'] ?? null;

            if (!$step_id) {
                $result->add_error($index, 'unknown', 'Step configuration missing type/id');
                continue;
            }

            $step = $this->get_step($step_id);

            if (!$step) {
                $result->add_error($index, $step_id, "Step '{$step_id}' not registered");
                if (!$this->continue_on_error) {
                    break;
                }
                continue;
            }

            // Check if step can execute
            if ($step instanceof AbstractWorkflowStep) {
                $eligibility = $step->can_execute($context);
                if (!$eligibility['can_execute']) {
                    if ($this->skip_incompatible) {
                        $result->add_skipped($index, $step_id, $eligibility['reason']);
                        continue;
                    } else {
                        $result->add_error($index, $step_id, $eligibility['reason']);
                        if (!$this->continue_on_error) {
                            break;
                        }
                        continue;
                    }
                }
            }

            // Validate config
            $validation_errors = $step->validate_config($step_config);
            if (!empty($validation_errors)) {
                $result->add_error($index, $step_id, implode('; ', $validation_errors));
                if (!$this->continue_on_error) {
                    break;
                }
                continue;
            }

            // Execute step
            try {
                $step->execute($context, $step_config);
                $result->add_executed($index, $step_id);
            } catch (\Throwable $e) {
                $result->add_error($index, $step_id, $e->getMessage(), $e);
                if (!$this->continue_on_error) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Validate a workflow definition without executing.
     *
     * @param array $workflow Workflow definition
     * @param WorkflowContextInterface|null $context Optional context for compatibility checks
     * @return array<string> Validation error messages
     */
    public function validate(array $workflow, ?WorkflowContextInterface $context = null): array
    {
        $errors = [];
        $steps_config = $workflow['steps'] ?? [];

        if (empty($steps_config)) {
            $errors[] = 'Workflow has no steps defined';
            return $errors;
        }

        foreach ($steps_config as $index => $step_config) {
            $step_id = $step_config['type'] ?? $step_config['id'] ?? null;

            if (!$step_id) {
                $errors[] = "Step {$index}: Missing type/id";
                continue;
            }

            $step = $this->get_step($step_id);

            if (!$step) {
                $errors[] = "Step {$index}: Unknown step type '{$step_id}'";
                continue;
            }

            // Check virtual compatibility if context provided
            if ($context && $context->is_virtual() && !$step->is_external_compatible()) {
                $errors[] = "Step {$index} ({$step_id}): Not compatible with virtual/external context";
            }

            // Validate step config
            $step_errors = $step->validate_config($step_config);
            foreach ($step_errors as $error) {
                $errors[] = "Step {$index} ({$step_id}): {$error}";
            }
        }

        return $errors;
    }

    /**
     * Get steps that are compatible with virtual context.
     *
     * @return array<string, WorkflowStepInterface> External-compatible steps
     */
    public function get_external_compatible_steps(): array
    {
        return array_filter(
            $this->steps,
            fn(WorkflowStepInterface $step) => $step->is_external_compatible()
        );
    }
}

/**
 * Result of workflow execution.
 */
class WorkflowResult
{
    /** @var array<array{index: int, step_id: string}> Successfully executed steps */
    private array $executed = [];

    /** @var array<array{index: int, step_id: string, reason: string}> Skipped steps */
    private array $skipped = [];

    /** @var array<array{index: int, step_id: string, message: string, exception: ?\Throwable}> Failed steps */
    private array $errors = [];

    public function add_executed(int $index, string $step_id): void
    {
        $this->executed[] = ['index' => $index, 'step_id' => $step_id];
    }

    public function add_skipped(int $index, string $step_id, ?string $reason): void
    {
        $this->skipped[] = ['index' => $index, 'step_id' => $step_id, 'reason' => $reason];
    }

    public function add_error(int $index, string $step_id, string $message, ?\Throwable $exception = null): void
    {
        $this->errors[] = [
            'index' => $index,
            'step_id' => $step_id,
            'message' => $message,
            'exception' => $exception,
        ];
    }

    public function is_success(): bool
    {
        return empty($this->errors);
    }

    public function has_skipped(): bool
    {
        return !empty($this->skipped);
    }

    public function get_executed(): array
    {
        return $this->executed;
    }

    public function get_skipped(): array
    {
        return $this->skipped;
    }

    public function get_errors(): array
    {
        return $this->errors;
    }

    public function get_executed_count(): int
    {
        return count($this->executed);
    }

    public function get_skipped_count(): int
    {
        return count($this->skipped);
    }

    public function get_error_count(): int
    {
        return count($this->errors);
    }

    /**
     * Get summary as array.
     */
    public function to_array(): array
    {
        return [
            'success' => $this->is_success(),
            'executed' => $this->executed,
            'skipped' => $this->skipped,
            'errors' => array_map(
                fn($e) => [
                    'index' => $e['index'],
                    'step_id' => $e['step_id'],
                    'message' => $e['message'],
                ],
                $this->errors
            ),
            'stats' => [
                'executed' => $this->get_executed_count(),
                'skipped' => $this->get_skipped_count(),
                'errors' => $this->get_error_count(),
            ],
        ];
    }
}
