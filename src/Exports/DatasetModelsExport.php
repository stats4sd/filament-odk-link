<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;

class DatasetModelsExport implements FromCollection, WithHeadings, WithStrictNullComparison
{

    protected array $columns = [];

    // by default, we use the dataset variables as the columns. If you want to specify columns, you can pass them in as an array.
    public function __construct(
        public Dataset      $dataset,
        public WithXlsforms $owner)
    {
        $this->columns = $this->dataset->entity_model::getColumnsForOdk();
    }

    public function collection(): Collection
    {

        // get all entries from the dataset's entity_model that belong to the given owner or that do not belong to anybody (owner_id === null means the entry is universal).
        $query = $this->dataset->entity_model::query();


        // if the dataset has owner-specific entries, filter by the owner. Otherwise, return all entries.
        if ($this->dataset->isOwnerSpecific()) {
            $query = $query->where(function (Builder $query) {
                $query->where('owner_id', $this->owner->id)
                    ->where('owner_type', get_class($this->owner));
            })
                ->orWhere('owner_id', null);
        }

        return $query
            ->get()
            ->map(function ($entry) {
                return $entry->getCsvContentsForOdk();
            });
    }

    public function headings(): array
    {
        return $this->columns;
    }
}
