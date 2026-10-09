<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Adds CORS headers on the TYPO3 frontend so the headless Nuxt app
 * (running on a different origin — e.g. *.fly.dev or a customer
 * production domain) can fetch the TYPO3 API client-side without the
 * browser blocking the response.
 *
 * The allowed origin is read PER-REQUEST from the resolved site's
 * `frontendBase` config field — the same field friendsoftypo3/headless
 * uses for link generation. This makes multi-tenant safe: tenant A's
 * frontend can never read tenant B's TYPO3 responses, because each
 * site only whitelists its own frontendBase host.
 *
 * Registered after `typo3/cms-frontend/site` so the Site attribute is
 * already on the request when this runs. Single-tenant deployments
 * without a frontendBase configured fall through silently — no header
 * added, same-origin requests still work.
 *
 * OPTIONS preflights short-circuit with 204 + the same allow-headers
 * so browsers don't keep retrying.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = trim($request->getHeaderLine('Origin'));
        $allowedOrigin = $this->resolveAllowedOrigin($request);

        $originMatches = $origin !== '' && $allowedOrigin !== null && $origin === $allowedOrigin;

        if ($request->getMethod() === 'OPTIONS' && $originMatches) {
            return $this->withCorsHeaders(new Response('php://temp', 204), $allowedOrigin);
        }

        $response = $handler->handle($request);

        if ($originMatches) {
            $response = $this->withCorsHeaders($response, $allowedOrigin);
        }

        return $response;
    }

    private function resolveAllowedOrigin(ServerRequestInterface $request): ?string
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return null;
        }
        $frontendBase = trim((string)($site->getConfiguration()['frontendBase'] ?? ''));
        if ($frontendBase === '') {
            return null;
        }
        $parsed = parse_url($frontendBase);
        if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }
        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }
        return $origin;
    }

    private function withCorsHeaders(ResponseInterface $response, string $allowedOrigin): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');
    }
}
