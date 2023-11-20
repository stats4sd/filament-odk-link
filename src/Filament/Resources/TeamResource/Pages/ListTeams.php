<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource;

class ListTeams extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
