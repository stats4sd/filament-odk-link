<?php

namespace Stats4sd\FilamentOdkLink\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// Generic Import to enable reading of XLSForm templates
class XlsImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    use Importable;

    public function collection(Collection $collection) {}

    public function sheets(): array
    {
        return [
            'survey' => $this,
            'choices' => $this,
            'settings' => $this,
            'entities' => $this,
        ];
    }
}
