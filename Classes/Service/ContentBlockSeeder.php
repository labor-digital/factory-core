<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Service;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentBlockSeeder
{
    private ?string $contentBlockBasePath = null;

    /** @var array<string, true> Tables already verified/created */
    private array $ensuredTables = [];

    public function getContentBlockBasePath(): string
    {
        if ($this->contentBlockBasePath === null) {
            $this->contentBlockBasePath = dirname(__DIR__, 2) . '/ContentBlocks/ContentElements';
        }

        return $this->contentBlockBasePath;
    }

    /**
     * Convert PascalCase to kebab-case: PageHero → page-hero
     */
    public function toKebabCase(string $pascalCase): string
    {
        $result = (string)preg_replace('/([a-z])([A-Z])/', '$1-$2', $pascalCase);

        return strtolower($result);
    }

    /**
     * Normalize a component key (lowercase, non-alphanumeric → hyphens).
     * Matches the normalizeComponentKey in ext_localconf.php.
     */
    public function normalizeComponentKey(string $value): string
    {
        $value = trim($value);
        $value = (string)preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value);
        $value = strtolower($value);
        $value = (string)preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    /**
     * Convert kebab-case slug to underscore directory name: page-hero → page_hero
     */
    public function toDirectoryName(string $slug): string
    {
        return str_replace('-', '_', $slug);
    }

    /**
     * Resolve the CType from the config.yaml qualified name.
     * factory/page-hero → factory_pagehero
     */
    public function resolveCType(string $directoryName): ?string
    {
        $config = $this->readConfigYaml($directoryName);
        if ($config === null || !isset($config['name'])) {
            return null;
        }

        $qualifiedName = $config['name'];
        // Replace / with _ and remove - to match Content Blocks UniqueIdentifierCreator
        return str_replace(['/', '-'], ['_', ''], $qualifiedName);
    }

    /**
     * Read and parse EditorInterface.yaml for a content block.
     */
    public function readConfigYaml(string $directoryName): ?array
    {
        $path = $this->getContentBlockBasePath() . '/' . $directoryName . '/config.yaml';
        if (!is_file($path)) {
            return null;
        }

        return Yaml::parseFile($path);
    }

    /**
     * Read and parse SeedData.yaml for a content block.
     */
    public function readSeedData(string $directoryName): ?array
    {
        $path = $this->getContentBlockBasePath() . '/' . $directoryName . '/SeedData.yaml';
        if (!is_file($path)) {
            return null;
        }

        return Yaml::parseFile($path);
    }

    /**
     * Read active components from factory.json (returns PascalCase names).
     */
    public function getActiveComponents(): ?array
    {
        $configPath = Environment::getProjectPath() . '/factory.json';
        if (!is_file($configPath)) {
            return null;
        }

        try {
            $config = json_decode((string)file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($config) || !isset($config['active_components']) || !is_array($config['active_components'])) {
            return null;
        }

        return array_values(array_filter(
            $config['active_components'],
            static fn(mixed $v): bool => is_string($v) && $v !== ''
        ));
    }

    /**
     * Build a DataHandler data array for seeding a content block (parent record only).
     *
     * Collection fields are excluded — use insertCollectionChildren() after
     * DataHandler has created the parent record.
     *
     * Returns ['tt_content' => [...]] or null if no seed data found.
     */
    public function buildDataHandlerRecord(
        string $directoryName,
        int $pid,
        int $sorting,
        string $newId,
        int $sysLanguageUid = 0,
    ): ?array {
        $seedData = $this->readSeedData($directoryName);
        if ($seedData === null) {
            return null;
        }

        $config = $this->readConfigYaml($directoryName);
        if ($config === null) {
            return null;
        }

        $cType = $this->resolveCType($directoryName);
        if ($cType === null) {
            return null;
        }

        // Index field definitions by identifier for quick lookup
        $fieldDefs = [];
        foreach ($config['fields'] ?? [] as $fieldDef) {
            if (isset($fieldDef['identifier'])) {
                $fieldDefs[$fieldDef['identifier']] = $fieldDef;
            }
        }

        $parentRecord = [
            'pid' => $pid,
            'CType' => $cType,
            'colPos' => 0,
            'sys_language_uid' => $sysLanguageUid,
            'sorting' => $sorting,
        ];

        foreach ($seedData as $fieldIdentifier => $value) {
            $fieldDef = $fieldDefs[$fieldIdentifier] ?? null;
            $fieldType = $fieldDef['type'] ?? null;

            // Skip types that can't be seeded via DataHandler
            if (in_array($fieldType, ['File', 'Record', 'Relation', 'Folder', 'Collection', 'Category'], true)) {
                continue;
            }

            $dbFieldName = $cType . '_' . $fieldIdentifier;
            $parentRecord[$dbFieldName] = $value;
        }

        return [
            'tt_content' => [
                $newId => $parentRecord,
            ],
        ];
    }

    /**
     * Insert Collection field children via direct SQL after the parent record exists.
     *
     * Content Blocks auto-generated child tables may not exist yet during early
     * CLI execution (before extension:setup). This method creates them on the fly
     * from the EditorInterface.yaml field definitions, then inserts seed data.
     *
     * @return int Number of collection fields seeded
     */
    public function insertCollectionChildren(
        string $directoryName,
        int $parentUid,
        int $pid,
    ): int {
        $seedData = $this->readSeedData($directoryName);
        $config = $this->readConfigYaml($directoryName);
        $cType = $this->resolveCType($directoryName);

        if ($seedData === null || $config === null || $cType === null) {
            return 0;
        }

        $fieldDefs = [];
        foreach ($config['fields'] ?? [] as $fieldDef) {
            if (isset($fieldDef['identifier'])) {
                $fieldDefs[$fieldDef['identifier']] = $fieldDef;
            }
        }

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $seededCollections = 0;

        foreach ($seedData as $fieldIdentifier => $value) {
            $fieldDef = $fieldDefs[$fieldIdentifier] ?? null;
            if (($fieldDef['type'] ?? null) !== 'Collection' || !is_array($value)) {
                continue;
            }

            $childTableName = $this->resolveChildTableName($cType, $fieldIdentifier);
            if ($childTableName === null) {
                continue;
            }

            $subFieldDefs = [];
            foreach ($fieldDef['fields'] ?? [] as $subField) {
                if (isset($subField['identifier'])) {
                    $subFieldDefs[$subField['identifier']] = $subField;
                }
            }

            // Ensure the child table exists, creating it if necessary
            $this->ensureChildTable($childTableName, $subFieldDefs, $connectionPool);

            $connection = $connectionPool->getConnectionForTable($childTableName);
            $childSorting = 1;
            $childCount = 0;

            foreach ($value as $childItem) {
                if (!is_array($childItem)) {
                    continue;
                }

                $childRecord = [
                    'pid' => $pid,
                    'foreign_table_parent_uid' => $parentUid,
                    'sorting' => $childSorting,
                ];

                foreach ($childItem as $subFieldId => $subValue) {
                    $subFieldType = $subFieldDefs[$subFieldId]['type'] ?? null;
                    if (in_array($subFieldType, ['File', 'Record', 'Relation', 'Folder', 'Category'], true)) {
                        continue;
                    }
                    $childRecord[$subFieldId] = $subValue;
                }

                $connection->insert($childTableName, $childRecord);
                $childSorting++;
                $childCount++;
            }

            if ($childCount > 0) {
                // Update parent record's collection count column if it exists.
                // Content Blocks adds these columns to tt_content dynamically —
                // they may not exist yet during early bootstrap.
                $parentConnection = $connectionPool->getConnectionForTable('tt_content');
                $parentColumns = $parentConnection->createSchemaManager()->listTableColumns('tt_content');
                $countColumn = $cType . '_' . $fieldIdentifier;
                if (isset($parentColumns[$countColumn])) {
                    $parentConnection->update(
                        'tt_content',
                        [$countColumn => $childCount],
                        ['uid' => $parentUid],
                    );
                }
                $seededCollections++;
            }
        }

        return $seededCollections;
    }

    /**
     * Resolve the IRRE child table name for a Collection field.
     *
     * First tries TCA (runtime), then falls back to the ContentBlocks naming convention.
     */
    private function resolveChildTableName(string $cType, string $fieldIdentifier): ?string
    {
        // Try TCA first (most reliable at runtime)
        $tcaField = $GLOBALS['TCA']['tt_content']['columns'][$cType . '_' . $fieldIdentifier]['config'] ?? null;
        if (is_array($tcaField) && isset($tcaField['foreign_table'])) {
            return $tcaField['foreign_table'];
        }

        // Fallback: Content Blocks names child tables as {uniqueIdentifier}_{fieldIdentifier}
        // where uniqueIdentifier = CType (already hyphen-free from resolveCType)
        return $cType . '_' . $fieldIdentifier;
    }

    /**
     * Create a Content Blocks child table if it doesn't exist yet.
     *
     * Mirrors the schema Content Blocks would generate: uid, pid,
     * foreign_table_parent_uid, sorting, plus one column per sub-field.
     */
    private function ensureChildTable(string $tableName, array $subFieldDefs, ConnectionPool $connectionPool): void
    {
        if (isset($this->ensuredTables[$tableName])) {
            return;
        }

        $connection = $connectionPool->getConnectionForTable($tableName);

        if ($connection->createSchemaManager()->tablesExist([$tableName])) {
            $this->ensuredTables[$tableName] = true;
            return;
        }

        // Build column definitions for sub-fields
        $fieldColumns = [];
        foreach ($subFieldDefs as $subFieldId => $subFieldDef) {
            $colName = $subFieldId;
            $fieldColumns[] = '`' . $colName . '` ' . $this->sqlTypeForFieldType($subFieldDef['type'] ?? 'Text');
        }

        $columnsSql = implode(",\n    ", $fieldColumns);

        $sql = <<<SQL
            CREATE TABLE `{$tableName}` (
                `uid` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                `pid` INT UNSIGNED DEFAULT 0 NOT NULL,
                `foreign_table_parent_uid` INT UNSIGNED DEFAULT 0 NOT NULL,
                `sorting` INT UNSIGNED DEFAULT 0 NOT NULL,
                {$columnsSql},
                PRIMARY KEY (`uid`),
                KEY `parent` (`foreign_table_parent_uid`),
                KEY `pid` (`pid`)
            )
            SQL;

        $connection->executeStatement($sql);
        $this->ensuredTables[$tableName] = true;
    }

    /**
     * Map Content Blocks field type to a SQL column definition.
     */
    private function sqlTypeForFieldType(string $fieldType): string
    {
        return match ($fieldType) {
            'Textarea' => 'TEXT DEFAULT NULL',
            'Checkbox' => "SMALLINT UNSIGNED DEFAULT 0 NOT NULL",
            'Number' => "INT DEFAULT 0 NOT NULL",
            default => "VARCHAR(255) DEFAULT '' NOT NULL",
        };
    }

    /**
     * Discover all content block directory names.
     */
    public function discoverContentBlocks(): array
    {
        $basePath = $this->getContentBlockBasePath();
        if (!is_dir($basePath)) {
            return [];
        }

        $directories = [];
        foreach (new \DirectoryIterator($basePath) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isDir()) {
                continue;
            }
            $directories[] = $fileInfo->getFilename();
        }

        sort($directories);

        return $directories;
    }
}
