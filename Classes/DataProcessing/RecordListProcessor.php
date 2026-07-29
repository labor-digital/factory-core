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
 * Resolves the records a `factory_record_list` element should show (DL #030).
 *
 * The Vue side of this block was written for CMS-free mode, where records arrive
 * through a broker-backed loader the ai-layer registers. In TYPO3 mode no such
 * loader exists, so the block rendered an empty list — and `factory_record_list`
 * was not even a registered CType, so TYPO3 could never emit it in the first
 * place. This is the server half that makes the same block work on both sources.
 *
 * Emits the same wire shape as ReferenceListProcessor (via the shared
 * RecordSerializer), so a card cannot tell which block or which data source it
 * was rendered from.
 *
 * Wired in Configuration/TypoScript/ContentElement/RecordList.typoscript.
 */
final class RecordListProcessor implements DataProcessorInterface
{
    private const FIELD_PREFIX = 'factory_recordlist_';

    private const DEFAULT_LIMIT = 6;

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
        $as = (string)($processorConfiguration['as'] ?? 'records');
        $data = $processedData['data'] ?? [];

        $slug = (string)($data[self::FIELD_PREFIX . 'record_type'] ?? '');
        $table = $slug !== ''
            ? $this->seeder->resolveRecordTable($this->seeder->recordDirectoryForSlug($slug))
            : null;
        if ($table === null) {
            $processedData[$as] = [];
            return $processedData;
        }

        $limit = (int)($data[self::FIELD_PREFIX . 'limit'] ?? self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $picked = $this->parseUidList((string)($data[self::FIELD_PREFIX . 'records'] ?? ''));
        $rows = $picked !== []
            ? $this->fetchPicked($table, $picked)
            : $this->fetchNewest($table, $limit);

        $records = [];
        foreach ($rows as $row) {
            $records[] = $this->serializer->serialize($slug, $table, $row);
        }

        $processedData[$as] = $records;

        return $processedData;
    }

    /** @return list<int> */
    private function parseUidList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn(int $uid): bool => $uid > 0,
        ));
    }

    /**
     * @param list<int> $uids
     * @return list<array<string, mixed>>
     */
    private function fetchPicked(string $table, array $uids): array
    {
        try {
            $qb = $this->connectionPool->getQueryBuilderForTable($table);
            $rows = $qb->select('*')
                ->from($table)
                ->where($qb->expr()->in('uid', $qb->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        // Preserve the EDITOR's order, which the IN() result does not.
        $byUid = [];
        foreach ($rows as $row) {
            $byUid[(int)$row['uid']] = $row;
        }
        $ordered = [];
        foreach ($uids as $uid) {
            if (isset($byUid[$uid])) {
                $ordered[] = $byUid[$uid];
            }
        }

        return $ordered;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchNewest(string $table, int $limit): array
    {
        try {
            $qb = $this->connectionPool->getQueryBuilderForTable($table);
            $rows = $qb->select('*')
                ->from($table)
                ->orderBy('crdate', 'DESC')
                ->setMaxResults($limit)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        return $rows;
    }
}
