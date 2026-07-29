<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\DataProcessing;

use LaborDigital\FactoryCore\Middleware\RecordPageMiddleware;
use LaborDigital\FactoryCore\Service\RecordSerializer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Emits the record a `factory_recorddetail` element should render (DL #030).
 *
 * `RecordPageMiddleware` has already matched `/<type>/<slug>` and attached the
 * resolved record to the request; this reads it, serialises the record's fields,
 * and resolves the record's BODY — the inline tt_content children — into the same
 * JSON the page renderer consumes for a page's own content.
 *
 * Output on $processedData (as `record` by default):
 *
 *   [
 *     '_record_type' => 'property',
 *     'uid' => 42, 'title' => '…', 'tag' => ['mfh'], 'hero_image' => [ … ],
 *     'content_elements' => [ { id, type: 'factory_hero', content: {…} }, … ],
 *   ]
 *
 * The Vue wrapper renders the typed header from the fields and hands
 * `content_elements` to FactoryPageRenderer — the same dispatch a page uses, so
 * every active block works inside a record with no per-type work.
 *
 * When no record is on the request the element emits `null`, which is how the
 * type's page renders as a plain LIST (the same page serves both, so the detail
 * element simply stays quiet).
 *
 * Wired in Configuration/TypoScript/ContentElement/RecordDetail.typoscript.
 */
final class RecordDetailProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly RecordSerializer $serializer,
    ) {}

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $as = (string)($processorConfiguration['as'] ?? 'record');

        $resolved = $this->readRequestAttribute($cObj);
        if ($resolved === null) {
            $processedData[$as] = null;
            return $processedData;
        }

        $row = $this->fetchRow($resolved['table'], $resolved['uid']);
        if ($row === null) {
            $processedData[$as] = null;
            return $processedData;
        }

        $record = $this->serializer->serialize($resolved['type'], $resolved['table'], $row);
        $record['content_elements'] = $this->resolveBody($cObj, $resolved['table'], $resolved['uid']);

        $processedData[$as] = $record;

        return $processedData;
    }

    /**
     * @return array{type: string, table: string, uid: int}|null
     */
    private function readRequestAttribute(ContentObjectRenderer $cObj): ?array
    {
        $request = $cObj->getRequest();
        $attribute = $request->getAttribute(RecordPageMiddleware::ATTRIBUTE);
        if (!is_array($attribute)) {
            return null;
        }
        $type = (string)($attribute['type'] ?? '');
        $table = (string)($attribute['table'] ?? '');
        $uid = (int)($attribute['uid'] ?? 0);
        if ($type === '' || $table === '' || $uid <= 0) {
            return null;
        }

        return ['type' => $type, 'table' => $table, 'uid' => $uid];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $table, int $uid): ?array
    {
        try {
            $qb = $this->connectionPool->getQueryBuilderForTable($table);
            $row = $qb->select('*')
                ->from($table)
                ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Render the record's inline tt_content children through the NORMAL content
     * pipeline, so each block produces exactly the JSON it would produce on a
     * page — including its own DataProcessors.
     *
     * Rendering rather than reading the rows directly is the whole point: a block's
     * JSON is built by `lib.contentBlock` (nb-headless-content-blocks) plus that
     * block's processors, and reimplementing that here would drift from how the
     * same block renders on a page.
     *
     * @return list<array<string, mixed>>
     */
    private function resolveBody(ContentObjectRenderer $cObj, string $table, int $uid): array
    {
        $childUids = $this->findChildUids($table, $uid);
        if ($childUids === []) {
            return [];
        }

        $content = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $content->setRequest($cObj->getRequest());
        $content->start([], '');

        $elements = [];
        foreach ($childUids as $childUid) {
            // One RECORDS call per child, decoded on its own.
            //
            // A single CONTENT call would be fewer queries, but its renderObj
            // output is concatenated with no separator — so N JSON objects come
            // back as one unparseable string, and any wrap trick to insert commas
            // breaks the moment a block renders empty. Per-element decoding cannot
            // produce a malformed array.
            $rendered = $content->cObjGetSingle('RECORDS', [
                'tables' => 'tt_content',
                'source' => (string)$childUid,
                // Children live on the record's storage page, not the page being
                // rendered, so the default pid check would drop all of them.
                'dontCheckPid' => '1',
            ]);

            $decoded = json_decode(trim((string)$rendered), true);
            if (is_array($decoded) && $decoded !== []) {
                $elements[] = $decoded;
            }
        }

        return $elements;
    }

    /**
     * The record's inline tt_content children, in editor order.
     *
     * @return list<int>
     */
    private function findChildUids(string $table, int $uid): array
    {
        try {
            $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
            $rows = $qb->select('uid')
                ->from('tt_content')
                ->where(
                    $qb->expr()->eq('tx_factorycore_record_parent', $qb->createNamedParameter($uid, Connection::PARAM_INT)),
                    $qb->expr()->eq('tx_factorycore_record_parent_table', $qb->createNamedParameter($table)),
                )
                ->orderBy('sorting')
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn(array $row): int => (int)$row['uid'], $rows);
    }
}
