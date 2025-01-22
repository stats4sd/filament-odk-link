<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\CustomXlsformTemplateResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\Resources\CustomXlsformTemplateResource;

class ListCustomXlsformTemplates extends ListRecords
{
    protected static string $resource = CustomXlsformTemplateResource::class;

    protected ?string $heading = 'Custom Xlsform Templates';

    public function getBreadcrumbs(): array
    {
        return [];
    }

}
