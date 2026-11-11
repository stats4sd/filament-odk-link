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
        // only the lists of the imported versions: a team's copy of a list stays the snapshot it was.
        $choiceLists = $this->model->choiceListsForImport()->get();

        $newEntries = $rows->map(function (Collection $row) use ($choiceLists) {


            // there may be multiple choice lists with the same list name (one per module that includes it).
            $rowChoiceLists = $choiceLists
                ->filter(fn(ChoiceList $choiceList) => $choiceList->list_name === $row['list_name']);

            if ($rowChoiceLists->count() === 0) {
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

        // cascade_filter is mostly NULL, and a unique index never sees NULL = NULL, so an upsert on the unique key would
        // insert a new row on every import; the old rows would then be deleted (with the language strings of any team locale).
        // So match the existing global entries ourselves and update them by id.
        $existingIds = $this->existingGlobalEntryIds($choiceLists->pluck('id')->all());

        [$existing, $new] = $newEntries->partition(
            fn (array $entry) => $existingIds->has($this->entryKey($entry['choice_list_id'], $entry['name'], $entry['cascade_filter']))
        );

        if ($existing->isNotEmpty()) {
            ChoiceListEntry::upsert(
                $existing->map(fn (array $entry) => [
                    ...$entry,
                    'id' => $existingIds->get($this->entryKey($entry['choice_list_id'], $entry['name'], $entry['cascade_filter'])),
                ])->values()->toArray(),
                ['id'],
                ['properties', 'updated_during_import'],
            );
        }

        if ($new->isNotEmpty()) {
            ChoiceListEntry::upsert($new->values()->toArray(), ['name', 'choice_list_id', 'cascade_filter']);
        }
    }

    /**
     * Ids of the existing global entries (owner_id NULL) of the given lists, keyed by list, name and cascade_filter.
     * If a key exists more than once, the first id wins; the others stay un-updated and are removed by the cleanup after the import.
     *
     * @param  array<int, int>  $choiceListIds
     * @return Collection<string, int<0, max>>
     */
    private function existingGlobalEntryIds(array $choiceListIds): Collection
    {
        return ChoiceListEntry::withoutGlobalScopes()
            ->whereNull('owner_id')
            ->whereIn('choice_list_id', $choiceListIds)
            ->orderBy('id')
            ->get(['id', 'choice_list_id', 'name', 'cascade_filter'])
            ->reverse() // so that the lowest id is written last and wins
            ->mapWithKeys(fn (ChoiceListEntry $entry) => [
                $this->entryKey($entry->choice_list_id, $entry->name, $entry->cascade_filter) => $entry->id,
            ]);
    }

    // a NULL cascade_filter equals another NULL here (it is not the same as an empty string).
    private function entryKey(int|string $choiceListId, int|string $name, int|string|null $cascadeFilter): string
    {
        return json_encode([(int) $choiceListId, (string) $name, $cascadeFilter === null ? null : (string) $cascadeFilter]);
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
