<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDatasets extends ListRecords
{
    protected static string $resource = DatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
