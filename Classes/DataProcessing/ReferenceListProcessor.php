<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\DataProcessing;

use LaborDigital\FactoryCore\Service\ContentBlockSeeder;
use LaborDigital\FactoryCore\Service\RecordSerializer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Resolves the records selected (manual or auto) in a ReferenceList CE and
 * serialises each to the wire shape consumed by BaseRecordProperty.vue (and
 * any other BaseRecord*.vue component).
 *
 * Output shape on $processedData:
 *   'records' => [
 *     [
 *       '_record_type' => 'property',
 *       'uid' => 42,
 *       'title' => '...',
 *       'tag' => ['mfh'],
 *       'listing_type' => ['buy'],
 *       'price' => '1200000.00',
 *       ...
 *       'hero_image' => [ { publicUrl, properties } ]
 *     ], ...
 *   ]
 *
 * Wired via Configuration/TypoScript/ContentElement/ReferenceList.typoscript.
 */
final class ReferenceListProcessor implements DataProcessorInterface
{
    /** Field names on tt_content created by Content Blocks — CType prefix + '_' + identifier. */
    private const FIELD_PREFIX = 'factory_referencelist_';

    public function __construct(
        private readonly ContentBlockSeeder $seeder,
        private readonly ConnectionPool $connectionPool,
        private readonly RecordSerializer $serializer,
    ) {}

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $data = $processedData['data'] ?? [];
        $recordTypeSlug = (string)($data[self::FIELD_PREFIX . 'record_type'] ?? '');
        $mode           = (string)($data[self::FIELD_PREFIX . 'selection_mode'] ?? 'manual');

        if ($recordTypeSlug === '') {
            $processedData['records'] = [];
            return $processedData;
        }

        $table = $this->resolveTableForSlug($recordTypeSlug);
        if ($table === null) {
            $processedData['records'] = [];
            return $processedData;
        }

        $uids = $mode === 'auto'
            ? $this->queryAuto($table, $recordTypeSlug, $data)
            : $this->resolveManual($data, $recordTypeSlug);

        $records = [];
        foreach ($uids as $uid) {
            $row = $this->fetchRow($table, (int)$uid);
            if ($row !== null) {
                $records[] = $this->serializer->serialize($recordTypeSlug, $table, $row);
            }
        }

        $as = (string)($processorConfiguration['as'] ?? 'records');
        $processedData[$as] = $records;

        return $processedData;
    }

    private function resolveTableForSlug(string $slug): ?string
    {
        // The stored value is the PUBLIC slug ("property"); the block directory
        // carries the record marker ("record_property"). One helper owns that
        // mapping — see ContentBlockSeeder (DL #030 naming convention).
        return $this->seeder->resolveRecordTable($this->seeder->recordDirectoryForSlug($slug));
    }

    /**
     * Manual mode: read the records_<slug> relation column.
     *
     * @return array<int>
     */
    private function resolveManual(array $data, string $slug): array
    {
        // One generic `records` column for every record type (DL #030). The
        // per-type `records_<slug>` column is still READ so content picked
        // before the change keeps rendering — the declared rename in the
        // Teaser's manifest is the same fallback, expressed for the guards.
        $raw = $data[self::FIELD_PREFIX . 'records'] ?? '';
        if (!is_string($raw) || $raw === '') {
            $legacyColumn = self::FIELD_PREFIX . 'records_' . str_replace('-', '_', $slug);
            $raw = $data[$legacyColumn] ?? '';
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        return array_values(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn(int $uid): bool => $uid > 0,
        ));
    }

    /**
     * Auto mode: run a query against the record table using the editor's filters.
     *
     * @return array<int>
     */
    private function queryAuto(string $table, string $slug, array $data): array
    {
        $storagePidsRaw = (string)($data[self::FIELD_PREFIX . 'auto_storage_pid'] ?? '');
        $storagePids = array_values(array_filter(
            array_map('intval', explode(',', $storagePidsRaw)),
            static fn(int $pid): bool => $pid > 0,
        ));
        $limit      = (int)($data[self::FIELD_PREFIX . 'auto_limit'] ?? 6);
        $orderRaw   = (string)($data[self::FIELD_PREFIX . 'auto_order'] ?? 'crdate desc');
        $listingFilter = (string)($data[self::FIELD_PREFIX . 'auto_filter_listing_type'] ?? '');

        [$orderField, $orderDir] = $this->parseOrder($orderRaw);

        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->select('uid')
            ->from($table);

        if ($storagePids !== []) {
            $qb->where($qb->expr()->in(
                'pid',
                $qb->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY),
            ));
        }

        // Only Property supports listing_type for now; guard by slug.
        if ($listingFilter !== '' && $slug === 'property') {
            $qb->andWhere($qb->expr()->eq(
                'listing_type',
                $qb->createNamedParameter($listingFilter),
            ));
        }

        $qb->orderBy($orderField, $orderDir);
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        $result = $qb->executeQuery()->fetchAllAssociative();
        return array_map(static fn(array $row): int => (int)$row['uid'], $result);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseOrder(string $raw): array
    {
        $parts = preg_split('/\s+/', trim($raw)) ?: ['crdate', 'desc'];
        $field = $parts[0] ?? 'crdate';
        $dir = strtoupper($parts[1] ?? 'desc');
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'DESC';
        }
        // Whitelist sortable columns to avoid TCA injection
        if (!in_array($field, ['crdate', 'tstamp', 'title', 'sorting', 'uid'], true)) {
            $field = 'crdate';
        }
        return [$field, $dir];
    }

    private function fetchRow(string $table, int $uid): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $row = $qb->select('*')
            ->from($table)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

}
