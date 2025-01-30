<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;

class XlsformTemplateModuleTypeImport implements ToModel, WithUpserts, ShouldQueue, WithChunkReading, SkipsEmptyRows
{
    public function model(array $row): XlsformModule
    {
        return new XlsformModule([
            'name' => $row['module'],
            'label' => $row['module'],
            'updated_during_import' => true,
        ]);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function uniqueBy(): array
    {
        return ['name'];
    }
}
