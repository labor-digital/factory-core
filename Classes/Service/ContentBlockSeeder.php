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
    private ?string $recordBlockBasePath = null;

    /** @var array<string, true> Tables already verified/created */
    private array $ensuredTables = [];

    public function getContentBlockBasePath(): string
    {
        if ($this->contentBlockBasePath === null) {
            $this->contentBlockBasePath = dirname(__DIR__, 2) . '/ContentBlocks/ContentElements';
        }

        return $this->contentBlockBasePath;
    }

    public function getRecordBlockBasePath(): string
    {
        if ($this->recordBlockBasePath === null) {
            $this->recordBlockBasePath = dirname(__DIR__, 2) . '/ContentBlocks/RecordTypes';
        }

        return $this->recordBlockBasePath;
    }

    private function resolveBasePath(bool $isRecord): string
    {
        return $isRecord ? $this->getRecordBlockBasePath() : $this->getContentBlockBasePath();
    }

    /**
     * Convert PascalCase to kebab-case: ImgSlider → img-slider
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
     * Convert kebab-case slug to underscore directory name: img-slider → img_slider
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
     * Read and parse config.yaml for a content block.
     */
    public function readConfigYaml(string $directoryName, bool $isRecord = false): ?array
    {
        $path = $this->resolveBasePath($isRecord) . '/' . $directoryName . '/config.yaml';
        if (!is_file($path)) {
            return null;
        }

        return Yaml::parseFile($path);
    }

    /**
     * Read and parse SeedData.yaml for a content block.
     */
    public function readSeedData(string $directoryName, bool $isRecord = false): ?array
    {
        $path = $this->resolveBasePath($isRecord) . '/' . $directoryName . '/SeedData.yaml';
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
        $schemaManager = $connection->createSchemaManager();

        // Existing table — bring it up to date by ALTER-adding any sub-field
        // columns that aren't there yet. Previously we returned early on
        // tablesExist, which broke seeding after a content-block schema
        // change because the new column never landed (content-blocks
        // creates the table during the DB analyzer pass but skipped
        // renamed/added columns when an old version of the table was
        // still in place — and a seed-time INSERT then failed with
        // "Unknown column 'X' in 'field list'"). Self-heal here.
        if ($schemaManager->tablesExist([$tableName])) {
            // Self-heal: discover existing columns via information_schema
            // (portable across MySQL/MariaDB, returns exactly the stored
            // column names — no Doctrine introspection quirks, no backtick
            // wrapping). Then ALTER-add the missing ones.
            //
            // We avoid `ADD COLUMN IF NOT EXISTS` because some MariaDB /
            // MySQL builds we deploy to don't accept it (parse error on
            // `IF NOT EXISTS` after ADD COLUMN). information_schema is
            // available in every supported version.
            $existing = $connection->fetchFirstColumn(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tableName]
            );
            $existingLower = [];
            foreach ($existing as $name) {
                $existingLower[] = strtolower((string)$name);
            }
            foreach ($subFieldDefs as $subFieldId => $subFieldDef) {
                if (in_array(strtolower($subFieldId), $existingLower, true)) {
                    continue;
                }
                $sqlType = $this->sqlTypeForFieldType($subFieldDef['type'] ?? 'Text');
                $connection->executeStatement(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `' . $subFieldId . '` ' . $sqlType
                );
            }
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
     * Build a DataHandler record using custom override data instead of SeedData.yaml.
     * Falls back to SeedData.yaml for fields not present in the override.
     */
    public function buildDataHandlerRecordWithOverrides(
        string $directoryName,
        int $pid,
        int $sorting,
        string $newId,
        array $overrideData,
        int $sysLanguageUid = 0,
    ): ?array {
        $config = $this->readConfigYaml($directoryName);
        if ($config === null) {
            return null;
        }

        $cType = $this->resolveCType($directoryName);
        if ($cType === null) {
            return null;
        }

        // Merge: SeedData.yaml defaults + template overrides
        $seedData = $this->readSeedData($directoryName) ?? [];
        $mergedData = array_merge($seedData, $overrideData);

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

        foreach ($mergedData as $fieldIdentifier => $value) {
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
     * Insert Collection children using custom override data instead of SeedData.yaml.
     */
    public function insertCollectionChildrenWithOverrides(
        string $directoryName,
        int $parentUid,
        int $pid,
        array $overrideData,
    ): int {
        $config = $this->readConfigYaml($directoryName);
        $cType = $this->resolveCType($directoryName);

        if ($config === null || $cType === null) {
            return 0;
        }

        // Merge: SeedData.yaml defaults + template overrides
        $seedData = $this->readSeedData($directoryName) ?? [];
        $mergedData = array_merge($seedData, $overrideData);

        $fieldDefs = [];
        foreach ($config['fields'] ?? [] as $fieldDef) {
            if (isset($fieldDef['identifier'])) {
                $fieldDefs[$fieldDef['identifier']] = $fieldDef;
            }
        }

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $seededCollections = 0;

        foreach ($mergedData as $fieldIdentifier => $value) {
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
     * Read a seed template JSON file.
     *
     * @return array{name: string, description: string, elements: list<array{component: string, data: array}>}|null
     */
    public function readSeedTemplate(string $templateName): ?array
    {
        $path = dirname(__DIR__, 2) . '/SeedTemplates/' . $templateName . '.json';
        if (!is_file($path)) {
            return null;
        }

        try {
            $content = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($content) || !isset($content['elements']) || !is_array($content['elements'])) {
            return null;
        }

        return $content;
    }

    /**
     * List available seed template names.
     *
     * @return list<array{name: string, slug: string, description: string}>
     */
    public function listSeedTemplates(): array
    {
        $dir = dirname(__DIR__, 2) . '/SeedTemplates';
        if (!is_dir($dir)) {
            return [];
        }

        $templates = [];
        foreach (new \DirectoryIterator($dir) as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->getExtension() !== 'json') {
                continue;
            }

            $slug = $fileInfo->getBasename('.json');
            $content = $this->readSeedTemplate($slug);
            if ($content !== null) {
                $templates[] = [
                    'name' => $content['name'] ?? $slug,
                    'slug' => $slug,
                    'description' => $content['description'] ?? '',
                ];
            }
        }

        return $templates;
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

    // -----------------------------------------------------------------------
    // Record type support (parallel path — no CType, own table per record type)
    // -----------------------------------------------------------------------

    /**
     * Discover all record type directory names.
     */
    public function discoverRecordTypes(): array
    {
        $basePath = $this->getRecordBlockBasePath();
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

    /**
     * Read active record types from factory.json (returns PascalCase names).
     */
    public function getActiveRecordTypes(): array
    {
        $configPath = Environment::getProjectPath() . '/factory.json';
        if (!is_file($configPath)) {
            return [];
        }

        try {
            $config = json_decode((string)file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($config) || !isset($config['active_record_types']) || !is_array($config['active_record_types'])) {
            return [];
        }

        return array_values(array_filter(
            $config['active_record_types'],
            static fn(mixed $v): bool => is_string($v) && $v !== ''
        ));
    }

    /**
     * Resolve the TYPO3 table name for a record type directory.
     * Reads the `table:` key from the record's config.yaml.
     */
    public function resolveRecordTable(string $directoryName): ?string
    {
        $config = $this->readConfigYaml($directoryName, isRecord: true);
        if ($config === null || empty($config['table']) || !is_string($config['table'])) {
            return null;
        }

        return $config['table'];
    }

    /**
     * Build a DataHandler data array for seeding a single record type entry.
     *
     * Unlike tt_content blocks, record types use their own table (e.g.
     * tx_factorycore_property) and have no CType / colPos. Field names are
     * used as-is (no cType_fieldname prefix).
     *
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    public function buildRecordDataHandlerRecord(
        string $directoryName,
        int $pid,
        int $sorting,
        string $newId,
        array $overrideData = [],
    ): ?array {
        $config = $this->readConfigYaml($directoryName, isRecord: true);
        if ($config === null) {
            return null;
        }

        $table = $this->resolveRecordTable($directoryName);
        if ($table === null) {
            return null;
        }

        $fieldDefs = [];
        foreach ($config['fields'] ?? [] as $fieldDef) {
            if (isset($fieldDef['identifier'])) {
                $fieldDefs[$fieldDef['identifier']] = $fieldDef;
            }
        }

        $data = $overrideData;
        $record = [
            'pid' => $pid,
            'sorting' => $sorting,
        ];

        foreach ($data as $fieldIdentifier => $value) {
            $fieldDef = $fieldDefs[$fieldIdentifier] ?? null;
            $fieldType = $fieldDef['type'] ?? null;

            // Skip non-scalar types that can't be seeded via DataHandler
            if (in_array($fieldType, ['File', 'Record', 'Relation', 'Folder', 'Collection', 'Category', 'Tab'], true)) {
                continue;
            }

            $record[$fieldIdentifier] = $value;
        }

        return [
            $table => [
                $newId => $record,
            ],
        ];
    }
}
