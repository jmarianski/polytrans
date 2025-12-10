<?php

declare(strict_types=1);

/**
 * Architecture Tests: Naming Conventions
 *
 * Enforces consistent naming across the codebase (basic structure validation)
 */

arch('no dangerous global functions are used')
    ->expect('PolyTrans')
    ->not()->toUse([
        'eval',
        'create_function',
    ]);
