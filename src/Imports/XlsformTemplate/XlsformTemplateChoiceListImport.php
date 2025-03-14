<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformTemplateChoiceListImport implements ShouldQueue, SkipsEmptyRows, ToModel, WithChunkReading, WithHeadingRow, WithMultipleSheets, WithUpserts
{
    use Importable;

    public function __construct(public XlsformModuleVersion $xlsformModuleVersion, public string $moduleColumn = 'module') {}

    public function sheets(): array
    {
        return [
            'survey' => $this,
        ];
    }

    public function model(array $row): ?ChoiceList
    {
        $row = collect($row);

        // only review the rows in the current module
        // skip entries not part of the current module
        $moduleName = $this->xlsformModuleVersion->xlsformModule->name ?? $this->xlsformModuleVersion->name;
        if ($row[$this->moduleColumn] !== $moduleName) {
            return null;
        }

        // only process select questions
        if (! Str::startsWith($row['type'], 'select_')) {
            return null;
        }

        if (isset($row['localisable'])) {
            $localisable = match ($row['localisable']) {
                'true', 'yes', 'TRUE', 'YES', 'Yes', 'True', '1', 1, true => true,
                default => false,
            };
        } else {
            $localisable = false;
        }

        // extract the list name from the type (e.g. "select_multiple crops" or "select_one farms")
        $listName = Str::afterLast($row['type'], ' ');

        return new ChoiceList([
            'xlsform_module_version_id' => $this->xlsformModuleVersion->id,
            'list_name' => $listName,
            'is_localisable' => $localisable,
        ]);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function isEmptyWhen(array $row): bool
    {
        return ! isset($row['type']) || $row['type'] === '';
    }

    public function uniqueBy(): array
    {
        return ['xlsform_module_version_id', 'list_name'];
    }
}
