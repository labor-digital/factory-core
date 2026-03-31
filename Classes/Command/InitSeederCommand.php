<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use LaborDigital\FactoryCore\Service\ContentBlockSeeder;

#[AsCommand(
    name: 'factory:seed:init',
    description: 'Seeds a fresh database with a multilingual page tree, admin user, and site configuration.'
)]
class InitSeederCommand extends Command
{
    public function __construct(
        private readonly ContentBlockSeeder $contentBlockSeeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('lang', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of languages (e.g., "de,en")', 'de')
            ->addOption('base', null, InputOption::VALUE_OPTIONAL, 'Base language (e.g., "de")', 'de');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Factory Init Seeder');

        $langOption = $input->getOption('lang');
        $baseLang = $input->getOption('base');

        $languages = array_map('trim', explode(',', $langOption));
        if (!in_array($baseLang, $languages, true)) {
            array_unshift($languages, $baseLang);
        }

        try {
            $uids = $this->createPageTree($languages, $baseLang, $io);
            $this->createHomeContent($uids['home'], $io);
            $this->seedCollectionContent($uids['cb_collection'], $io);
            $this->generateSiteConfiguration($languages, $baseLang, $uids, $io);
            $this->createAdminUser($io);

            $io->success('Seeding completed successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createPageTree(array $languages, string $baseLang, SymfonyStyle $io): array
    {
        $io->section('Creating Page Tree');

        // Bootstrap a real backend admin user for DataHandler
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
                throw new \RuntimeException('No admin user found in be_users. Run "typo3 setup" first.');
            }
            $backendUser->user = $adminRecord;
            $backendUser->workspace = 0;
            $GLOBALS['BE_USER'] = $backendUser;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        // 1. Create Base Pages
        $data = [
            'pages' => [
                'NEW_home' => [
                    'pid' => 0,
                    'title' => 'Homepage',
                    'doktype' => 1,
                    'is_siteroot' => 1,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
                'NEW_footer' => [
                    'pid' => 'NEW_home',
                    'title' => 'Footer',
                    'doktype' => 254,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
                'NEW_imprint' => [
                    'pid' => 'NEW_footer',
                    'title' => 'Imprint',
                    'doktype' => 1,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
                'NEW_privacy' => [
                    'pid' => 'NEW_footer',
                    'title' => 'Privacy Policy',
                    'doktype' => 1,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
                'NEW_404' => [
                    'pid' => 'NEW_home',
                    'title' => '404 Error',
                    'doktype' => 1,
                    'nav_hide' => 1,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
                'NEW_cb_collection' => [
                    'pid' => 'NEW_home',
                    'title' => 'ContentBlocks-Collection',
                    'doktype' => 1,
                    'hidden' => 0,
                    'sys_language_uid' => 0,
                ],
            ]
        ];

        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Error creating base pages: ' . implode(', ', $dataHandler->errorLog));
        }

        $newUids = $dataHandler->substNEWwithIDs;
        $homeUid = $newUids['NEW_home'] ?? null;
        $footerUid = $newUids['NEW_footer'] ?? null;
        $imprintUid = $newUids['NEW_imprint'] ?? null;
        $privacyUid = $newUids['NEW_privacy'] ?? null;
        $error404Uid = $newUids['NEW_404'] ?? null;
        $cbCollectionUid = $newUids['NEW_cb_collection'] ?? null;

        $io->writeln("Created base pages (Home: $homeUid, Footer: $footerUid, Imprint: $imprintUid, Privacy: $privacyUid, 404: $error404Uid, CB-Collection: $cbCollectionUid)");

        // Save CB Collection PID to a registry
        $registry = GeneralUtility::makeInstance(Registry::class);
        $registry->set('FactoryCore', 'CbCollectionPid', $cbCollectionUid);
        $io->writeln("Saved CB-Collection PID ($cbCollectionUid) to TYPO3 Registry.");

        // 2. Create Translated Pages
        $secondaryLanguages = array_filter($languages, fn($l) => $l !== $baseLang);
        
        if (!empty($secondaryLanguages)) {
            $languageId = 1;
            foreach ($secondaryLanguages as $lang) {
                $translationData = [
                    'pages' => [
                        'NEW_home_' . $lang => [
                            'pid' => $homeUid,
                            'title' => 'Homepage (' . $lang . ')',
                            'hidden' => 0,
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $homeUid,
                        ],
                        'NEW_footer_' . $lang => [
                            'pid' => $homeUid,
                            'title' => 'Footer (' . $lang . ')',
                            'hidden' => 0,
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $footerUid,
                        ],
                        'NEW_imprint_' . $lang => [
                            'pid' => $footerUid,
                            'title' => 'Imprint (' . $lang . ')',
                            'hidden' => 0,
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $imprintUid,
                        ],
                        'NEW_privacy_' . $lang => [
                            'pid' => $footerUid,
                            'title' => 'Privacy Policy (' . $lang . ')',
                            'hidden' => 0,
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $privacyUid,
                        ],
                        'NEW_404_' . $lang => [
                            'pid' => $homeUid,
                            'title' => '404 Error (' . $lang . ')',
                            'hidden' => 0,
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $error404Uid,
                        ],
                        'NEW_cb_collection_' . $lang => [
                            'pid' => $homeUid,
                            'title' => 'ContentBlocks-Collection (' . $lang . ')',
                            'hidden' => 1,
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $cbCollectionUid,
                        ],
                    ]
                ];

                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start($translationData, []);
                $dataHandler->process_datamap();

                if (!empty($dataHandler->errorLog)) {
                    throw new \RuntimeException('Error creating translated pages for ' . $lang . ': ' . implode(', ', $dataHandler->errorLog));
                }

                $io->writeln("Created translated pages for language: $lang (ID: $languageId)");
                $languageId++;
            }
        }

        return [
            'home' => $homeUid,
            'footer' => $footerUid,
            '404' => $error404Uid,
            'cb_collection' => $cbCollectionUid,
        ];
    }

    private function createHomeContent(int $homeUid, SymfonyStyle $io): void
    {
        $io->section('Seeding Homepage Content');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        $data = [
            'tt_content' => [
                'NEW_hero' => [
                    'pid' => $homeUid,
                    'CType' => 'factory_pagehero',
                    'colPos' => 0,
                    'sys_language_uid' => 0,
                    'sorting' => 256,
                    'factory_pagehero_headline' => 'Welcome',
                    'factory_pagehero_title' => 'Factory Headless CMS',
                    'factory_pagehero_description' => 'Your TYPO3 + Nuxt boilerplate is ready.',
                ],
                'NEW_header' => [
                    'pid' => $homeUid,
                    'CType' => 'factory_header',
                    'colPos' => 0,
                    'sys_language_uid' => 0,
                    'sorting' => 512,
                    'factory_header_text' => 'Getting Started',
                    'factory_header_level' => 'h2',
                ],
                'NEW_text' => [
                    'pid' => $homeUid,
                    'CType' => 'factory_text',
                    'colPos' => 0,
                    'sys_language_uid' => 0,
                    'sorting' => 768,
                    'factory_text_bodytext' => '<p>Edit this page in the TYPO3 backend to start building your site.</p>',
                ],
            ],
        ];

        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Error seeding homepage content: ' . implode(', ', $dataHandler->errorLog));
        }

        $newUids = $dataHandler->substNEWwithIDs;
        $io->writeln('Seeded homepage content (Hero: ' . ($newUids['NEW_hero'] ?? '?') . ', Header: ' . ($newUids['NEW_header'] ?? '?') . ', Text: ' . ($newUids['NEW_text'] ?? '?') . ')');
    }

    private function seedCollectionContent(int $cbCollectionPid, SymfonyStyle $io): void
    {
        $activeComponents = $this->contentBlockSeeder->getActiveComponents();
        if ($activeComponents === null) {
            $io->writeln('No factory.json found — skipping ContentBlocks-Collection seeding.');
            return;
        }

        if ($activeComponents === []) {
            $io->writeln('No active components in factory.json — skipping ContentBlocks-Collection seeding.');
            return;
        }

        $io->section('Seeding ContentBlocks-Collection');

        $data = [];
        $sorting = 256;
        $seeded = [];
        $componentDirectories = [];

        foreach ($activeComponents as $componentName) {
            $slug = $this->contentBlockSeeder->toKebabCase($componentName);
            $directoryName = $this->contentBlockSeeder->toDirectoryName($slug);
            $newId = 'NEW_cb_' . $directoryName;

            $record = $this->contentBlockSeeder->buildDataHandlerRecord(
                $directoryName,
                $cbCollectionPid,
                $sorting,
                $newId,
            );

            if ($record === null) {
                $io->writeln("  Skipping $componentName — no SeedData.yaml found.");
                continue;
            }

            // Merge record data into the combined data array
            foreach ($record as $table => $records) {
                if (!isset($data[$table])) {
                    $data[$table] = [];
                }
                $data[$table] = array_merge($data[$table], $records);
            }

            $componentDirectories[$newId] = $directoryName;
            $seeded[] = $componentName;
            $sorting += 256;
        }

        if ($data === []) {
            $io->writeln('No seed data found for any active component.');
            return;
        }

        // Phase 1: Create parent records via DataHandler (scalar fields only)
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if (!empty($dataHandler->errorLog)) {
            throw new \RuntimeException('Error seeding ContentBlocks-Collection: ' . implode(', ', $dataHandler->errorLog));
        }

        // Phase 2: Insert Collection children via direct SQL
        $collectionsSeeded = 0;
        foreach ($componentDirectories as $newId => $directoryName) {
            $parentUid = (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
            if ($parentUid > 0) {
                $collectionsSeeded += $this->contentBlockSeeder->insertCollectionChildren(
                    $directoryName,
                    $parentUid,
                    $cbCollectionPid,
                );
            }
        }

        $io->writeln('Seeded ' . count($seeded) . ' component(s) on ContentBlocks-Collection: ' . implode(', ', $seeded));
        if ($collectionsSeeded > 0) {
            $io->writeln("  Including $collectionsSeeded collection field(s) via direct SQL.");
        }
    }

    private function generateSiteConfiguration(array $languages, string $baseLang, array $uids, SymfonyStyle $io): void
    {
        $io->section('Generating Site Configuration');

        // Remove the default "main" site config created by "typo3 setup"
        $defaultSitePath = Environment::getConfigPath() . '/sites/main';
        if (is_dir($defaultSitePath)) {
            GeneralUtility::rmdir($defaultSitePath, true);
            $io->writeln('Removed default "main" site configuration.');
        }

        $siteIdentifier = 'factory_base';
        $configPath = Environment::getConfigPath() . '/sites/' . $siteIdentifier;

        if (!is_dir($configPath)) {
            GeneralUtility::mkdir_deep($configPath);
        }

        $configFile = $configPath . '/config.yaml';

        $languageConfigs = [];
        $languageId = 0;

        // Base language
        $languageConfigs[] = [
            'title' => strtoupper($baseLang),
            'enabled' => true,
            'languageId' => $languageId++,
            'base' => '/',
            'typo3Language' => $baseLang,
            'locale' => $baseLang . '_' . strtoupper($baseLang) . '.UTF-8',
            'iso-639-1' => $baseLang,
            'navigationTitle' => strtoupper($baseLang),
            'hreflang' => $baseLang . '-' . strtoupper($baseLang),
            'direction' => 'ltr',
            'flag' => $baseLang,
        ];

        // Secondary languages
        foreach ($languages as $lang) {
            if ($lang === $baseLang) {
                continue;
            }

            $languageConfigs[] = [
                'title' => strtoupper($lang),
                'enabled' => true,
                'languageId' => $languageId++,
                'base' => '/' . $lang . '/',
                'typo3Language' => $lang,
                'locale' => $lang . '_' . strtoupper($lang) . '.UTF-8',
                'iso-639-1' => $lang,
                'navigationTitle' => strtoupper($lang),
                'hreflang' => $lang . '-' . strtoupper($lang),
                'direction' => 'ltr',
                'fallbackType' => 'fallback',
                'fallbacks' => '0',
                'flag' => $lang,
            ];
        }

        $config = [
            'rootPageId' => (int)$uids['home'],
            'base' => '/',
            'frontendBase' => '%env(APP_FRONTEND_DOMAIN)%',
            'websiteTitle' => '%env(APP_SITENAME)%',
            'headless' => 1,
            'dependencies' => [
                'labor-digital/client_sitepackage',
            ],
            'languages' => $languageConfigs,
            'errorHandling' => [
                [
                    'errorCode' => 404,
                    'errorHandler' => 'Page',
                    'errorContentSource' => 't3://page?uid=' . $uids['404'],
                ]
            ],
            'routes' => [
                [
                    'route' => 'robots.txt',
                    'type' => 'staticText',
                    'content' => "User-agent: *\nDisallow: /typo3/\n"
                ]
            ]
        ];

        $yaml = Yaml::dump($config, 99, 2);
        file_put_contents($configFile, $yaml);

        $io->writeln('Site configuration written to ' . $configFile);

        // Write site settings (separate file for sitesettings: TypoScript access)
        $settingsFile = $configPath . '/settings.yaml';
        $settings = [
            'footerPageUid' => (int)$uids['footer'],
        ];
        $settingsYaml = Yaml::dump($settings, 99, 2);
        file_put_contents($settingsFile, $settingsYaml);

        $io->writeln('Site settings written to ' . $settingsFile);
    }

    private function createAdminUser(SymfonyStyle $io): void
    {
        $io->section('Creating Admin User');

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable('be_users');

        $existingUser = $queryBuilder
            ->select('uid')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter('admin'))
            )
            ->executeQuery()
            ->fetchOne();

        if ($existingUser) {
            $io->writeln('Admin user already exists. Skipping.');
            return;
        }

        $hashFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $hashInstance = $hashFactory->getDefaultHashInstance('BE');
        $hashedPassword = $hashInstance->getHashedPassword('password');

        $queryBuilder
            ->insert('be_users')
            ->values([
                'username' => 'admin',
                'password' => $hashedPassword,
                'admin' => 1,
                'tstamp' => $GLOBALS['EXEC_TIME'] ?? time(),
                'crdate' => $GLOBALS['EXEC_TIME'] ?? time(),
                'workspace_id' => 0,
                'TSconfig' => 'options.pageTree.showPageIdWithTitle = 1',
            ])
            ->executeStatement();

        $io->writeln('Admin user created (username: admin, password: password).');
    }
}
