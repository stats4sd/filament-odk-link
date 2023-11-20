<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;

class ViewXlsformTemplate extends ViewRecord
{
    protected static string $resource = XlsformTemplateResource::class;

    public function getTitle(): string
    {
        return self::getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
