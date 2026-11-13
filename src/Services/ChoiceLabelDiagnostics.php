<?php

namespace Stats4sd\FilamentOdkLink\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Explains ODK's "<label> node for itemset doesn't exist" validation error.
 * ODK only says which choice list it was; this reads the generated XLSForm to say which entries have no label.
 */
class ChoiceLabelDiagnostics
{
    private const MAX_NAMES_SHOWN = 10;

    /** The choice list named in ODK Validate's missing-label error, if the message is that error. */
    public static function listNameFromValidationError(string $message): ?string
    {
        return preg_match('/instance\(([^)]+)\)\/root\/item\/label/', $message, $matches) ? $matches[1] : null;
    }

    /**
     * Entries of the given list whose label is empty in every language column of the choices sheet.
     *
     * @return array{languages: list<string>, entries: list<string>}
     */
    public function unlabelledEntries(string $xlsxPath, string $listName): array
    {
        $reader = IOFactory::createReaderForFile($xlsxPath);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly('choices');

        $rows = $reader->load($xlsxPath)->getSheetByName('choices')?->toArray(null, true, false, false) ?? [];

        $headers = array_map(fn ($header) => (string) $header, array_shift($rows) ?? []);

        $listColumn = array_search('list_name', $headers, true);
        $nameColumn = array_search('name', $headers, true);
        $labelColumns = array_filter($headers, fn (string $header) => $header === 'label' || str_starts_with($header, 'label::'));

        if ($listColumn === false || $nameColumn === false || $labelColumns === []) {
            return ['languages' => [], 'entries' => []];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (($row[$listColumn] ?? null) !== $listName) {
                continue;
            }

            $hasLabel = collect(array_keys($labelColumns))
                ->contains(fn (int $column) => trim((string) ($row[$column] ?? '')) !== '');

            if (! $hasLabel) {
                $entries[] = (string) $row[$nameColumn];
            }
        }

        return [
            'languages' => array_map(fn (string $header) => str_replace('label::', '', $header), array_values($labelColumns)),
            'entries' => $entries,
        ];
    }

    /** A readable replacement for ODK's message, or null when the file shows no unlabelled entries to point at. */
    public function explain(string $odkMessage, string $xlsxPath): ?string
    {
        $listName = self::listNameFromValidationError($odkMessage);

        if ($listName === null) {
            return null;
        }

        ['languages' => $languages, 'entries' => $entries] = $this->unlabelledEntries($xlsxPath, $listName);

        if ($entries === []) {
            return null;
        }

        $shown = array_slice($entries, 0, self::MAX_NAMES_SHOWN);
        $more = count($entries) - count($shown);

        return sprintf(
            "The choice list '%s' has %d entries with no label in any of this form's languages (%s): %s%s. "
            . 'ODK rejects a choice list used by a question when its entries have no label. '
            . "Check that the list has translations for each of the team's languages. "
            . 'ODK said: %s',
            e($listName),
            count($entries),
            e(implode(', ', $languages) ?: 'none'),
            e(implode(', ', $shown)),
            $more > 0 ? " and $more more" : '',
            e($odkMessage),
        );
    }
}
