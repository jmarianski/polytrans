<?php

declare(strict_types=1);

/**
 * Architecture Tests: Workflow System
 *
 * Ensures workflow system maintains clean architecture
 */

arch('workflow system has no circular dependencies')
    ->expect('PolyTrans\\Postprocessing\\Steps')
    ->not()->toUse('PolyTrans\\Postprocessing\\Managers\\Workflow_Manager')
    ->and('PolyTrans\\Postprocessing\\Triggers')
    ->not()->toUse('PolyTrans\\Postprocessing\\Managers\\Workflow_Manager');
