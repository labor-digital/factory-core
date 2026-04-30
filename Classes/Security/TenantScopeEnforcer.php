<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Security;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Exception as ResourceException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * DataHandler hook enforcing tenant-scope guardrails in the shared-tenant
 * TYPO3 instance. Backs up the UI-level TSconfig / mount restrictions so a
 * crafted DataHandler call cannot cross tenant boundaries.
 *
 * Global admins (be_users.admin = 1) bypass every check.
 *
 * Covers:
 *  - be_users / be_groups writes (no admin escalation, no foreign-group
 *    assignment, no foreign db_mountpoints / file_mountpoints)
 *  - sys_file_reference writes (uid_local must resolve to a file inside one
 *    of the actor's filemounts)
 *  - sys_redirect writes (source_host must belong to one of the actor's sites)
 *  - any other content record: target pid (and current pid for updates)
 *    must be inside the actor's db_mountpoints rootline
 *  - cmdmap commands (delete/copy/move) on any of the above: same scope
 *
 * Registered in ext_localconf.php under processDatamapClass / processCmdmapClass.
 */
final class TenantScopeEnforcer
{
    private const TABLES_SKIP = [
        'sys_log' => true,
        'sys_history' => true,
        'sys_registry' => true,
        'sys_category' => true,
        'sys_file' => true,
        'sys_file_metadata' => true,
    ];

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly ResourceFactory $resourceFactory,
    ) {}

    /**
     * @param array<string,mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        int|string $id,
        DataHandler $dataHandler
    ): void {
        $actor = $dataHandler->BE_USER ?? null;
        if (!$actor instanceof BackendUserAuthentication || $actor->isAdmin()) {
            return;
        }
        if (isset(self::TABLES_SKIP[$table])) {
            return;
        }

        switch ($table) {
            case 'be_users':
                $this->guardBeUsersWrite($incomingFieldArray, $id, $actor, $dataHandler);
                return;
            case 'be_groups':
                $this->guardBeGroupsWrite($incomingFieldArray, $id, $actor, $dataHandler);
                return;
            case 'sys_file_reference':
                $this->guardSysFileReferenceWrite($incomingFieldArray, $id, $actor, $dataHandler);
                // sys_file_reference also has a pid — continue through to the generic check
                break;
            case 'sys_redirect':
                $this->guardSysRedirectWrite($incomingFieldArray, $id, $actor, $dataHandler);
                return;
        }

        if ($incomingFieldArray !== []) {
            $this->guardRecordPidScope($incomingFieldArray, $table, $id, $actor, $dataHandler);
        }
    }

    /**
     * cmdmap gate for delete / copy / move. Sets $commandIsProcessed when we
     * block, so DataHandler skips its own dispatch.
     */
    public function processCmdmap(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        bool &$commandIsProcessed,
        DataHandler $dataHandler,
        mixed $pasteUpdate
    ): ?array {
        $actor = $dataHandler->BE_USER ?? null;
        if (!$actor instanceof BackendUserAuthentication || $actor->isAdmin()) {
            return null;
        }
        if (isset(self::TABLES_SKIP[$table]) || !$this->isExistingRecord($id)) {
            return null;
        }

        $blocked = match ($table) {
            'be_users' => $this->beUserOutsideScope((int)$id, $actor),
            'be_groups' => !isset($this->resolveActorGroupScope($actor)[(int)$id]),
            'sys_redirect' => !$this->sysRedirectInScope((int)$id, $actor),
            default => !$this->recordPidInScope($table, (int)$id, $actor),
        };

        if ($blocked) {
            $commandIsProcessed = true;
            $dataHandler->log(
                $table,
                (int)$id,
                SystemLogDatabaseAction::DELETE,
                null,
                SystemLogErrorClassification::USER_ERROR,
                'TenantScopeEnforcer rejected cmdmap `' . $command . '` on ' . $table . ' #' . $id
            );
        }
        return null;
    }

    /**
     * @param array<string,mixed> $fieldArray
     */
    private function guardBeUsersWrite(
        array &$fieldArray,
        int|string $id,
        BackendUserAuthentication $actor,
        DataHandler $dataHandler
    ): void {
        if (array_key_exists('admin', $fieldArray) && (int)$fieldArray['admin'] === 1) {
            $this->reject($fieldArray, $dataHandler, 'be_users', $id, 'non-admin cannot set admin=1');
            return;
        }

        $actorGroupScope = $this->resolveActorGroupScope($actor);

        if (array_key_exists('usergroup', $fieldArray)) {
            foreach ($this->parseIntCsv($fieldArray['usergroup']) as $gid) {
                if (!isset($actorGroupScope[$gid])) {
                    $this->reject($fieldArray, $dataHandler, 'be_users', $id, "usergroup {$gid} outside tenant scope");
                    return;
                }
            }
        }

        if (array_key_exists('db_mountpoints', $fieldArray)) {
            $actorPageMounts = $this->resolveActorPageMounts($actor);
            foreach ($this->parseIntCsv($fieldArray['db_mountpoints']) as $pageUid) {
                if (!isset($actorPageMounts[$pageUid])) {
                    $this->reject($fieldArray, $dataHandler, 'be_users', $id, "db_mountpoint {$pageUid} outside tenant scope");
                    return;
                }
            }
        }

        if (array_key_exists('file_mountpoints', $fieldArray)) {
            $actorFileMounts = $this->resolveActorFileMounts($actor);
            foreach ($this->parseIntCsv($fieldArray['file_mountpoints']) as $fmUid) {
                if (!isset($actorFileMounts[$fmUid])) {
                    $this->reject($fieldArray, $dataHandler, 'be_users', $id, "file_mountpoint {$fmUid} outside tenant scope");
                    return;
                }
            }
        }

        if ($this->isExistingRecord($id)) {
            $existing = BackendUtility::getRecord('be_users', (int)$id, 'usergroup,admin');
            if (is_array($existing)) {
                if ((int)($existing['admin'] ?? 0) === 1) {
                    $this->reject($fieldArray, $dataHandler, 'be_users', $id, 'tenant admins cannot edit global admins');
                    return;
                }
                foreach ($this->parseIntCsv($existing['usergroup'] ?? '') as $gid) {
                    if (!isset($actorGroupScope[$gid])) {
                        $this->reject($fieldArray, $dataHandler, 'be_users', $id, "existing user member of foreign group {$gid}");
                        return;
                    }
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $fieldArray
     */
    private function guardBeGroupsWrite(
        array &$fieldArray,
        int|string $id,
        BackendUserAuthentication $actor,
        DataHandler $dataHandler
    ): void {
        if (!$this->isExistingRecord($id)) {
            $this->reject($fieldArray, $dataHandler, 'be_groups', $id, 'tenant admins cannot create new be_groups');
            return;
        }

        $actorGroupScope = $this->resolveActorGroupScope($actor);
        if (!isset($actorGroupScope[(int)$id])) {
            $this->reject($fieldArray, $dataHandler, 'be_groups', $id, "group {$id} outside tenant scope");
            return;
        }

        if (array_key_exists('subgroup', $fieldArray)) {
            foreach ($this->parseIntCsv($fieldArray['subgroup']) as $gid) {
                if (!isset($actorGroupScope[$gid])) {
                    $this->reject($fieldArray, $dataHandler, 'be_groups', $id, "subgroup {$gid} outside tenant scope");
                    return;
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $fieldArray
     */
    private function guardSysFileReferenceWrite(
        array &$fieldArray,
        int|string $id,
        BackendUserAuthentication $actor,
        DataHandler $dataHandler
    ): void {
        if (!array_key_exists('uid_local', $fieldArray)) {
            return;
        }
        $uids = $this->parseIntCsv($fieldArray['uid_local']);
        if ($uids === []) {
            return;
        }
        $actorFileMountPaths = $this->resolveActorFileMountPaths($actor);
        foreach ($uids as $uid) {
            if (!$this->fileInsideActorMounts($uid, $actorFileMountPaths)) {
                $this->reject($fieldArray, $dataHandler, 'sys_file_reference', $id, "sys_file #{$uid} outside tenant filemounts");
                return;
            }
        }
    }

    /**
     * @param array<string,mixed> $fieldArray
     */
    private function guardSysRedirectWrite(
        array &$fieldArray,
        int|string $id,
        BackendUserAuthentication $actor,
        DataHandler $dataHandler
    ): void {
        $host = $fieldArray['source_host'] ?? null;
        if ($host === null || $host === '') {
            return;
        }
        $actorHosts = $this->resolveActorSiteHosts($actor);
        if (!isset($actorHosts[(string)$host]) && !isset($actorHosts['*'])) {
            $this->reject($fieldArray, $dataHandler, 'sys_redirect', $id, "source_host `{$host}` not owned by any of the actor's sites");
        }
    }

    /**
     * @param array<string,mixed> $fieldArray
     */
    private function guardRecordPidScope(
        array &$fieldArray,
        string $table,
        int|string $id,
        BackendUserAuthentication $actor,
        DataHandler $dataHandler
    ): void {
        $actorPageMounts = $this->resolveActorPageMounts($actor);
        if ($actorPageMounts === []) {
            return;
        }

        if (array_key_exists('pid', $fieldArray)) {
            $targetPid = (int)$fieldArray['pid'];
            if ($targetPid !== 0 && !$this->pidInMounts($targetPid, $actorPageMounts)) {
                $this->reject($fieldArray, $dataHandler, $table, $id, "target pid {$targetPid} outside tenant scope");
                return;
            }
        }

        if ($this->isExistingRecord($id)) {
            if (!$this->recordPidInScope($table, (int)$id, $actor, $actorPageMounts)) {
                $this->reject($fieldArray, $dataHandler, $table, $id, "existing record #{$id} outside tenant scope");
                return;
            }
        }
    }

    /** @param array<int,true> $actorPageMounts */
    private function pidInMounts(int $pid, array $actorPageMounts): bool
    {
        if ($pid === 0) {
            return false;
        }
        $rootline = BackendUtility::BEgetRootLine($pid, '', true);
        foreach ($rootline as $page) {
            $uid = (int)($page['uid'] ?? 0);
            if ($uid !== 0 && isset($actorPageMounts[$uid])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,true>|null $actorPageMounts */
    private function recordPidInScope(string $table, int $uid, BackendUserAuthentication $actor, ?array $actorPageMounts = null): bool
    {
        $record = BackendUtility::getRecord($table, $uid, 'pid');
        if (!is_array($record)) {
            return true; // record doesn't exist — let DataHandler decide
        }
        $pid = (int)($record['pid'] ?? 0);
        if ($pid === 0) {
            return true; // root-level — separate concern; page permissions handle it
        }
        return $this->pidInMounts($pid, $actorPageMounts ?? $this->resolveActorPageMounts($actor));
    }

    private function beUserOutsideScope(int $uid, BackendUserAuthentication $actor): bool
    {
        $existing = BackendUtility::getRecord('be_users', $uid, 'usergroup,admin');
        if (!is_array($existing)) {
            return false;
        }
        if ((int)($existing['admin'] ?? 0) === 1) {
            return true;
        }
        $actorGroupScope = $this->resolveActorGroupScope($actor);
        foreach ($this->parseIntCsv($existing['usergroup'] ?? '') as $gid) {
            if (!isset($actorGroupScope[$gid])) {
                return true;
            }
        }
        return false;
    }

    private function sysRedirectInScope(int $uid, BackendUserAuthentication $actor): bool
    {
        $record = BackendUtility::getRecord('sys_redirect', $uid, 'source_host');
        if (!is_array($record)) {
            return true;
        }
        $host = (string)($record['source_host'] ?? '');
        if ($host === '') {
            return true;
        }
        $hosts = $this->resolveActorSiteHosts($actor);
        return isset($hosts[$host]) || isset($hosts['*']);
    }

    /** @return array<int,true> */
    private function resolveActorGroupScope(BackendUserAuthentication $actor): array
    {
        $scope = [];
        foreach ($actor->userGroupsUID ?? [] as $gid) {
            $scope[(int)$gid] = true;
        }
        return $scope;
    }

    /** @return array<int,true> */
    private function resolveActorPageMounts(BackendUserAuthentication $actor): array
    {
        $mounts = [];
        foreach ($actor->getWebmounts() as $uid) {
            $mounts[(int)$uid] = true;
        }
        return $mounts;
    }

    /** @return array<int,true> */
    private function resolveActorFileMounts(BackendUserAuthentication $actor): array
    {
        $mounts = [];
        foreach (GeneralUtility::intExplode(',', (string)($actor->groupData['file_mountpoints'] ?? ''), true) as $uid) {
            $mounts[(int)$uid] = true;
        }
        return $mounts;
    }

    /**
     * @return list<array{storage:int,path:string}>
     */
    private function resolveActorFileMountPaths(BackendUserAuthentication $actor): array
    {
        $paths = [];
        foreach ($actor->getFileMountRecords() as $record) {
            $identifier = (string)($record['identifier'] ?? '');
            if ($identifier === '' || !str_contains($identifier, ':')) {
                continue;
            }
            [$storage, $path] = explode(':', $identifier, 2);
            $paths[] = ['storage' => (int)$storage, 'path' => '/' . trim($path, '/') . '/'];
        }
        return $paths;
    }

    /**
     * @param list<array{storage:int,path:string}> $mountPaths
     */
    private function fileInsideActorMounts(int $sysFileUid, array $mountPaths): bool
    {
        if ($mountPaths === []) {
            return false;
        }
        try {
            $file = $this->resourceFactory->getFileObject($sysFileUid);
        } catch (ResourceException) {
            return false;
        }
        $storageUid = (int)$file->getStorage()->getUid();
        $identifier = '/' . ltrim($file->getIdentifier(), '/');
        foreach ($mountPaths as $mount) {
            if ($mount['storage'] === $storageUid && str_starts_with($identifier, $mount['path'])) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,true> */
    private function resolveActorSiteHosts(BackendUserAuthentication $actor): array
    {
        $hosts = [];
        $mounts = $actor->getWebmounts();
        foreach ($this->siteFinder->getAllSites() as $site) {
            if (!$this->siteCoveredByMounts($site, $mounts)) {
                continue;
            }
            $host = (string)($site->getBase()->getHost());
            if ($host !== '') {
                $hosts[$host] = true;
            }
        }
        return $hosts;
    }

    /** @param list<int> $mounts */
    private function siteCoveredByMounts(Site $site, array $mounts): bool
    {
        $root = (int)$site->getRootPageId();
        foreach ($mounts as $uid) {
            if ((int)$uid === $root) {
                return true;
            }
        }
        return false;
    }

    /** @return list<int> */
    private function parseIntCsv(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', array_filter($value, static fn($v): bool => $v !== '' && $v !== null)));
        }
        if (is_string($value)) {
            return GeneralUtility::intExplode(',', $value, true);
        }
        if (is_int($value)) {
            return [$value];
        }
        return [];
    }

    private function isExistingRecord(int|string $id): bool
    {
        return is_int($id) || (is_string($id) && ctype_digit($id));
    }

    /**
     * @param array<string,mixed> $fieldArray
     */
    private function reject(array &$fieldArray, DataHandler $dataHandler, string $table, int|string $id, string $reason): void
    {
        $fieldArray = [];
        $dataHandler->log(
            $table,
            $this->isExistingRecord($id) ? (int)$id : 0,
            SystemLogDatabaseAction::UPDATE,
            null,
            SystemLogErrorClassification::USER_ERROR,
            'TenantScopeEnforcer rejected write to ' . $table . ' #' . $id . ': ' . $reason
        );
    }
}
