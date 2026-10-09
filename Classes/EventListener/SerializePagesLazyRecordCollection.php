<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\EventListener;

use Netzbewegung\NbHeadlessContentBlocks\Event\ModifyArrayRecursiveToArrayEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Collection\LazyRecordCollection;

/**
 * nb-headless-content-blocks can only serialize LazyRecordCollections for
 * Content Block-defined tables + sys_category. When a CE has a Relation field
 * pointing to a core table like `pages` (e.g., a folder picker), the default
 * serializer throws. This listener intercepts those cases and emits a flat
 * array of page UIDs instead — which is what DataProcessors like
 * ReferenceListProcessor need anyway.
 */
#[AsEventListener(identifier: 'factory-core/serialize-pages-lazy-record-collection')]
final class SerializePagesLazyRecordCollection
{
    public function __invoke(ModifyArrayRecursiveToArrayEvent $event): void
    {
        $value = $event->getValue();
        if (!$value instanceof LazyRecordCollection) {
            return;
        }

        $uids = [];
        foreach ($value as $record) {
            $table = $record->getRawRecord()->getMainType();
            if ($table !== 'pages') {
                return;
            }
            $uids[] = (int)$record->getUid();
        }

        $event->setProcessedValue($uids);
    }
}
