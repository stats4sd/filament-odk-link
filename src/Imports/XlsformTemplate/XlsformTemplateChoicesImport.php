<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformTemplateChoicesImport implements ShouldQueue, SkipsEmptyRows, ToModel, WithChunkReading, WithHeadingRow, WithUpserts
{
    use RemembersRowNumber;

    public function __construct(public XlsformModuleVersion $xlsformModuleVersion, public Collection $translatableHeadings) {}

    public function model(array $row): ?ChoiceListEntry
    {
        $row = collect($row);

        // check the choice list entry is part of a list in the current module version:

        $choiceList = $this->xlsformModuleVersion
            ->choiceLists()
            ->where('list_name', $row['list_name'])
            ->first();

        if (! $choiceList) {
            return null;
        }

        $data = [];

        $data['name'] = $row['name'];
        $data['choice_list_id'] = $choiceList->id;

        $data['properties'] = $row
            ->filter(fn ($value, $key) => ! $this->translatableHeadings->contains($key))
            ->filter(fn ($value, $key) => $key !== 'name')
            ->filter(fn ($value, $key) => $key !== 'list_name')
            ->filter(fn ($value, $key) => $value !== null);

        // TODO: generalise after HOLPA ('filter' may not always be called 'filter')
        $data['cascade_filter'] = isset($row['filter']) ? $row['filter'] : null;
        $data['updated_during_import'] = true;

        return new ChoiceListEntry($data);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function uniqueBy(): array
    {
        return ['name', 'choice_list_id', 'cascade_filter'];
    }

    public function isEmptyWhen(array $row): bool
    {
        return (! isset($row['name']) || $row['name'] === '')
            || (! isset($row['list_name']) || $row['list_name'] === '');
    }
}
