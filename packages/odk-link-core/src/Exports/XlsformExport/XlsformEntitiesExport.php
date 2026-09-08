<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class XlsformEntitiesExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public Xlsform $xlsform) {}

    public function collection(): Collection
    {
        return $this->xlsform->xlsformTemplate->templateEntityLists->map(fn ($entityList) => [
            'list_name' => $entityList->list_name,
            'entity_id' => $entityList->odk_entity_id_expression,
            'label' => $entityList->label_expression,
        ]);
    }

    public function headings(): array
    {
        return ['list_name', 'entity_id', 'label'];
    }

    public function title(): string
    {
        return 'entities';
    }
}
