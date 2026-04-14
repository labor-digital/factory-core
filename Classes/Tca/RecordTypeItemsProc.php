<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Tca;

use LaborDigital\FactoryCore\Service\ContentBlockSeeder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Populates the ReferenceList CE's `record_type` dropdown from the client's
 * active_record_types list in factory.json.
 *
 * Wiring: see ContentBlocks/ContentElements/reference_list/config.yaml →
 *   itemsProcFunc: LaborDigital\FactoryCore\Tca\RecordTypeItemsProc->getItems
 *
 * Instantiated by TYPO3 via `GeneralUtility::makeInstance()` without DI, so
 * we cannot accept constructor arguments. Fetch the ContentBlockSeeder via
 * `makeInstance()` inside the method.
 */
final class RecordTypeItemsProc
{
    public function getItems(array &$params): void
    {
        $seeder = GeneralUtility::makeInstance(ContentBlockSeeder::class);
        foreach ($seeder->getActiveRecordTypes() as $name) {
            $slug = $seeder->toKebabCase($name);
            $params['items'][] = [
                'label' => $name,
                'value' => $slug,
            ];
        }
    }
}
