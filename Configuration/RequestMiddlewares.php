<?php

declare(strict_types=1);

use LaborDigital\FactoryCore\Middleware\CorsMiddleware;
use LaborDigital\FactoryCore\Middleware\RecordPageMiddleware;

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
        /*
         * Serves a record's own page at /<type>/<slug> (DL #030). Rewrites the
         * request to the record type's page and attaches the resolved record, so
         * everything downstream is ordinary page rendering.
         *
         * Must run AFTER `site` (needs the Site + language attributes to strip
         * the language base) and BEFORE `page-resolver`, which is what turns a
         * path into a page — by then the rewrite would be too late.
         */
        'labor-digital/factory-core/record-page' => [
            'target' => RecordPageMiddleware::class,
            'after' => ['typo3/cms-frontend/base-redirect-resolver'],
            'before' => ['typo3/cms-frontend/page-resolver'],
        ],
    ],
];
