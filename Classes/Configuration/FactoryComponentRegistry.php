<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Configuration;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Reads the client's `factory.json` and discovers factory-core ContentBlocks
 * and RecordTypes from disk. Shared by:
 *  - Configuration/TCA/Overrides/hide_inactive_content_blocks.php (CType filter)
 *  - Configuration/TCA/Overrides/hide_inactive_records.php (record-type filter)
 *  - the legacy ext_localconf.php (reads `active_components` for any other use)
 *
 * Discovery is filesystem-based so it works during early TCA bootstrap, before
 * the Content Blocks loader has populated its registry.
 */
final class FactoryComponentRegistry
{
    private const CONTENT_ELEMENTS_DIR = 'ContentBlocks/ContentElements';
    private const RECORD_TYPES_DIR = 'ContentBlocks/RecordTypes';

    /** @var array{core_version:string,active_components:list<string>,active_record_types:list<string>}|null */
    private static ?array $config = null;

    /**
     * @return array{core_version:string,active_components:list<string>,active_record_types:list<string>}
     */
    public static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = Environment::getProjectPath() . '/factory.json';
        $config = [
            'core_version' => '',
            'active_components' => [],
            'active_record_types' => [],
        ];

        if (is_file($path)) {
            try {
                $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }

            if (is_array($decoded)) {
                if (isset($decoded['core_version']) && is_string($decoded['core_version'])) {
                    $config['core_version'] = $decoded['core_version'];
                }
                $config['active_components'] = self::normalizeKeyList($decoded['active_components'] ?? []);
                $config['active_record_types'] = self::normalizeKeyList($decoded['active_record_types'] ?? []);
            }
        }

        return self::$config = $config;
    }

    /**
     * Discover every factory ContentBlock (ContentElement) on disk.
     *
     * @return list<array{key:string,qualifiedName:?string}>
     */
    public static function discoverContentBlocks(): array
    {
        return self::discover(self::CONTENT_ELEMENTS_DIR);
    }

    /**
     * Discover every factory RecordType on disk and read its `table:` value.
     *
     * @return list<array{key:string,table:?string}>
     */
    public static function discoverRecordTypes(): array
    {
        $path = ExtensionManagementUtility::extPath('factory_core') . self::RECORD_TYPES_DIR;
        if (!is_dir($path)) {
            return [];
        }

        $records = [];
        foreach (new \DirectoryIterator($path) as $info) {
            if ($info->isDot() || !$info->isDir()) {
                continue;
            }
            $key = self::normalizeKey($info->getFilename());
            if ($key === '') {
                continue;
            }

            $configYamlPath = $info->getPathname() . '/config.yaml';
            $table = null;
            if (is_file($configYamlPath)) {
                $yaml = (string)file_get_contents($configYamlPath);
                if (preg_match("/^table:\\s*['\"]?([A-Za-z0-9_]+)['\"]?/m", $yaml, $matches) === 1) {
                    $table = $matches[1];
                }
            }

            $records[] = ['key' => $key, 'table' => $table];
        }

        return $records;
    }

    /**
     * Build all CType identifier candidates that the content-blocks loader
     * might generate for a given component (handles vendor/name + dash/underscore
     * permutations).
     *
     * @param array{key:string,qualifiedName:?string} $component
     * @return list<string>
     */
    public static function buildCTypeCandidates(array $component): array
    {
        $candidates = [];
        $key = str_replace('-', '_', $component['key']);
        if ($key !== '') {
            $candidates[] = $key;
            $candidates[] = str_replace('_', '', $key);
        }

        $qualified = $component['qualifiedName'] ?? null;
        if (is_string($qualified) && str_contains($qualified, '/')) {
            [$vendor, $name] = explode('/', $qualified, 2);
            $vendorSlug = self::normalizeKey($vendor);
            $nameSlug = self::normalizeKey($name);
            if ($vendorSlug !== '' && $nameSlug !== '') {
                $vendorUnderscore = str_replace('-', '_', $vendorSlug);
                $vendorCompact = str_replace('-', '', $vendorSlug);
                $nameUnderscore = str_replace('-', '_', $nameSlug);
                $nameCompact = str_replace('-', '', $nameSlug);
                $candidates[] = $nameUnderscore;
                $candidates[] = $vendorUnderscore . '_' . $nameUnderscore;
                $candidates[] = $vendorCompact . '_' . $nameUnderscore;
                $candidates[] = $vendorUnderscore . '_' . $nameCompact;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    public static function normalizeKey(string $value): string
    {
        $value = trim($value);
        $value = (string)preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value);
        $value = strtolower($value);
        $value = (string)preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }

    /**
     * @return list<array{key:string,qualifiedName:?string}>
     */
    private static function discover(string $relativeDir): array
    {
        $path = ExtensionManagementUtility::extPath('factory_core') . $relativeDir;
        if (!is_dir($path)) {
            return [];
        }

        $components = [];
        foreach (new \DirectoryIterator($path) as $info) {
            if ($info->isDot() || !$info->isDir()) {
                continue;
            }
            $key = self::normalizeKey($info->getFilename());
            if ($key === '') {
                continue;
            }
            $components[] = [
                'key' => $key,
                'qualifiedName' => self::readQualifiedName($info->getPathname() . '/config.yaml'),
            ];
        }

        return $components;
    }

    private static function readQualifiedName(string $configYamlPath): ?string
    {
        if (!is_file($configYamlPath)) {
            return null;
        }
        $yaml = (string)file_get_contents($configYamlPath);
        if (preg_match("/^name:\\s*['\"]?([^'\"\\n]+)['\"]?/m", $yaml, $matches) !== 1) {
            return null;
        }
        return trim($matches[1]);
    }

    /**
     * @return list<string>
     */
    private static function normalizeKeyList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            $key = self::normalizeKey($value);
            if ($key !== '') {
                $out[] = $key;
            }
        }
        return array_values(array_unique($out));
    }
}
