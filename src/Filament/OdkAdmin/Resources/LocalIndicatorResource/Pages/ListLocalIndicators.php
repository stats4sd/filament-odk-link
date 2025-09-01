<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocalIndicators extends ListRecords
{
    protected static string $resource = LocalIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
