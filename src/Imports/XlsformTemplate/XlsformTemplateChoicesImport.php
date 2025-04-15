<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformTemplateChoicesImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow, WithUpserts, WithBatchInserts
{
    use RemembersRowNumber;

    public function __construct(public XlsformModuleVersion|XlsformTemplate $model, public Collection $translatableHeadings)
    {
        $model->load('choiceLists');
    }

    public function collection(Collection $rows): void
    {
        $choiceLists = $this->model->choiceLists()->get();

        $newEntries = $rows->map(function (Collection $row) use ($choiceLists) {


            // there may be multiple choice lists with the same list name (one per module that includes it).
            $rowChoiceLists = $choiceLists
                ->filter(fn(ChoiceList $choiceList) => $choiceList->list_name === $row['list_name']);

            if ($rowChoiceLists->count() === 0) {
                ray('no choice list found for ' . $row['list_name']);
                return null;
            }

            $data = [];

            $data['name'] = $row['name'];

            $data['properties'] = $row
                ->filter(fn($value, $key) => !$this->translatableHeadings->contains($key))
                ->filter(fn($value, $key) => $key !== 'name')
                ->filter(fn($value, $key) => $key !== 'list_name')
                ->filter(fn($value, $key) => $value !== null);

            // TODO: generalise after HOLPA ('filter' may not always be called 'filter')
            $data['cascade_filter'] = isset($row['filter']) ? $row['filter'] : null;
            $data['updated_during_import'] = true;

            return $rowChoiceLists->map(fn(ChoiceList $choiceList) => [
                'choice_list_id' => $choiceList->id,
                'name' => $data['name'],
                'properties' => $data['properties']->toJson(),
                'cascade_filter' => $data['cascade_filter'],
                'updated_during_import' => $data['updated_during_import'],
            ])->toArray();
        })
            ->filter()
            ->flatten(1);
        ChoiceListEntry::upsert($newEntries->toArray(), ['name', 'choice_list_id', 'cascade_filter']);

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
        return (!isset($row['name']) || $row['name'] === '')
            || (!isset($row['list_name']) || $row['list_name'] === '');
    }

    public function batchSize(): int
    {
        return 1000;
    }
}
