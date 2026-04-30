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

// ReferenceList: upgrade `auto_storage_pid` from CB's Text type to a native
// multi-page picker. See ContentBlocks/ContentElements/reference_list/config.yaml
// for the rationale (nb-headless-content-blocks cannot serialize `pages`
// LazyRecordCollection, so we keep the CB schema as Text while giving editors a
// proper page picker here). The column stores a CSV of page UIDs; the
// ReferenceListProcessor parses it.
$column = 'factory_referencelist_auto_storage_pid';
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
