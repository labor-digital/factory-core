<?php

declare(strict_types=1);

defined('TYPO3') or die();

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
