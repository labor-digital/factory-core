<?php

declare(strict_types=1);

defined('TYPO3') or die();

/*
 * Factory Text fields become TEXT columns, not VARCHAR(255).
 *
 * Every Content Block field lands on tt_content, and TYPO3 13 turns a TCA `input`
 * column into VARCHAR(255) unless its `max` exceeds 255 (DefaultTcaSchema). In
 * utf8mb4 that is up to 1,020 bytes of the 65,535-byte row limit PER FIELD — and
 * factory-core 2.x has well over a hundred of them, so MySQL refuses to create
 * tt_content at all ("Row size too large"), and on an existing database the ALTER
 * that adds new fields fails the same way. TYPO3's extension:setup still exits 0,
 * so the failure is silent until a page renders without its fields. Found booting
 * labor-factory-multitenant on 2.5.0, 2026-10-07.
 *
 * A TEXT column costs only a pointer in the row. Doing it here rather than with
 * `max:` on each of ~260 field definitions means a new block cannot reintroduce
 * the problem. Only factory_* columns are touched; a field that declares its own
 * `max` keeps it. Converting an existing VARCHAR to TEXT keeps its data.
 */
$factoryTextMax = 2048;

foreach ($GLOBALS['TCA']['tt_content']['columns'] ?? [] as $column => $definition) {
    if (!str_starts_with((string)$column, 'factory_')) {
        continue;
    }
    if (($definition['config']['type'] ?? null) !== 'input' || isset($definition['config']['max'])) {
        continue;
    }
    $GLOBALS['TCA']['tt_content']['columns'][$column]['config']['max'] = $factoryTextMax;
}
