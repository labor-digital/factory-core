<?php

declare(strict_types=1);

use LaborDigital\FactoryCore\Middleware\CorsMiddleware;

/**
 * factory-core registers a CORS middleware on the frontend chain so the
 * headless Nuxt frontend (different origin) can fetch the TYPO3 API
 * client-side. Allowed origins are read per-request from the resolved
 * site's `frontendBase` — multi-tenant safe.
 *
 * Runs after `typo3/cms-frontend/site` so the Site attribute is on the
 * request when the middleware needs to read its `frontendBase`. Runs
 * before `typo3/cms-frontend/tsfe` so OPTIONS preflights short-circuit
 * before page rendering kicks in (no need to build a TSFE for a CORS
 * preflight that will return 204).
 */
return [
    'frontend' => [
        'labor-digital/factory-core/cors' => [
            'target' => CorsMiddleware::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/tsfe'],
        ],
    ],
];
