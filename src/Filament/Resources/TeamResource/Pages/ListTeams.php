<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
