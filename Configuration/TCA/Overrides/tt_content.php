<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Wizard group for all factory ContentBlocks. CBs declare `group: factory` in
// their config.yaml; this registers the group label and its position in the
// New Content Element wizard.
ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content',
    'CType',
    'factory',
    'Factory',
    'before:default',
);

// Teaser: upgrade `auto_storage_pid` from CB's Text type to a native
// multi-page picker. See ContentBlocks/ContentElements/teaser/config.yaml
// for the rationale (nb-headless-content-blocks cannot serialize `pages`
// LazyRecordCollection, so we keep the CB schema as Text while giving editors a
// proper page picker here). The column stores a CSV of page UIDs; the
// ReferenceListProcessor parses it.
$column = 'factory_teaser_auto_storage_pid';
if (isset($GLOBALS['TCA']['tt_content']['columns'][$column])) {
    $GLOBALS['TCA']['tt_content']['columns'][$column]['config'] = [
        'type' => 'group',
        'allowed' => 'pages',
        'maxitems' => 10,
        'minitems' => 0,
        'size' => 5,
        'suggestOptions' => [
            'default' => [
                'searchWholePhrase' => true,
            ],
        ],
    ];
}

/*
 * Record pickers: turn the record-list blocks' opaque `records` CSV into a
 * native picker over every DISCOVERED record type (DL #030).
 *
 * `allowed` is built from disk rather than listed here, so adding a record type
 * is still just dropping a directory — which is what DL #010 wanted from the
 * abandoned `records_<slug>` pass, without needing one column (and one generated
 * SQL statement) per type.
 *
 * Discovery, not activation: a client's inactive types are hidden by
 * hide_inactive_records.php, and TCA is shared across tenants in a multi-tenant
 * install, so filtering by one tenant's factory.json here would be wrong.
 */
$recordTables = [];
foreach (\LaborDigital\FactoryCore\Configuration\FactoryComponentRegistry::discoverRecordTypes() as $recordType) {
    if (!empty($recordType['table'])) {
        $recordTables[] = $recordType['table'];
    }
}

if ($recordTables !== []) {
    foreach (['factory_teaser_records', 'factory_recordlist_records'] as $recordColumn) {
        if (!isset($GLOBALS['TCA']['tt_content']['columns'][$recordColumn])) {
            continue;
        }
        $GLOBALS['TCA']['tt_content']['columns'][$recordColumn]['config'] = [
            'type' => 'group',
            'allowed' => implode(',', $recordTables),
            'maxitems' => 24,
            'minitems' => 0,
            'size' => 6,
            'suggestOptions' => [
                'default' => [
                    'searchWholePhrase' => true,
                ],
            ],
        ];
    }
}
