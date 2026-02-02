<?php

declare(strict_types=1);

namespace PolyTrans\Tests\Unit\Workflows;

use PolyTrans\Workflows\Context\VirtualWorkflowContext;
use PolyTrans\Workflows\WorkflowExecutor;
use PolyTrans\Workflows\Steps\AbstractWorkflowStep;
use PolyTrans\Workflows\Context\WorkflowContextInterface;

// Simple test step that uppercases title
class UppercaseTitleStep extends AbstractWorkflowStep
{
    public function get_id(): string
    {
        return 'uppercase_title';
    }

    public function get_name(): string
    {
        return 'Uppercase Title';
    }

    public function get_required_paths(): array
    {
        return ['post.title'];
    }

    public function execute(WorkflowContextInterface $context, array $config): void
    {
        $title = $context->get('post.title');
        $context->set('post.title', strtoupper($title));
    }
}

// Step that requires a service
class ServiceDependentStep extends AbstractWorkflowStep
{
    public function get_id(): string
    {
        return 'service_dependent';
    }

    public function get_name(): string
    {
        return 'Service Dependent';
    }

    public function get_required_services(): array
    {
        return ['SomeService'];
    }

    public function execute(WorkflowContextInterface $context, array $config): void
    {
        // Would use service
    }
}

// Step not compatible with virtual context
class DatabaseOnlyStep extends AbstractWorkflowStep
{
    public function get_id(): string
    {
        return 'database_only';
    }

    public function get_name(): string
    {
        return 'Database Only';
    }

    public function is_external_compatible(): bool
    {
        return false;
    }

    public function execute(WorkflowContextInterface $context, array $config): void
    {
        // Would access database
    }
}

// Step that throws exception
class FailingStep extends AbstractWorkflowStep
{
    public function get_id(): string
    {
        return 'failing';
    }

    public function get_name(): string
    {
        return 'Failing Step';
    }

    public function execute(WorkflowContextInterface $context, array $config): void
    {
        throw new \RuntimeException('Step failed intentionally');
    }
}

describe('WorkflowExecutor', function () {

    it('executes simple workflow', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new UppercaseTitleStep());

        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'hello world'],
        ], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'uppercase_title'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->is_success())->toBeTrue();
        expect($result->get_executed_count())->toBe(1);
        expect($context->get('post.title'))->toBe('HELLO WORLD');
    });

    it('skips unknown steps', function () {
        $executor = new WorkflowExecutor();

        $context = VirtualWorkflowContext::create([], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'nonexistent_step'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->is_success())->toBeFalse();
        expect($result->get_error_count())->toBe(1);
        expect($result->get_errors()[0]['message'])->toContain('not registered');
    });

    it('skips steps missing required services', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new ServiceDependentStep());

        $context = VirtualWorkflowContext::create([], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'service_dependent'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->is_success())->toBeTrue(); // Skipped, not failed
        expect($result->get_skipped_count())->toBe(1);
        expect($result->get_skipped()[0]['reason'])->toContain('SomeService');
    });

    it('skips database-only steps in virtual context', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new DatabaseOnlyStep());

        $context = VirtualWorkflowContext::create([], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'database_only'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->is_success())->toBeTrue();
        expect($result->get_skipped_count())->toBe(1);
        expect($result->get_skipped()[0]['reason'])->toContain('virtual');
    });

    it('skips steps missing required paths', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new UppercaseTitleStep());

        $context = VirtualWorkflowContext::create([
            'post' => ['content' => 'no title here'],
        ], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'uppercase_title'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->is_success())->toBeTrue();
        expect($result->get_skipped_count())->toBe(1);
        expect($result->get_skipped()[0]['reason'])->toContain('post.title');
    });

    it('handles step exceptions', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new FailingStep());

        $context = VirtualWorkflowContext::create([], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'failing'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->is_success())->toBeFalse();
        expect($result->get_error_count())->toBe(1);
        expect($result->get_errors()[0]['message'])->toContain('intentionally');
    });

    it('continues after error by default', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new FailingStep());
        $executor->register_step(new UppercaseTitleStep());

        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'test'],
        ], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'failing'],
                ['type' => 'uppercase_title'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->get_error_count())->toBe(1);
        expect($result->get_executed_count())->toBe(1);
        expect($context->get('post.title'))->toBe('TEST');
    });

    it('stops on error when configured', function () {
        $executor = new WorkflowExecutor();
        $executor->set_continue_on_error(false);
        $executor->register_step(new FailingStep());
        $executor->register_step(new UppercaseTitleStep());

        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'test'],
        ], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'failing'],
                ['type' => 'uppercase_title'],
            ],
        ];

        $result = $executor->execute($context, $workflow);

        expect($result->get_error_count())->toBe(1);
        expect($result->get_executed_count())->toBe(0);
        expect($context->get('post.title'))->toBe('test'); // Unchanged
    });

    it('validates workflow definition', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new UppercaseTitleStep());
        $executor->register_step(new DatabaseOnlyStep());

        $context = VirtualWorkflowContext::create([], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'uppercase_title'],
                ['type' => 'database_only'],
                ['type' => 'unknown_step'],
            ],
        ];

        $errors = $executor->validate($workflow, $context);

        expect($errors)->toHaveCount(2);
        expect($errors[0])->toContain('database_only');
        expect($errors[1])->toContain('unknown_step');
    });

    it('returns external compatible steps', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new UppercaseTitleStep());
        $executor->register_step(new DatabaseOnlyStep());
        $executor->register_step(new ServiceDependentStep());

        $compatible = $executor->get_external_compatible_steps();

        expect($compatible)->toHaveCount(2);
        expect($compatible)->toHaveKey('uppercase_title');
        expect($compatible)->toHaveKey('service_dependent');
        expect($compatible)->not->toHaveKey('database_only');
    });

    it('exports result to array', function () {
        $executor = new WorkflowExecutor();
        $executor->register_step(new UppercaseTitleStep());

        $context = VirtualWorkflowContext::create([
            'post' => ['title' => 'test'],
        ], 'en', 'de');

        $workflow = [
            'steps' => [
                ['type' => 'uppercase_title'],
            ],
        ];

        $result = $executor->execute($context, $workflow);
        $array = $result->to_array();

        expect($array['success'])->toBeTrue();
        expect($array['stats']['executed'])->toBe(1);
        expect($array['stats']['skipped'])->toBe(0);
        expect($array['stats']['errors'])->toBe(0);
    });

});
