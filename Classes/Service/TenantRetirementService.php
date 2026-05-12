<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Service;

use LaborDigital\FactoryCore\Configuration\FactoryComponentRegistry;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Retires a tenant: deletes its pages (and content), be_groups, sys_filemounts
 * row, factory record-table rows under those pages, and the on-disk site
 * config + fileadmin directories. Best-effort — missing pieces don't fail
 * the operation, so this doubles as a cleanup tool for half-provisioned
 * tenants from earlier failed POST /tenants attempts.
 *
 * What it does NOT delete: the tenant-admin be_user row (might be shared
 * across tenants), and shared audit tables (sys_log, sys_history).
 *
 * See DL #016 — the API's DELETE /tenants/{slug} route wraps this.
 */
final class TenantRetirementService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @return array{pages: int, be_groups: int, sys_filemounts: int, record_rows: int, site_config_dir: bool, fileadmin_dir: bool}
     */
    public function retire(string $siteIdentifier): array
    {
        $this->bootstrapAdminBeUser();

        $rootPageUid = $this->resolveSiteRoot($siteIdentifier);
        $pageUids = $rootPageUid > 0 ? $this->collectPageTree($rootPageUid) : [];

        $deletedPages = $this->deletePages($pageUids);
        $deletedRecords = $this->deleteFactoryRecords($pageUids);
        $deletedGroups = $this->deleteBeGroups($siteIdentifier);
        $deletedMounts = $this->deleteFilemounts($siteIdentifier);
        $siteDir = $this->deleteSiteConfigDir($siteIdentifier);
        $fileDir = $this->deleteFileadminDir($siteIdentifier);

        // Pipeline-app + future ECS workers will re-read on next request.
        FactoryComponentRegistry::invalidate($siteIdentifier);

        return [
            'pages' => $deletedPages,
            'be_groups' => $deletedGroups,
            'sys_filemounts' => $deletedMounts,
            'record_rows' => $deletedRecords,
            'site_config_dir' => $siteDir,
            'fileadmin_dir' => $fileDir,
        ];
    }

    private function resolveSiteRoot(string $siteIdentifier): int
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            return $site->getRootPageId();
        } catch (\Throwable) {
            // Site config missing or unparseable — that's fine, retire is
            // best-effort. Return 0 so the page-tree step no-ops; we still
            // run the on-disk cleanup below.
            return 0;
        }
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
            throw new \RuntimeException('No admin user found in be_users — cannot retire tenant.');
        }
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $beUser->user = $admin;
        $beUser->workspace = 0;
        $GLOBALS['BE_USER'] = $beUser;
    }

    /** @return list<int> */
    private function collectPageTree(int $rootUid): array
    {
        $uids = [$rootUid];
        $stack = [$rootUid];
        while ($stack !== []) {
            $parent = array_pop($stack);
            $qb = $this->connectionPool->getQueryBuilderForTable('pages');
            $rows = $qb->select('uid')
                ->from('pages')
                ->where(
                    $qb->expr()->eq('pid', $qb->createNamedParameter($parent, \PDO::PARAM_INT)),
                    $qb->expr()->eq('deleted', 0),
                )
                ->executeQuery()
                ->fetchAllAssociative();
            foreach ($rows as $row) {
                $uid = (int)$row['uid'];
                $uids[] = $uid;
                $stack[] = $uid;
            }
        }
        return $uids;
    }

    /** @param list<int> $pageUids */
    private function deletePages(array $pageUids): int
    {
        if ($pageUids === []) return 0;

        // DataHandler cmdmap honours cascade rules + permission checks.
        // Delete deepest-first to avoid foreign-key chokes.
        $deepestFirst = array_reverse($pageUids);

        $cmdmap = ['pages' => []];
        foreach ($deepestFirst as $uid) {
            $cmdmap['pages'][$uid] = ['delete' => 1];
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmdmap);
        $dataHandler->process_cmdmap();

        // Don't throw on errors — best-effort. A missing record just means
        // it was already gone; that's the desired state.
        return count($pageUids);
    }

    /** @param list<int> $pageUids */
    private function deleteFactoryRecords(array $pageUids): int
    {
        if ($pageUids === []) return 0;

        $tables = [];
        foreach (FactoryComponentRegistry::discoverRecordTypes() as $type) {
            $table = $type['table'] ?? null;
            if (is_string($table) && $table !== '' && str_starts_with($table, 'tx_factorycore_')) {
                $tables[] = $table;
            }
        }
        $tables = array_unique($tables);
        if ($tables === []) return 0;

        $deleted = 0;
        $idList = implode(',', array_map('intval', $pageUids));
        foreach ($tables as $table) {
            try {
                $connection = $this->connectionPool->getConnectionForTable($table);
                $deleted += (int)$connection->executeStatement(
                    'DELETE FROM ' . $connection->quoteIdentifier($table) . ' WHERE pid IN (' . $idList . ')'
                );
            } catch (\Throwable) {
                // Table doesn't exist on this install — skip.
            }
        }
        return $deleted;
    }

    private function deleteBeGroups(string $siteIdentifier): int
    {
        $names = [
            'tenant_' . $siteIdentifier . '_editor',
            'tenant_' . $siteIdentifier . '_admin',
        ];
        $connection = $this->connectionPool->getConnectionForTable('be_groups');
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $deleted = (int)$connection->executeStatement(
            'DELETE FROM be_groups WHERE title IN (' . $placeholders . ')',
            $names
        );
        return $deleted;
    }

    private function deleteFilemounts(string $siteIdentifier): int
    {
        $name = 'tenant_' . $siteIdentifier;
        $connection = $this->connectionPool->getConnectionForTable('sys_filemounts');
        return (int)$connection->executeStatement(
            'DELETE FROM sys_filemounts WHERE title = ?',
            [$name]
        );
    }

    private function deleteSiteConfigDir(string $siteIdentifier): bool
    {
        $dir = Environment::getProjectPath() . '/config/sites/' . $siteIdentifier;
        if (!is_dir($dir)) return false;
        $this->rmrf($dir);
        return !is_dir($dir);
    }

    private function deleteFileadminDir(string $siteIdentifier): bool
    {
        $dir = Environment::getPublicPath() . '/fileadmin/' . $siteIdentifier;
        if (!is_dir($dir)) return false;
        $this->rmrf($dir);
        return !is_dir($dir);
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }
}
