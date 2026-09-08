<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource;

class ListChoiceLists extends ListRecords
{
    protected static string $resource = ChoiceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
