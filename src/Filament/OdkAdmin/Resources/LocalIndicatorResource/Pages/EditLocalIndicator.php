<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocalIndicator extends EditRecord
{
    protected static string $resource = LocalIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }


    protected function getRedirectUrl(): string
    {
        return LocalIndicatorResource::getUrl('index');
    }
}
