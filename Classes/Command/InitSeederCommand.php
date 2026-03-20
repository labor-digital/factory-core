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

#[AsCommand(
    name: 'factory:seed:init',
    description: 'Seeds a fresh database with a multilingual page tree, admin user, and site configuration.'
)]
class InitSeederCommand extends Command
{
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

        // Initialize a fake backend user for DataHandler if not present
        if (!isset($GLOBALS['BE_USER'])) {
            $GLOBALS['BE_USER'] = GeneralUtility::makeInstance(BackendUserAuthentication::class);
            $GLOBALS['BE_USER']->user = ['uid' => 1, 'admin' => 1];
            $GLOBALS['BE_USER']->workspace = 0;
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
                    'sys_language_uid' => 0,
                ],
                'NEW_imprint' => [
                    'pid' => 0,
                    'title' => 'Imprint',
                    'doktype' => 1,
                    'sys_language_uid' => 0,
                ],
                'NEW_privacy' => [
                    'pid' => 0,
                    'title' => 'Privacy Policy',
                    'doktype' => 1,
                    'sys_language_uid' => 0,
                ],
                'NEW_404' => [
                    'pid' => 0,
                    'title' => '404 Error',
                    'doktype' => 1,
                    'nav_hide' => 1,
                    'sys_language_uid' => 0,
                ],
                'NEW_cb_collection' => [
                    'pid' => 0,
                    'title' => 'ContentBlocks-Collection',
                    'doktype' => 1,
                    'hidden' => 1,
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
        $imprintUid = $newUids['NEW_imprint'] ?? null;
        $privacyUid = $newUids['NEW_privacy'] ?? null;
        $error404Uid = $newUids['NEW_404'] ?? null;
        $cbCollectionUid = $newUids['NEW_cb_collection'] ?? null;

        $io->writeln("Created base pages (Home: $homeUid, Imprint: $imprintUid, Privacy: $privacyUid, 404: $error404Uid, CB-Collection: $cbCollectionUid)");

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
                            'pid' => 0,
                            'title' => 'Homepage (' . $lang . ')',
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $homeUid,
                        ],
                        'NEW_imprint_' . $lang => [
                            'pid' => 0,
                            'title' => 'Imprint (' . $lang . ')',
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $imprintUid,
                        ],
                        'NEW_privacy_' . $lang => [
                            'pid' => 0,
                            'title' => 'Privacy Policy (' . $lang . ')',
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $privacyUid,
                        ],
                        'NEW_404_' . $lang => [
                            'pid' => 0,
                            'title' => '404 Error (' . $lang . ')',
                            'sys_language_uid' => $languageId,
                            'l10n_parent' => $error404Uid,
                        ],
                        'NEW_cb_collection_' . $lang => [
                            'pid' => 0,
                            'title' => 'ContentBlocks-Collection (' . $lang . ')',
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
            '404' => $error404Uid,
        ];
    }

    private function generateSiteConfiguration(array $languages, string $baseLang, array $uids, SymfonyStyle $io): void
    {
        $io->section('Generating Site Configuration');

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
            ])
            ->executeStatement();

        $io->writeln('Admin user created (username: admin, password: password).');
    }
}
