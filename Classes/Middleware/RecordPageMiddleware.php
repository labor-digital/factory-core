<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Middleware;

use LaborDigital\FactoryCore\Service\ContentBlockSeeder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Serves a record's own page at `/<type>/<slug>` (DL #030).
 *
 * A record is a page: it has a slug and renders with every content element
 * available. In CMS-free mode the ai-layer owns that route; in TYPO3 mode
 * nothing served it at all, so `/property/haus-am-see` 404'd — DL #010 deferred
 * this and it stayed deferred (todos.md:408).
 *
 * ## How
 *
 * The frontend needs no new route: `@t3headless/nuxt-typo3` registers a
 * catch-all that fetches whatever path the browser is on, so once the API
 * answers `/property/haus-am-see`, the existing renderer draws it. That makes
 * this a backend-only concern.
 *
 * This middleware translates the record URL into a page request the rest of
 * TYPO3 already understands:
 *
 *   1. match `/<type>/<slug>` against the site's ACTIVE record types
 *   2. look the record up by slug in that type's table
 *   3. rewrite the request URI to `/<type>` — the type's list+detail page,
 *      created by the seeder — and attach the resolved uid to the request
 *
 * `RecordDetailProcessor` then reads that attribute. Everything downstream is
 * ordinary page rendering, so headData, navigation, breadcrumbs and caching all
 * come from TYPO3 for free.
 *
 * ## Why not a route enhancer
 *
 * The idiomatic TYPO3 answer is `routeEnhancers` + `PersistedAliasMapper`, and
 * DL #030 originally specified it. Two things argued against it here:
 *
 *   - An enhancer must be declared in the SITE configuration, per site. Injecting
 *     one per active record type via `SiteConfigurationLoadedEvent` means a DB
 *     lookup (to resolve `limitToPages`) while site config is being read and
 *     cached — early, and in contexts where the DB may not be ready. Omitting
 *     `limitToPages` instead makes a one-segment enhancer compete with every
 *     normal page path.
 *   - Enhancers exist mainly so TYPO3 can also GENERATE these URLs. Nothing here
 *     needs that: the headless frontend builds record URLs itself
 *     (`/${type}/${slug}`), on both data sources.
 *
 * So this trades URL generation — which is unused — for zero per-tenant
 * configuration. Revisit if TYPO3 ever needs to typolink to a record.
 *
 * Registered after `typo3/cms-frontend/page-resolver` would be too late (the page
 * is already resolved), so it runs BEFORE it, and after `site` so the Site
 * attribute is available.
 */
final class RecordPageMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'factoryRecord';

    public function __construct(
        private readonly ContentBlockSeeder $seeder,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $resolved = $this->resolveRecord($request);
        if ($resolved === null) {
            return $handler->handle($request);
        }

        [$typeSlug, $table, $uid, $listPath] = $resolved;

        $uri = $request->getUri()->withPath($listPath);

        return $handler->handle(
            $request
                ->withUri($uri)
                // Keep the ORIGINAL path: the record page's canonical URL is the
                // one the visitor asked for, not the list page we rewrote to.
                ->withAttribute(self::ATTRIBUTE, [
                    'type' => $typeSlug,
                    'table' => $table,
                    'uid' => $uid,
                    'requestedPath' => $request->getUri()->getPath(),
                ])
        );
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: string}|null
     *         [type slug, table, record uid, list page path]
     */
    private function resolveRecord(ServerRequestInterface $request): ?array
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return null;
        }

        // Strip the site's language/base path so `/de/news/foo` matches too.
        $language = $request->getAttribute('language');
        $basePath = $language !== null ? rtrim($language->getBase()->getPath(), '/') : '';
        $path = $request->getUri()->getPath();
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $s): bool => $s !== ''));
        if (count($segments) !== 2) {
            return null;
        }
        [$typeSegment, $recordSlug] = $segments;

        // Only ACTIVE record types are candidates, so a normal two-segment page
        // path (/about/team) is never intercepted unless it collides with a
        // record type's slug — and then the slug lookup below still has to match.
        $table = null;
        foreach ($this->seeder->getActiveRecordTypes() as $name) {
            if ($this->seeder->toRecordSlug($name) === $typeSegment) {
                $table = $this->seeder->resolveRecordTable($this->seeder->toRecordDirectory($name));
                break;
            }
        }
        if ($table === null) {
            return null;
        }

        $uid = $this->findRecordUid($table, $recordSlug);
        if ($uid === null) {
            // An unknown slug under a known type must 404 as a page would, not
            // silently render the list — so fall through with the path intact.
            return null;
        }

        return [$typeSegment, $table, $uid, ($basePath !== '' ? $basePath : '') . '/' . $typeSegment];
    }

    private function findRecordUid(string $table, string $slug): ?int
    {
        try {
            $qb = $this->connectionPool->getQueryBuilderForTable($table);
            $uid = $qb->select('uid')
                ->from($table)
                ->where($qb->expr()->eq('slug', $qb->createNamedParameter($slug)))
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne();
        } catch (\Throwable) {
            // A record table that does not exist yet (extension installed, DB
            // not analysed) must not break every frontend request.
            return null;
        }

        return is_numeric($uid) && (int)$uid > 0 ? (int)$uid : null;
    }
}
