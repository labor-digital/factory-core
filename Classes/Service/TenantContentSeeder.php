<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Service;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Seeds tt_content rows onto a tenant's root page from an inline `elements`
 * array. The shape matches a seed.json's `elements`: each entry has a
 * kebab-case `component` slug + an override `data` map. Mirrors the seeding
 * logic of `ResetSeederCommand` but accepts data directly instead of reading
 * from a seed template file — so the API (and pipeline-app) can drive
 * seeding without the staging instance needing access to the seed library.
 *
 * The wipe step is a flat `DELETE FROM tt_content WHERE pid = ?` on the
 * tenant's root page only. Descendant pages stay untouched (we don't yet
 * seed multi-page tenants; v2 would extend this to a page-tree-shaped
 * payload).
 *
 * @see ResetSeederCommand for the pattern this is factored out of.
 */
final class TenantContentSeeder
{
    public function __construct(
        private readonly ContentBlockSeeder $contentBlockSeeder,
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @param list<array{component?: string, data?: array<string, mixed>}> $elements
     * @param list<array{title?: string, slug?: string, elements?: list<array{component?: string, data?: array<string, mixed>}>}> $pages
     * @return array{root_page_id: int, seeded: int, wiped: int, pages_seeded: int, pages_wiped: int}
     */
    public function seed(string $siteIdentifier, array $elements, bool $wipe = true, array $pages = []): array
    {
        $this->bootstrapAdminBeUser();

        $rootPageUid = $this->resolveSiteRoot($siteIdentifier);

        $wiped = 0;
        $pagesWiped = 0;
        if ($wipe) {
            $wiped = $this->wipeRootContent($rootPageUid);
            // Only purge descendants when the caller actually supplies a
            // pages array. Without this guard a "wipe just content" call
            // would also nuke any subpages the operator may have created
            // manually in the BE.
            if ($pages !== []) {
                $pagesWiped = $this->wipeDescendantPages($rootPageUid);
            }
        }

        $seeded = $this->seedElements($rootPageUid, $elements);
        $pagesSeeded = $this->seedSubpages($rootPageUid, $pages);

        return [
            'root_page_id' => $rootPageUid,
            'seeded' => $seeded,
            'wiped' => $wiped,
            'pages_seeded' => $pagesSeeded,
            'pages_wiped' => $pagesWiped,
        ];
    }

    /**
     * @param list<array{title?: string, slug?: string, elements?: list<array{component?: string, data?: array<string, mixed>}>}> $pages
     */
    private function seedSubpages(int $rootPageUid, array $pages): int
    {
        if ($pages === []) {
            return 0;
        }

        $created = 0;
        $sorting = 256;
        foreach ($pages as $index => $page) {
            $title = is_string($page['title'] ?? null) ? trim((string)$page['title']) : '';
            $slug = is_string($page['slug'] ?? null) ? trim((string)$page['slug']) : '';
            if ($title === '' || $slug === '') {
                continue;
            }
            $newId = 'NEW_subpage_' . $index . '_' . preg_replace('/[^a-z0-9]+/i', '_', $slug);
            $data = [
                'pages' => [
                    $newId => [
                        'pid' => $rootPageUid,
                        'title' => $title,
                        // DataHandler accepts a leading "/" — TYPO3 normalises
                        // (per-language base prefix is applied at routing time).
                        'slug' => '/' . ltrim($slug, '/'),
                        'doktype' => 1,
                        'is_siteroot' => 0,
                        'hidden' => 0,
                        'sys_language_uid' => 0,
                        'sorting' => $sorting,
                    ],
                ],
            ];
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();
            if (!empty($dataHandler->errorLog)) {
                throw new \RuntimeException(
                    'TenantContentSeeder: failed creating subpage "' . $slug . '": ' . implode(', ', $dataHandler->errorLog)
                );
            }
            $newPageUid = (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
            if ($newPageUid <= 0) {
                continue;
            }
            $childElements = is_array($page['elements'] ?? null) ? $page['elements'] : [];
            if ($childElements !== []) {
                $this->seedElements($newPageUid, $childElements);
            }
            $sorting += 256;
            $created++;
        }
        return $created;
    }

    private function wipeDescendantPages(int $rootPageUid): int
    {
        // Collect every descendant uid via BFS, then submit a single
        // DataHandler cmdmap (deepest-first) so TCA cascade rules fire for
        // each. DataHandler also takes care of removing tt_content rows
        // tied to the deleted pages.
        $descendants = [];
        $stack = [$rootPageUid];
        while ($stack !== []) {
            $parent = array_pop($stack);
            $qb = $this->connectionPool->getQueryBuilderForTable('pages');
            $rows = $qb->select('uid')
                ->from('pages')
                ->where(
                    $qb->expr()->eq('pid', $qb->createNamedParameter($parent, \Doctrine\DBAL\ParameterType::INTEGER)),
                    $qb->expr()->eq('deleted', 0),
                )
                ->executeQuery()
                ->fetchAllAssociative();
            foreach ($rows as $row) {
                $uid = (int)$row['uid'];
                $descendants[] = $uid;
                $stack[] = $uid;
            }
        }
        if ($descendants === []) {
            return 0;
        }

        $cmdmap = ['pages' => []];
        foreach (array_reverse($descendants) as $uid) {
            $cmdmap['pages'][$uid] = ['delete' => 1];
        }
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmdmap);
        $dataHandler->process_cmdmap();
        // Best-effort — leftovers from manual BE edits aren't fatal.
        return count($descendants);
    }

    private function resolveSiteRoot(string $siteIdentifier): int
    {
        // Read config.yaml directly instead of going through SiteFinder.
        // The site was almost certainly created by a `POST /tenants` in the
        // immediately-previous HTTP request; TYPO3's site cache is populated
        // across requests and was warmed before the new site existed, so
        // SiteFinder::getSiteByIdentifier returns "site not found" even
        // though the file is on disk. A direct YAML read avoids the entire
        // cache layer — one extra fopen, no staleness window.
        //
        // Fall back to SiteFinder if the file isn't where we expect, since
        // some installs may relocate config/sites/.
        $configPath = Environment::getProjectPath() . '/config/sites/' . $siteIdentifier . '/config.yaml';
        if (is_file($configPath)) {
            try {
                $config = Yaml::parseFile($configPath);
                $rootUid = (int)($config['rootPageId'] ?? 0);
                if ($rootUid > 0) {
                    return $rootUid;
                }
            } catch (\Throwable) {
                // fall through to SiteFinder
            }
        }

        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Site "%s" not found.', $siteIdentifier), 0, $e);
        }
        $rootUid = $site->getRootPageId();
        if ($rootUid <= 0) {
            throw new \RuntimeException(sprintf('Site "%s" has no root page configured.', $siteIdentifier));
        }
        return $rootUid;
    }

    private function bootstrapAdminBeUser(): void
    {
        if (isset($GLOBALS['BE_USER']) && !empty($GLOBALS['BE_USER']->user['uid'])) {
            return;
        }
        $qb = $this->connectionPool->getQueryBuilderForTable('be_users');
        $admin = $qb->select('*')
            ->from('be_users')
            ->where($qb->expr()->eq('admin', 1))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if (!$admin) {
            throw new \RuntimeException('No admin user found in be_users. Provision a tenant or run setup first.');
        }
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $beUser->user = $admin;
        $beUser->workspace = 0;
        $GLOBALS['BE_USER'] = $beUser;
    }

    private function wipeRootContent(int $rootPageUid): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tt_content');
        return (int)$connection->executeStatement(
            'DELETE FROM tt_content WHERE pid = ' . (int)$rootPageUid
        );
    }

    /**
     * @param list<array{component?: string, data?: array<string, mixed>}> $elements
     */
    private function seedElements(int $rootPageUid, array $elements): int
    {
        if ($elements === []) {
            return 0;
        }

        // Reverse so default DataHandler sort (newest-on-top) yields the
        // operator's intended order. Matches InitSeederCommand /
        // ResetSeederCommand convention.
        $elements = array_reverse($elements);

        $data = [];
        $sorting = 256;
        $componentMeta = [];

        foreach ($elements as $index => $element) {
            $slug = $element['component'] ?? '';
            $overrideData = is_array($element['data'] ?? null) ? $element['data'] : [];
            if (!is_string($slug) || $slug === '') {
                continue;
            }

            $directoryName = $this->contentBlockSeeder->toDirectoryName($slug);
            $newId = 'NEW_tenant_content_' . $index . '_' . $directoryName;

            $record = $this->contentBlockSeeder->buildDataHandlerRecordWithOverrides(
                $directoryName,
                $rootPageUid,
                $sorting,
                $newId,
                $overrideData,
            );
            if ($record === null) {
                continue;
            }
            foreach ($record as $table => $rows) {
                $data[$table] = array_merge($data[$table] ?? [], $rows);
            }
            $componentMeta[$newId] = [
                'directory' => $directoryName,
                'data' => $overrideData,
            ];
            $sorting += 256;
        }

        if ($data === []) {
            return 0;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException(
                'TenantContentSeeder: DataHandler errors: ' . implode(', ', $dataHandler->errorLog)
            );
        }

        foreach ($componentMeta as $newId => $meta) {
            $parentUid = (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
            if ($parentUid > 0) {
                $this->contentBlockSeeder->insertCollectionChildrenWithOverrides(
                    $meta['directory'],
                    $parentUid,
                    $rootPageUid,
                    $meta['data'],
                );
            }
        }

        return count($componentMeta);
    }
}
