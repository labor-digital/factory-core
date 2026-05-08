<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Command;

use LaborDigital\FactoryCore\Configuration\FactoryComponentRegistry;
use LaborDigital\FactoryCore\Service\ContentBlockSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reseeds an existing site's content from a seed template without touching the
 * page tree, schema, be_users, or install-tool config. Designed for fast local
 * iteration on seeds (pipeline-app reseed flow, DL #013/#014).
 *
 * Wipes tt_content for the site root + descendants and rows in every factory
 * record-type table, then reapplies the homepage seed template, the CB
 * Collection components implied by factory.json, and the active record-type
 * fixtures. Idempotent.
 */
#[AsCommand(
    name: 'factory:seed:reset',
    description: 'Reseed an existing TYPO3 instance from a seed template (wipes tt_content + factory record tables, preserves page tree).',
)]
class ResetSeederCommand extends Command
{
    public function __construct(
        private readonly ContentBlockSeeder $contentBlockSeeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('seed-template', null, InputOption::VALUE_REQUIRED, 'Seed template slug (looked up under SeedTemplates/)')
            ->addOption('site', null, InputOption::VALUE_OPTIONAL, 'Site identifier whose root tree gets reseeded', 'factory_base')
            ->addOption('lang', null, InputOption::VALUE_OPTIONAL, 'Comma-separated languages — kept for parity with seed:init', 'de,en')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Factory Reset Seeder');

        $templateName = (string)$input->getOption('seed-template');
        if ($templateName === '') {
            $io->error('--seed-template is required.');
            return Command::FAILURE;
        }

        $siteIdentifier = (string)$input->getOption('site');

        try {
            $rootPageUid = $this->resolveSiteRoot($siteIdentifier);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (!$input->getOption('yes') && !$io->confirm(
            sprintf('Wipe tt_content + factory record tables under site "%s" (root pid %d) and reseed from "%s"?', $siteIdentifier, $rootPageUid, $templateName),
            false
        )) {
            $io->writeln('Aborted.');
            return Command::SUCCESS;
        }

        try {
            $this->bootstrapAdminBeUser();

            $pageUids = $this->collectPageTree($rootPageUid);
            $io->writeln(sprintf('Reseeding %d page(s) under root %d.', count($pageUids), $rootPageUid));

            $this->wipeContent($pageUids, $io);
            $this->wipeRecordTables($io);

            $homeUid = $rootPageUid;
            $this->reseedHomeFromTemplate($homeUid, $templateName, $io);

            $cbCollectionPid = $this->resolveCbCollectionPid();
            if ($cbCollectionPid > 0) {
                $this->reseedCbCollection($cbCollectionPid, $io);
            } else {
                $io->writeln('No CB-Collection PID in registry — skipping collection reseed.');
            }

            $recordsPid = $this->resolveRecordsPid($rootPageUid);
            if ($recordsPid > 0) {
                $this->reseedRecords($recordsPid, $io);
            }

            FactoryComponentRegistry::invalidate($siteIdentifier);

            $io->success('Reseed completed.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function resolveSiteRoot(string $siteIdentifier): int
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByIdentifier($siteIdentifier);
            return $site->getRootPageId();
        } catch (\Throwable) {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $qb = $connectionPool->getQueryBuilderForTable('pages');
            $row = $qb->select('uid')
                ->from('pages')
                ->where(
                    $qb->expr()->eq('is_siteroot', 1),
                    $qb->expr()->eq('sys_language_uid', 0),
                    $qb->expr()->eq('hidden', 0),
                    $qb->expr()->eq('deleted', 0),
                )
                ->orderBy('uid', 'ASC')
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
            if (!$row) {
                throw new \RuntimeException(sprintf('Site "%s" not found and no site root exists in the page tree.', $siteIdentifier));
            }
            return (int)$row['uid'];
        }
    }

    private function bootstrapAdminBeUser(): void
    {
        if (isset($GLOBALS['BE_USER']) && !empty($GLOBALS['BE_USER']->user['uid'])) {
            return;
        }
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $qb = $connectionPool->getQueryBuilderForTable('be_users');
        $admin = $qb->select('*')
            ->from('be_users')
            ->where($qb->expr()->eq('admin', 1))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if (!$admin) {
            throw new \RuntimeException('No admin user found in be_users. Run "factory:seed:init" first.');
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
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        while ($stack !== []) {
            $parent = array_pop($stack);
            $qb = $connectionPool->getQueryBuilderForTable('pages');
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
    private function wipeContent(array $pageUids, SymfonyStyle $io): void
    {
        if ($pageUids === []) {
            return;
        }
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tt_content');
        $deleted = $connection->executeStatement(
            'DELETE FROM tt_content WHERE pid IN (' . implode(',', array_map('intval', $pageUids)) . ')'
        );
        $io->writeln(sprintf('Wiped %d tt_content row(s).', (int)$deleted));
    }

    private function wipeRecordTables(SymfonyStyle $io): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $tables = [];
        foreach (FactoryComponentRegistry::discoverRecordTypes() as $type) {
            $table = $type['table'] ?? null;
            if (is_string($table) && $table !== '' && str_starts_with($table, 'tx_factorycore_')) {
                $tables[] = $table;
            }
        }
        if ($tables === []) {
            return;
        }
        $tables = array_unique($tables);
        foreach ($tables as $table) {
            try {
                $connection = $connectionPool->getConnectionForTable($table);
                $deleted = $connection->executeStatement('DELETE FROM ' . $connection->quoteIdentifier($table));
                $io->writeln(sprintf('Wiped %d row(s) in %s.', (int)$deleted, $table));
            } catch (\Throwable $e) {
                $io->writeln(sprintf('Skipping %s: %s', $table, $e->getMessage()));
            }
        }
    }

    private function resolveCbCollectionPid(): int
    {
        $registry = GeneralUtility::makeInstance(Registry::class);
        return (int)$registry->get('FactoryCore', 'CbCollectionPid', 0);
    }

    private function resolveRecordsPid(int $rootUid): int
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $qb = $connectionPool->getQueryBuilderForTable('pages');
        $row = $qb->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($rootUid, \PDO::PARAM_INT)),
                $qb->expr()->eq('title', $qb->createNamedParameter('Records')),
                $qb->expr()->eq('deleted', 0),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        return $row ? (int)$row['uid'] : 0;
    }

    private function reseedHomeFromTemplate(int $homeUid, string $templateName, SymfonyStyle $io): void
    {
        $template = $this->contentBlockSeeder->readSeedTemplate($templateName);
        if ($template === null) {
            throw new \RuntimeException(sprintf('Seed template "%s" not found.', $templateName));
        }
        $elements = $template['elements'] ?? [];
        if ($elements === []) {
            $io->writeln('Template has no elements — nothing to seed on the homepage.');
            return;
        }

        $elements = array_reverse($elements);

        $data = [];
        $sorting = 256;
        $componentMeta = [];

        foreach ($elements as $index => $element) {
            $slug = $element['component'] ?? '';
            $overrideData = $element['data'] ?? [];
            if ($slug === '') {
                continue;
            }
            $directoryName = $this->contentBlockSeeder->toDirectoryName($slug);
            $newId = 'NEW_reset_tpl_' . $index . '_' . $directoryName;

            $record = $this->contentBlockSeeder->buildDataHandlerRecordWithOverrides(
                $directoryName,
                $homeUid,
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
            $componentMeta[$newId] = ['directory' => $directoryName, 'data' => $overrideData];
            $sorting += 256;
        }

        if ($data === []) {
            return;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Reseed home from template failed: ' . implode(', ', $dataHandler->errorLog));
        }

        foreach ($componentMeta as $newId => $meta) {
            $parentUid = (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
            if ($parentUid > 0) {
                $this->contentBlockSeeder->insertCollectionChildrenWithOverrides(
                    $meta['directory'],
                    $parentUid,
                    $homeUid,
                    $meta['data'],
                );
            }
        }

        $io->writeln(sprintf('Reseeded %d homepage element(s) from template "%s".', count($componentMeta), $templateName));
    }

    private function reseedCbCollection(int $cbCollectionPid, SymfonyStyle $io): void
    {
        $activeComponents = $this->contentBlockSeeder->getActiveComponents();
        if ($activeComponents === null || $activeComponents === []) {
            return;
        }

        $data = [];
        $sorting = 256;
        $componentDirectories = [];

        foreach ($activeComponents as $slug) {
            $directoryName = $this->contentBlockSeeder->toDirectoryName($slug);
            $newId = 'NEW_reset_cb_' . $directoryName;
            $record = $this->contentBlockSeeder->buildDataHandlerRecord(
                $directoryName,
                $cbCollectionPid,
                $sorting,
                $newId,
            );
            if ($record === null) {
                continue;
            }
            foreach ($record as $table => $rows) {
                $data[$table] = array_merge($data[$table] ?? [], $rows);
            }
            $componentDirectories[$newId] = $directoryName;
            $sorting += 256;
        }

        if ($data === []) {
            return;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Reseed CB collection failed: ' . implode(', ', $dataHandler->errorLog));
        }

        foreach ($componentDirectories as $newId => $directoryName) {
            $parentUid = (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
            if ($parentUid > 0) {
                $this->contentBlockSeeder->insertCollectionChildren($directoryName, $parentUid, $cbCollectionPid);
            }
        }

        $io->writeln(sprintf('Reseeded CB-Collection with %d component(s).', count($componentDirectories)));
    }

    private function reseedRecords(int $recordsPid, SymfonyStyle $io): void
    {
        $activeRecordTypes = $this->contentBlockSeeder->getActiveRecordTypes();
        if ($activeRecordTypes === []) {
            return;
        }

        $data = [];
        $seeded = [];
        $sorting = 256;

        foreach ($activeRecordTypes as $recordTypeName) {
            $slug = $this->contentBlockSeeder->toKebabCase($recordTypeName);
            $directoryName = $this->contentBlockSeeder->toDirectoryName($slug);
            $seedList = $this->contentBlockSeeder->readSeedData($directoryName, isRecord: true);
            if (!is_array($seedList)) {
                continue;
            }
            foreach ($seedList as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $newId = 'NEW_reset_rec_' . $directoryName . '_' . $index;
                $record = $this->contentBlockSeeder->buildRecordDataHandlerRecord(
                    $directoryName,
                    $recordsPid,
                    $sorting,
                    $newId,
                    $entry,
                );
                if ($record === null) {
                    continue;
                }
                foreach ($record as $table => $rows) {
                    $data[$table] = array_merge($data[$table] ?? [], $rows);
                }
                $sorting += 256;
            }
            $seeded[] = $recordTypeName . ' (' . count($seedList) . ')';
        }

        if ($data === []) {
            return;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Reseed records failed: ' . implode(', ', $dataHandler->errorLog));
        }

        $io->writeln('Reseeded record types: ' . implode(', ', $seeded));
    }
}
