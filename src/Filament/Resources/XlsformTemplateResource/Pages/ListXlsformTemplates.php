<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;

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
