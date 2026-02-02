<?php

declare(strict_types=1);

namespace PolyTrans\Workflows;

use PolyTrans\Workflows\Context\WorkflowContextInterface;
use PolyTrans\Workflows\Context\VirtualWorkflowContext;
use PolyTrans\Workflows\Context\DatabaseWorkflowContext;

/**
 * High-level workflow runner for easy integration.
 *
 * Provides simple methods to execute workflows on various data sources.
 *
 * @since 1.8.0
 */
class WorkflowRunner
{
    /**
     * @var WorkflowRegistry Registry instance
     */
    private WorkflowRegistry $registry;

    /**
     * Constructor.
     *
     * @param WorkflowRegistry|null $registry Optional registry (uses singleton if not provided)
     */
    public function __construct(?WorkflowRegistry $registry = null)
    {
        $this->registry = $registry ?? WorkflowRegistry::get_instance();
    }

    /**
     * Execute workflow on a JSON payload (virtual/stateless).
     *
     * Use this for Translation Only mode or external processing.
     *
     * @param array $payload Translation payload with post data
     * @param array $workflow Workflow definition
     * @param array $options Additional options
     * @return array Result with modified payload and execution info
     */
    public function run_virtual(array $payload, array $workflow, array $options = []): array
    {
        $context = VirtualWorkflowContext::from_payload($payload);

        // Inject services
        $this->registry->inject_services($context);

        // Execute workflow
        $executor = $this->registry->get_executor();
        $result = $executor->execute($context, $workflow);

        return [
            'success' => $result->is_success(),
            'payload' => $context->export(),
            'changes' => $context->get_changes($payload),
            'execution' => $result->to_array(),
        ];
    }

    /**
     * Execute workflow on a WordPress post (database-backed).
     *
     * Use this for local post processing.
     *
     * @param int $post_id WordPress post ID
     * @param array $workflow Workflow definition
     * @param array $options Additional options (auto_commit, source_lang, target_lang)
     * @return array Result with execution info
     */
    public function run_on_post(int $post_id, array $workflow, array $options = []): array
    {
        $auto_commit = $options['auto_commit'] ?? true;
        $source_lang = $options['source_language'] ?? '';
        $target_lang = $options['target_language'] ?? '';

        // Create context
        if (!empty($source_lang) && !empty($target_lang)) {
            $context = new DatabaseWorkflowContext($post_id, $source_lang, $target_lang, false);
        } else {
            $context = DatabaseWorkflowContext::from_post($post_id, false);
            if ($context === null) {
                return [
                    'success' => false,
                    'error' => "Post {$post_id} not found",
                    'execution' => null,
                ];
            }
        }

        // Inject services
        $this->registry->inject_services($context);

        // Execute workflow
        $executor = $this->registry->get_executor();
        $result = $executor->execute($context, $workflow);

        // Commit changes if successful and auto_commit enabled
        $committed = false;
        if ($result->is_success() && $auto_commit && $context->has_changes()) {
            $committed = $context->commit();
        }

        return [
            'success' => $result->is_success(),
            'committed' => $committed,
            'pending_changes' => $context->get_pending_changes(),
            'execution' => $result->to_array(),
        ];
    }

    /**
     * Execute workflow on a context (generic).
     *
     * @param WorkflowContextInterface $context Pre-created context
     * @param array $workflow Workflow definition
     * @return WorkflowResult Execution result
     */
    public function run(WorkflowContextInterface $context, array $workflow): WorkflowResult
    {
        // Inject services if context supports it
        $this->registry->inject_services($context);

        // Execute workflow
        $executor = $this->registry->get_executor();
        return $executor->execute($context, $workflow);
    }

    /**
     * Validate workflow without executing.
     *
     * @param array $workflow Workflow definition
     * @param bool $for_virtual Whether to validate for virtual context
     * @return array Validation errors (empty if valid)
     */
    public function validate(array $workflow, bool $for_virtual = false): array
    {
        $executor = $this->registry->get_executor();

        $context = null;
        if ($for_virtual) {
            $context = VirtualWorkflowContext::create([], 'en', 'en');
        }

        return $executor->validate($workflow, $context);
    }

    /**
     * Get available steps for UI.
     *
     * @param bool $external_only Only return external-compatible steps
     * @return array Steps metadata
     */
    public function get_available_steps(bool $external_only = false): array
    {
        $steps = $this->registry->get_steps_for_ui();

        if ($external_only) {
            $steps = array_filter($steps, fn($s) => $s['external_compatible']);
        }

        return $steps;
    }

    /**
     * Check if a workflow is compatible with virtual context.
     *
     * @param array $workflow Workflow definition
     * @return array{compatible: bool, incompatible_steps: array}
     */
    public function check_virtual_compatibility(array $workflow): array
    {
        $executor = $this->registry->get_executor();
        $incompatible = [];

        foreach ($workflow['steps'] ?? [] as $index => $step_config) {
            $step_id = $step_config['type'] ?? $step_config['id'] ?? null;
            if (!$step_id) {
                continue;
            }

            $step = $executor->get_step($step_id);
            if ($step && !$step->is_external_compatible()) {
                $incompatible[] = [
                    'index' => $index,
                    'id' => $step_id,
                    'name' => $step->get_name(),
                ];
            }
        }

        return [
            'compatible' => empty($incompatible),
            'incompatible_steps' => $incompatible,
        ];
    }
}
