<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Command;

use LaborDigital\FactoryCore\Service\ContentBlockSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsCommand(
    name: 'factory:seed:component',
    description: 'Seeds a single content block with default data on the ContentBlocks-Collection page.'
)]
class ComponentSeederCommand extends Command
{
    public function __construct(
        private readonly ContentBlockSeeder $contentBlockSeeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::REQUIRED, 'The kebab-case slug of the component (e.g., "page-hero")')
            ->addOption('pid', null, InputOption::VALUE_OPTIONAL, 'Target page ID (defaults to CB-Collection page from Registry)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slug = $input->getArgument('slug');
        $pidOption = $input->getOption('pid');

        // Bootstrap backend user for DataHandler
        if (!isset($GLOBALS['BE_USER']) || empty($GLOBALS['BE_USER']->user['uid'])) {
            $backendUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $queryBuilder = $connectionPool->getQueryBuilderForTable('be_users');
            $adminRecord = $queryBuilder
                ->select('*')
                ->from('be_users')
                ->where($queryBuilder->expr()->eq('admin', 1))
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
            if (!$adminRecord) {
                $io->error('No admin user found in be_users. Run "typo3 setup" first.');
                return Command::FAILURE;
            }
            $backendUser->user = $adminRecord;
            $backendUser->workspace = 0;
            $GLOBALS['BE_USER'] = $backendUser;
        }

        // Resolve target PID
        if ($pidOption !== null) {
            $pid = (int)$pidOption;
        } else {
            $registry = GeneralUtility::makeInstance(Registry::class);
            $pid = $registry->get('FactoryCore', 'CbCollectionPid');
            if ($pid === null) {
                $io->error('No CB-Collection PID found in Registry. Run "factory:seed:init" first or use --pid.');
                return Command::FAILURE;
            }
            $pid = (int)$pid;
        }

        $directoryName = $this->contentBlockSeeder->toDirectoryName($slug);

        // Get current max sorting on the target page
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('tt_content');
        $maxSorting = $queryBuilder
            ->selectLiteral('MAX(sorting) AS max_sorting')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('pid', $pid))
            ->executeQuery()
            ->fetchOne();
        $sorting = ((int)$maxSorting) + 256;

        $newId = 'NEW_cb_' . $directoryName;
        $record = $this->contentBlockSeeder->buildDataHandlerRecord($directoryName, $pid, $sorting, $newId);

        if ($record === null) {
            $io->error("No SeedData.yaml found for component \"$slug\" (directory: $directoryName).");
            return Command::FAILURE;
        }

        // Phase 1: Create parent record via DataHandler (scalar fields only)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($record, []);
        $dataHandler->process_datamap();

        if (!empty($dataHandler->errorLog)) {
            $io->error('Error seeding component: ' . implode(', ', $dataHandler->errorLog));
            return Command::FAILURE;
        }

        $newUid = $dataHandler->substNEWwithIDs[$newId] ?? '?';

        // Phase 2: Insert Collection children via direct SQL
        if (is_int($newUid) || ctype_digit((string)$newUid)) {
            $this->contentBlockSeeder->insertCollectionChildren($directoryName, (int)$newUid, $pid);
        }

        $io->success("Seeded component \"$slug\" on page $pid (UID: $newUid)");

        return Command::SUCCESS;
    }
}
