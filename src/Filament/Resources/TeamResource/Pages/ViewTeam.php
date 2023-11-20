<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTeam extends ViewRecord
{
    protected static string $resource = TeamResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
