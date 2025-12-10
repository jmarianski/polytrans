<?php

declare(strict_types=1);

/**
 * Architecture Tests: Provider System
 *
 * Ensures translation provider system is swappable
 */

arch('translation providers do not cross-depend')
    ->expect('PolyTrans\\Providers\\OpenAI')
    ->not()->toUse('PolyTrans\\Providers\\Google')
    ->and('PolyTrans\\Providers\\Google')
    ->not()->toUse('PolyTrans\\Providers\\OpenAI');

arch('providers are in dedicated namespace')
    ->expect('PolyTrans\\Providers')
    ->toOnlyUse([
        'PolyTrans\\Providers',
        'PolyTrans\\Core',
        'WP_Error',
        'WP_REST_Response',
        'WP_REST_Request',
        // Standard PHP/vendor
        'Psr',
        'GuzzleHttp',
        'OpenAI',
    ]);
