<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class XlsformTemplateSettingsImport implements SkipsUnknownSheets, ToCollection, WithHeadingRow, WithMultipleSheets
{
    use Importable;

    public function collection(Collection $collection): void {}

    /** @return array<string, self> */
    public function sheets(): array
    {
        return ['settings' => $this];
    }

    public function onUnknownSheet($sheetName): void {}
}
