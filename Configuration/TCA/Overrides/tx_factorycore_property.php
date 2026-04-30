<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

/*
 * Content Blocks emits the `content_elements` field as a `group/db` Relation, but
 * we want an IRRE (inline) relation to tt_content rows pointed at this property —
 * the same pattern EXT:news uses for tx_news_domain_model_news.content_elements.
 *
 * Override the column to `type: inline` with foreign_field / foreign_table_field
 * pointing at two passthrough pointer columns on tt_content (added below).
 */
if (isset($GLOBALS['TCA']['tx_factorycore_property']['columns']['content_elements'])) {
    $GLOBALS['TCA']['tx_factorycore_property']['columns']['content_elements']['config'] = [
        'type' => 'inline',
        'foreign_table' => 'tt_content',
        'foreign_field' => 'tx_factorycore_property_parent',
        'foreign_table_field' => 'tx_factorycore_property_parent_table',
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
 * Add the two passthrough pointer columns to tt_content, so a tt_content row can
 * declare itself as belonging to a specific tx_factorycore_property (or, in the
 * future, any other record type using the same pattern).
 */
ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_factorycore_property_parent' => [
        'config' => ['type' => 'passthrough'],
    ],
    'tx_factorycore_property_parent_table' => [
        'config' => ['type' => 'passthrough'],
    ],
]);
