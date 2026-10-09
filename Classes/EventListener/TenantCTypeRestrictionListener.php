<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\EventListener;

use LaborDigital\FactoryCore\Configuration\FactoryComponentRegistry;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\ModifyLoadedPageTsConfigEvent;

/**
 * Shared-tenant CType/RecordType gating.
 *
 * Each site owns a `config/sites/{id}/factory.json`. This listener fires per
 * BE page view, resolves the site from the rootline, and emits page TSconfig
 * that hides inactive CTypes and RecordTypes for that site only. Gating is
 * page-context-driven so a be_user in multiple tenant groups sees the
 * correct subset per page — group TSconfig would union `removeItems` across
 * groups, which widens restrictions incorrectly.
 *
 * No-ops in dedicated-instance mode (no per-site factory.json). There the
 * bootstrap-time TCA overrides remain authoritative.
 */
#[AsEventListener(identifier: 'factory-core/tenant-ctype-restriction')]
final class TenantCTypeRestrictionListener
{
    public function __construct(private readonly SiteFinder $siteFinder) {}

    public function __invoke(ModifyLoadedPageTsConfigEvent $event): void
    {
        if (!FactoryComponentRegistry::hasAnySiteConfig()) {
            return;
        }

        $site = $this->resolveSite($event->getRootLine());
        if ($site === null || !FactoryComponentRegistry::hasSiteConfig($site->getIdentifier())) {
            return;
        }

        $config = FactoryComponentRegistry::loadSiteConfig($site->getIdentifier());

        $lines = [];

        $inactiveCTypes = $this->resolveInactiveCTypes($config['active_components']);
        if ($inactiveCTypes !== []) {
            $lines[] = 'TCEFORM.tt_content.CType.removeItems := addToList(' . implode(',', $inactiveCTypes) . ')';
        }

        foreach ($this->resolveInactiveRecordTables($config['active_record_types']) as $table) {
            $lines[] = 'options.hideRecords.' . $table . ' = 1';
        }

        if ($lines !== []) {
            $event->addTsConfig(implode("\n", $lines));
        }
    }

    /** @param array<int,array<string,mixed>> $rootLine */
    private function resolveSite(array $rootLine): ?Site
    {
        foreach ($rootLine as $page) {
            $uid = (int)($page['uid'] ?? 0);
            if ($uid === 0) {
                continue;
            }
            try {
                return $this->siteFinder->getSiteByPageId($uid);
            } catch (SiteNotFoundException) {
                continue;
            }
        }
        return null;
    }

    /**
     * @param list<string> $active
     * @return list<string>
     */
    private function resolveInactiveCTypes(array $active): array
    {
        $activeMap = array_fill_keys($active, true);
        $inactive = [];
        foreach (FactoryComponentRegistry::discoverContentBlocks() as $component) {
            if (isset($activeMap[$component['key']])) {
                continue;
            }
            foreach (FactoryComponentRegistry::buildCTypeCandidates($component) as $cType) {
                $inactive[$cType] = true;
            }
        }
        return array_keys($inactive);
    }

    /**
     * @param list<string> $active
     * @return list<string>
     */
    private function resolveInactiveRecordTables(array $active): array
    {
        $activeMap = array_fill_keys($active, true);
        $tables = [];
        foreach (FactoryComponentRegistry::discoverRecordTypes() as $record) {
            if (isset($activeMap[$record['key']]) || $record['table'] === null) {
                continue;
            }
            $tables[] = $record['table'];
        }
        return $tables;
    }
}
