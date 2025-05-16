<?php

namespace Stats4sd\FilamentOdkLink\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TempCsvExport implements FromCollection, WithHeadings
{

    public array $headings;

    public function __construct(public Collection $rowData)
    {
        $this->headings = collect($rowData->first())->keys()->toArray();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): Collection
    {
        return $this->rowData;
    }


    public function headings(): array
    {
        return $this->headings;
    }
}
