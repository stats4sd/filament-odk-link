<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListXlsformTemplates extends ListRecords
{
    protected static string $resource = XlsformTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
