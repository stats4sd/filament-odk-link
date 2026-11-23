<?php

namespace Stats4sd\FilamentOdkLink\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

// Generic Import to enable reading of XLSForm templates
class XlsImport implements SkipsUnknownSheets, ToCollection, WithHeadingRow, WithMultipleSheets
{
    use Importable;

    public function collection(Collection $collection): void {}

    public function sheets(): array
    {
        return [
            'survey' => $this,
            'choices' => $this,
            'settings' => $this,
            'entities' => $this,
        ];
    }

    // Only `survey` is mandatory in an XLSForm; callers check for the optional sheets with has().
    public function onUnknownSheet(int | string $sheetName): void {}
}
