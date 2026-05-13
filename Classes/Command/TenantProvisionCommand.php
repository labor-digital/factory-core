<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'factory:tenant:provision',
    description: 'Provisions a new tenant in the shared-tenant TYPO3 instance: site config, factory.json, root page, filemount, be_groups, initial tenant admin.'
)]
final class TenantProvisionCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PasswordHashFactory $passwordHashFactory,
        private readonly SiteWriter $siteWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Tenant slug (alphanumeric + dashes, used for site identifier and filemount path)')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Primary domain, e.g. acme.example.com')
            ->addOption('base', null, InputOption::VALUE_OPTIONAL, 'Full TYPO3 site base URL. Defaults to https://<domain>. Set explicitly for shared-host setups where the tenant lives on a subpath (e.g. https://shared.example.com/<slug>) — the TYPO3 site resolver routes by this prefix.', '')
            ->addOption('display-name', null, InputOption::VALUE_REQUIRED, 'Human-readable tenant name (root page title)')
            ->addOption('components', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of active component names (PascalCase or slug)', '')
            ->addOption('record-types', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of active record type names', '')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email address of the initial tenant admin (also used as username)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would be done without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Factory Tenant Provisioning');

        $slug = (string)$input->getOption('slug');
        $domain = (string)$input->getOption('domain');
        $base = (string)($input->getOption('base') ?: '');
        $displayName = (string)($input->getOption('display-name') ?: $slug);
        $components = $this->parseCsvOption((string)$input->getOption('components'));
        $recordTypes = $this->parseCsvOption((string)$input->getOption('record-types'));
        $adminEmail = (string)$input->getOption('admin-email');
        $dryRun = (bool)$input->getOption('dry-run');

        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $slug)) {
            $io->error('Invalid --slug. Must start with [a-z0-9] and contain only [a-z0-9-], max 63 chars.');
            return Command::FAILURE;
        }
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+$/i', $domain)) {
            $io->error('Invalid --domain.');
            return Command::FAILURE;
        }
        if ($base !== '' && !preg_match('#^https?://[^\s]+$#i', $base)) {
            $io->error('Invalid --base. Must be a full http(s) URL.');
            return Command::FAILURE;
        }
        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid --admin-email.');
            return Command::FAILURE;
        }

        $io->definitionList(
            ['slug' => $slug],
            ['domain' => $domain],
            ['display-name' => $displayName],
            ['components' => $components === [] ? '(none)' : implode(', ', $components)],
            ['record-types' => $recordTypes === [] ? '(none)' : implode(', ', $recordTypes)],
            ['admin-email' => $adminEmail],
            ['dry-run' => $dryRun ? 'yes' : 'no'],
        );

        if ($this->siteConfigExists($slug)) {
            $io->error("Site `{$slug}` already exists at config/sites/{$slug}/. Aborting.");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->warning('Dry run — no changes will be persisted.');
            return Command::SUCCESS;
        }

        try {
            $this->bootstrapAdminBeUser();

            $rootPageUid = $this->createRootPage($displayName, $io);
            $editorGroupUid = $this->createEditorGroup($slug, $displayName, $rootPageUid, $io);
            $adminGroupUid = $this->createAdminGroup($slug, $displayName, $rootPageUid, $editorGroupUid, $io);
            $this->applyRootPagePerms($rootPageUid, $editorGroupUid, $io);
            $fileMountUid = $this->createFileMount($slug, $io);
            $this->createFileadminDir($slug, $io);
            $this->writeSiteConfig($slug, $domain, $base, $displayName, $rootPageUid, $io);
            $this->writeFactoryJson($slug, $components, $recordTypes, $io);
            $this->createTenantAdminUser($slug, $adminEmail, [$editorGroupUid, $adminGroupUid], $fileMountUid, $rootPageUid, $io);

            $io->success("Tenant `{$slug}` provisioned. Root page #{$rootPageUid}, editor group #{$editorGroupUid}, admin group #{$adminGroupUid}.");
            $io->note("Send the tenant admin a password-reset URL from the TYPO3 login page (username: {$adminEmail}).");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Provisioning failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /** @return list<string> */
    private function parseCsvOption(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn(string $v): bool => $v !== ''));
    }

    private function siteConfigExists(string $slug): bool
    {
        return is_dir(Environment::getProjectPath() . '/config/sites/' . $slug);
    }

    private function bootstrapAdminBeUser(): void
    {
        if (isset($GLOBALS['BE_USER']) && !empty($GLOBALS['BE_USER']->user['uid'])) {
            return;
        }
        $backendUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $adminRecord = $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where($queryBuilder->expr()->eq('admin', 1))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if (!$adminRecord) {
            throw new \RuntimeException('No admin user found in be_users. Run "typo3 setup" first.');
        }
        $backendUser->user = $adminRecord;
        $backendUser->workspace = 0;
        $GLOBALS['BE_USER'] = $backendUser;
    }

    private function createRootPage(string $displayName, SymfonyStyle $io): int
    {
        $data = [
            'pages' => [
                'NEW_tenantroot' => [
                    'pid' => 0,
                    'title' => $displayName,
                    'doktype' => 1,
                    'is_siteroot' => 1,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        $this->assertNoDataHandlerErrors($dataHandler, 'create root page');
        $uid = (int)($dataHandler->substNEWwithIDs['NEW_tenantroot'] ?? 0);
        if ($uid === 0) {
            throw new \RuntimeException('Failed to create tenant root page.');
        }
        $io->writeln("Created root page #{$uid} ({$displayName}).");
        return $uid;
    }

    private function createEditorGroup(string $slug, string $displayName, int $rootPageUid, SymfonyStyle $io): int
    {
        $title = "tenant_{$slug}_editor";
        $tsConfig = "options.hideModules = extensionmanager,system_BeuserTxBeuser,system_config,tools_toolsadministration\noptions.pageTree.showPageIdWithTitle = 1\n";

        $data = [
            'be_groups' => [
                'NEW_group_editor' => [
                    'pid' => 0,
                    'title' => $title,
                    'description' => "Editor role for {$displayName}",
                    'db_mountpoints' => (string)$rootPageUid,
                    'tables_select' => 'pages,tt_content,sys_file_reference,sys_category',
                    'tables_modify' => 'pages,tt_content,sys_file_reference',
                    'TSconfig' => $tsConfig,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        $this->assertNoDataHandlerErrors($dataHandler, 'create editor group');
        $uid = (int)($dataHandler->substNEWwithIDs['NEW_group_editor'] ?? 0);
        $io->writeln("Created editor be_group #{$uid} ({$title}).");
        return $uid;
    }

    private function createAdminGroup(string $slug, string $displayName, int $rootPageUid, int $editorGroupUid, SymfonyStyle $io): int
    {
        $title = "tenant_{$slug}_admin";
        // Tenant-admin TSconfig: limit user management to own group, hide escalation fields.
        $tsConfig = "options.createUsersInGroups = {$editorGroupUid}\n"
            . "options.editUsersInGroups = {$editorGroupUid}\n"
            . "options.db_mountpoints = {$rootPageUid}\n"
            . "TCEFORM.be_users.admin.disabled = 1\n"
            . "TCEFORM.be_users.db_mountpoints.disabled = 1\n"
            . "TCEFORM.be_users.file_mountpoints.disabled = 1\n"
            . "TCEFORM.be_users.tables_select.disabled = 1\n"
            . "TCEFORM.be_users.tables_modify.disabled = 1\n"
            . "options.hideModules = extensionmanager,system_config,tools_toolsadministration\n";

        $data = [
            'be_groups' => [
                'NEW_group_admin' => [
                    'pid' => 0,
                    'title' => $title,
                    'description' => "Tenant-admin role for {$displayName}",
                    'subgroup' => (string)$editorGroupUid,
                    'db_mountpoints' => (string)$rootPageUid,
                    'tables_select' => 'pages,tt_content,sys_file_reference,sys_category,be_users',
                    'tables_modify' => 'pages,tt_content,sys_file_reference,be_users',
                    'TSconfig' => $tsConfig,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        $this->assertNoDataHandlerErrors($dataHandler, 'create admin group');
        $uid = (int)($dataHandler->substNEWwithIDs['NEW_group_admin'] ?? 0);
        $io->writeln("Created admin be_group #{$uid} ({$title}).");
        return $uid;
    }

    private function applyRootPagePerms(int $rootPageUid, int $editorGroupUid, SymfonyStyle $io): void
    {
        $connection = $this->connectionPool->getConnectionForTable('pages');
        $connection->update(
            'pages',
            [
                'perms_groupid' => $editorGroupUid,
                'perms_user' => 31,
                'perms_group' => 31,
                'perms_everybody' => 0,
            ],
            ['uid' => $rootPageUid]
        );
        $io->writeln("Applied tenant perms to root page #{$rootPageUid} (groupid={$editorGroupUid}, everybody=0).");
    }

    private function createFileMount(string $slug, SymfonyStyle $io): int
    {
        // TYPO3 13's table name is `sys_filemounts` (plural) — `sys_filemount`
        // (singular) doesn't exist as a TCA schema and DataHandler refuses to
        // operate on it. The mistake is easy to make because the BE column
        // referencing it (be_groups.file_mountpoints) uses the singular root.
        $data = [
            'sys_filemounts' => [
                'NEW_fm' => [
                    'pid' => 0,
                    'title' => "tenant_{$slug}",
                    'identifier' => '1:/' . $slug . '/',
                    'description' => "Filemount for tenant {$slug}",
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        $this->assertNoDataHandlerErrors($dataHandler, 'create filemount');
        $uid = (int)($dataHandler->substNEWwithIDs['NEW_fm'] ?? 0);
        $io->writeln("Created sys_filemounts #{$uid} (1:/{$slug}/).");
        return $uid;
    }

    private function createFileadminDir(string $slug, SymfonyStyle $io): void
    {
        $dir = Environment::getPublicPath() . '/fileadmin/' . $slug;
        if (is_dir($dir)) {
            $io->writeln("fileadmin dir {$dir} already exists.");
            return;
        }
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create fileadmin dir {$dir}");
        }
        $io->writeln("Created fileadmin dir {$dir}.");
    }

    private function writeSiteConfig(string $slug, string $domain, string $base, string $displayName, int $rootPageUid, SymfonyStyle $io): void
    {
        // Use TYPO3's SiteWriter (not file_put_contents) so that the
        // SiteConfigurationChangedEvent fires and the in-memory + persistent
        // site caches are invalidated. Direct filesystem writes leave the
        // site cache stale until the next manual `typo3 cache:flush` —
        // which translates to a 404 on every request to the newly
        // provisioned tenant for the duration of the cache TTL.
        $config = [
            'base' => $base !== '' ? $base : 'https://' . $domain,
            'dependencies' => ['labor-digital/client_sitepackage'],
            'websiteTitle' => $displayName,
            'headless' => 1,
            'languages' => [
                [
                    'title' => 'English',
                    'enabled' => true,
                    'locale' => 'en_US.utf8',
                    'hreflang' => '',
                    'base' => '/',
                    'websiteTitle' => $displayName,
                    'navigationTitle' => 'English',
                    'fallbackType' => 'strict',
                    'fallbacks' => '',
                    'flag' => 'en-us-gb',
                    'languageId' => 0,
                ],
            ],
            'rootPageId' => $rootPageUid,
        ];
        $this->siteWriter->write($slug, $config);
        $io->writeln("Wrote site config for {$slug} (cache invalidated via SiteConfigurationChangedEvent).");
    }

    /**
     * @param list<string> $components
     * @param list<string> $recordTypes
     */
    private function writeFactoryJson(string $slug, array $components, array $recordTypes, SymfonyStyle $io): void
    {
        $siteDir = Environment::getProjectPath() . '/config/sites/' . $slug;
        $factoryJson = [
            'core_version' => '1.0.0',
            'active_components' => $components,
            'active_record_types' => $recordTypes,
        ];
        file_put_contents(
            $siteDir . '/factory.json',
            json_encode($factoryJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        $io->writeln("Wrote factory.json to {$siteDir}/factory.json.");
    }

    /**
     * @param list<int> $groupUids
     */
    private function createTenantAdminUser(
        string $slug,
        string $email,
        array $groupUids,
        int $fileMountUid,
        int $rootPageUid,
        SymfonyStyle $io,
    ): void {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $existing = $queryBuilder
            ->select('uid')
            ->from('be_users')
            ->where($queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($email)))
            ->executeQuery()
            ->fetchOne();
        if ($existing) {
            $io->writeln("be_user {$email} already exists (#{$existing}) — skipping creation.");
            return;
        }

        $random = GeneralUtility::makeInstance(Random::class);
        $initialPassword = $random->generateRandomHexString(20);
        $hashInstance = $this->passwordHashFactory->getDefaultHashInstance('BE');
        $hashed = $hashInstance->getHashedPassword($initialPassword);

        $this->connectionPool->getConnectionForTable('be_users')->insert('be_users', [
            'username' => $email,
            'email' => $email,
            'password' => $hashed,
            'admin' => 0,
            'usergroup' => implode(',', array_map('intval', $groupUids)),
            'db_mountpoints' => (string)$rootPageUid,
            'file_mountpoints' => (string)$fileMountUid,
            'options' => 3, // mount db + file by default
            'disable' => 0,
            'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
            'crdate' => $GLOBALS['EXEC_TIME'] ?? time(),
            'workspace_id' => 0,
        ]);
        $io->writeln("Created tenant admin be_user '{$email}' (groups: " . implode(',', $groupUids) . ").");
        $io->writeln("  Initial password (rotate immediately): {$initialPassword}");
    }

    private function assertNoDataHandlerErrors(DataHandler $dataHandler, string $context): void
    {
        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException("DataHandler error during {$context}: " . implode(', ', $dataHandler->errorLog));
        }
    }
}
