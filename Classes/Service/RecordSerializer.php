<?php

declare(strict_types=1);

namespace LaborDigital\FactoryCore\Service;

use TYPO3\CMS\Core\Resource\FileRepository;

/**
 * Serialise a record row into the wire shape the Vue side reads (DL #030).
 *
 * One implementation for both consumers — the card path (ReferenceListProcessor,
 * for a Teaser listing) and the page path (RecordDetailProcessor, for a record's
 * own page). They must agree: a card and a page header read the same fields
 * through the same helpers (`unwrapSelect`, `parseFile`), so two serialisers
 * would eventually disagree and only one of the two surfaces would break.
 *
 * The field TYPES are derived from the record type's `config.yaml`, not listed
 * here. The previous version hardcoded `if ($slug === 'property')` plus two
 * arrays of field names, which meant a new record type silently got no select
 * unwrapping and no file resolution — its card would render raw values and no
 * images, with nothing to indicate why.
 *
 * Shape contract (matches nuxt-layer/utils):
 *   - Select fields become single-element ARRAYS, because `unwrapSelect` accepts
 *     both but TYPO3's headless API has always emitted arrays.
 *   - File fields become arrays of `{ publicUrl, properties }`, because
 *     `parseFile` returns null for anything that is not an array.
 */
final class RecordSerializer
{
    /** Content Blocks field types whose value must be wrapped in an array. */
    private const SELECT_TYPES = ['Select', 'Radio'];

    /** Content Blocks field types backed by file references. */
    private const FILE_TYPES = ['File', 'Image'];

    /** @var array<string, array{select: list<string>, file: list<string>}> */
    private array $fieldTypeCache = [];

    public function __construct(
        private readonly ContentBlockSeeder $seeder,
        private readonly FileRepository $fileRepository,
    ) {}

    /**
     * @param string $slug  the record type's PUBLIC slug, e.g. 'property'
     * @param string $table the backing table, e.g. tx_factorycore_record_property
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function serialize(string $slug, string $table, array $row): array
    {
        $result = ['_record_type' => $slug];
        foreach ($row as $key => $value) {
            $result[$key] = $value;
        }

        $fieldTypes = $this->resolveFieldTypes($slug);

        foreach ($fieldTypes['select'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '' && $row[$field] !== null) {
                $result[$field] = [$row[$field]];
            }
        }

        $uid = (int)($row['uid'] ?? 0);
        if ($uid > 0) {
            foreach ($fieldTypes['file'] as $field) {
                $files = $this->resolveFileReferences($table, $uid, $field);
                if ($files !== []) {
                    $result[$field] = $files;
                }
            }
        }

        return $result;
    }

    /**
     * Which fields of this record type are selects, and which are files —
     * read from its config.yaml.
     *
     * @return array{select: list<string>, file: list<string>}
     */
    private function resolveFieldTypes(string $slug): array
    {
        if (isset($this->fieldTypeCache[$slug])) {
            return $this->fieldTypeCache[$slug];
        }

        $select = [];
        $file = [];

        $directory = $this->seeder->recordDirectoryForSlug($slug);
        $config = $directory !== '' ? $this->seeder->readConfigYaml($directory, isRecord: true) : null;

        foreach ($config['fields'] ?? [] as $field) {
            if (!is_array($field) || !isset($field['identifier'], $field['type'])) {
                continue;
            }
            if (in_array($field['type'], self::SELECT_TYPES, true)) {
                $select[] = (string)$field['identifier'];
            } elseif (in_array($field['type'], self::FILE_TYPES, true)) {
                $file[] = (string)$field['identifier'];
            }
        }

        return $this->fieldTypeCache[$slug] = ['select' => $select, 'file' => $file];
    }

    /**
     * @return list<array{publicUrl: string, properties: array<string, mixed>}>
     */
    private function resolveFileReferences(string $table, int $uid, string $field): array
    {
        try {
            $references = $this->fileRepository->findByRelation($table, $field, $uid);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($references as $ref) {
            $file = $ref->getOriginalFile();
            $out[] = [
                'publicUrl' => $file->getPublicUrl() ?? '',
                'properties' => [
                    'alternative' => (string)($ref->getProperty('alternative') ?? ''),
                    'title'       => (string)($ref->getProperty('title') ?? ''),
                    'description' => (string)($ref->getProperty('description') ?? ''),
                    'width'       => (int)($file->getProperty('width') ?? 0),
                    'height'      => (int)($file->getProperty('height') ?? 0),
                    'mimeType'    => (string)($file->getProperty('mime_type') ?? ''),
                ],
            ];
        }

        return $out;
    }
}
