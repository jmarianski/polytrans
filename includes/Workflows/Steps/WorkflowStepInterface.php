<?php

declare(strict_types=1);

namespace PolyTrans\Workflows\Steps;

use PolyTrans\Workflows\Context\WorkflowContextInterface;

/**
 * Interface for workflow steps.
 *
 * Workflow steps are pure transformations that operate on a context.
 * They should not have side effects outside of the context.
 *
 * @since 1.8.0
 */
interface WorkflowStepInterface
{
    /**
     * Get unique step identifier.
     *
     * @return string Step ID (e.g., 'ai_assistant', 'link_fixer')
     */
    public function get_id(): string;

    /**
     * Get human-readable step name.
     *
     * @return string Display name
     */
    public function get_name(): string;

    /**
     * Get step description.
     *
     * @return string Description of what this step does
     */
    public function get_description(): string;

    /**
     * Check if step is compatible with virtual (external) context.
     *
     * Virtual-compatible steps can operate on pure JSON without WordPress.
     *
     * @return bool True if works with VirtualWorkflowContext
     */
    public function is_external_compatible(): bool;

    /**
     * Get list of required services for this step.
     *
     * Step will be skipped if required services are not available.
     *
     * @return array<string> Service names (e.g., ['TaxonomyResolver'])
     */
    public function get_required_services(): array;

    /**
     * Get list of required data paths.
     *
     * Used for validation and UI hints.
     *
     * @return array<string> Dot-notation paths (e.g., ['post.content', 'post.title'])
     */
    public function get_required_paths(): array;

    /**
     * Execute the workflow step.
     *
     * Modifies the context in place. Should not have side effects outside context.
     *
     * @param WorkflowContextInterface $context Execution context
     * @param array $config Step configuration from workflow definition
     * @return void
     * @throws \Exception On unrecoverable error
     */
    public function execute(WorkflowContextInterface $context, array $config): void;

    /**
     * Validate step configuration.
     *
     * @param array $config Step configuration
     * @return array<string> List of validation error messages (empty if valid)
     */
    public function validate_config(array $config): array;
}
