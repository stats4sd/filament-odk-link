<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return TeamResource::getUrl('view', ['record' => $this->record]);
    }
}
