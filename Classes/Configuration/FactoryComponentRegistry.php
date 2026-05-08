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

    /** @var array<string,array{core_version:string,active_components:list<string>,active_record_types:list<string>}> */
    private static array $siteConfigs = [];

    /**
     * Project-root factory.json (dedicated-instance mode).
     *
     * @return array{core_version:string,active_components:list<string>,active_record_types:list<string>}
     */
    public static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        return self::$config = self::loadConfigFile(Environment::getProjectPath() . '/factory.json');
    }

    /**
     * Per-site factory.json (shared-tenant mode). Reads
     * `config/sites/{siteIdentifier}/factory.json`. Returns an empty
     * config when the file is missing; the caller decides whether to
     * fall back to the project-root config.
     *
     * @return array{core_version:string,active_components:list<string>,active_record_types:list<string>}
     */
    public static function loadSiteConfig(string $siteIdentifier): array
    {
        if (isset(self::$siteConfigs[$siteIdentifier])) {
            return self::$siteConfigs[$siteIdentifier];
        }
        $path = Environment::getProjectPath() . '/config/sites/' . $siteIdentifier . '/factory.json';
        return self::$siteConfigs[$siteIdentifier] = self::loadConfigFile($path);
    }

    /**
     * True when the given site has its own `factory.json`. Distinguishes
     * "site not yet onboarded" from "site exists with empty active_components".
     */
    public static function hasSiteConfig(string $siteIdentifier): bool
    {
        return is_file(Environment::getProjectPath() . '/config/sites/' . $siteIdentifier . '/factory.json');
    }

    /**
     * True when a project-root `factory.json` exists (dedicated-instance mode).
     */
    public static function hasRootConfig(): bool
    {
        return is_file(Environment::getProjectPath() . '/factory.json');
    }

    /**
     * True when at least one site under `config/sites/` ships its own
     * `factory.json`. Used by the legacy TCA strip overrides to no-op
     * in shared-tenant mode (where gating happens per-request via
     * TSconfig instead of globally at bootstrap).
     */
    public static function hasAnySiteConfig(): bool
    {
        $sitesDir = Environment::getProjectPath() . '/config/sites';
        if (!is_dir($sitesDir)) {
            return false;
        }
        foreach (new \DirectoryIterator($sitesDir) as $info) {
            if ($info->isDot() || !$info->isDir()) {
                continue;
            }
            if (is_file($info->getPathname() . '/factory.json')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Drops the in-process static cache for a single site (or for everything
     * when null). Call after rewriting `config/sites/{slug}/factory.json` so
     * the next request on this PHP-FPM worker re-reads from disk. Other
     * workers (other ECS tasks, other FPM children) catch up on their own
     * recycle cadence — see DL #013 for the multi-instance caveat.
     */
    public static function invalidate(?string $siteIdentifier = null): void
    {
        if ($siteIdentifier === null) {
            self::$siteConfigs = [];
            self::$config = null;
            return;
        }
        unset(self::$siteConfigs[$siteIdentifier]);
    }

    /**
     * @return array{core_version:string,active_components:list<string>,active_record_types:list<string>}
     */
    private static function loadConfigFile(string $path): array
    {
        $config = [
            'core_version' => '',
            'active_components' => [],
            'active_record_types' => [],
        ];

        if (!is_file($path)) {
            return $config;
        }

        try {
            $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $config;
        }

        if (!is_array($decoded)) {
            return $config;
        }

        if (isset($decoded['core_version']) && is_string($decoded['core_version'])) {
            $config['core_version'] = $decoded['core_version'];
        }
        $config['active_components'] = self::normalizeKeyList($decoded['active_components'] ?? []);
        $config['active_record_types'] = self::normalizeKeyList($decoded['active_record_types'] ?? []);

        return $config;
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
