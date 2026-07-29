<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

/*
 * Record types: inline content bodies (DL #030).
 *
 * Every record type is a page — it has a slug and a `content_elements` body that
 * accepts any active Content Block (DL #030 "A record is a page"). Content Blocks
 * emits `content_elements` as a `group/db` Relation, so we override it to an IRRE
 * (inline) relation to tt_content — the pattern EXT:news uses for
 * tx_news_domain_model_news.content_elements.
 *
 * This pass is GENERIC: it walks every `tx_factorycore_record_*` table that has a
 * `content_elements` column instead of naming one table, so adding a record type
 * needs no file here. It replaced the per-type override (formerly
 * tx_factorycore_property.php) when the pointer columns became shared.
 *
 * The pointer columns are ONE shared pair for all record types, not a pair per
 * table: `foreign_table_field` stores the owning table name, which is what
 * disambiguates a tt_content row's parent.
 */

$recordTables = array_filter(
    array_keys($GLOBALS['TCA'] ?? []),
    static fn (string $table): bool => str_starts_with($table, 'tx_factorycore_record_')
        && isset($GLOBALS['TCA'][$table]['columns']['content_elements'])
);

foreach ($recordTables as $table) {
    $GLOBALS['TCA'][$table]['columns']['content_elements']['config'] = [
        'type' => 'inline',
        'foreign_table' => 'tt_content',
        'foreign_field' => 'tx_factorycore_record_parent',
        'foreign_table_field' => 'tx_factorycore_record_parent_table',
        'foreign_sortby' => 'sorting',
        'maxitems' => 999,
        'appearance' => [
            'collapseAll' => true,
            'expandSingle' => true,
            'levelLinksPosition' => 'top',
            'useSortable' => true,
            'showPossibleLocalizationRecords' => true,
            'showAllLocalizationLink' => true,
            'showSynchronizationLink' => true,
            'enabledControls' => [
                'info' => true,
                'dragdrop' => true,
                'hide' => true,
                'delete' => true,
                'localize' => true,
            ],
        ],
        'behaviour' => [
            'enableCascadingDelete' => true,
        ],
    ];
}

/*
 * The shared passthrough pointer columns on tt_content. Added unconditionally
 * (not inside the loop) so the schema is stable even before Content Blocks has
 * registered any record table — the matching SQL lives in ext_tables.sql.
 */
ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_factorycore_record_parent' => [
        'config' => ['type' => 'passthrough'],
    ],
    'tx_factorycore_record_parent_table' => [
        'config' => ['type' => 'passthrough'],
    ],
]);
