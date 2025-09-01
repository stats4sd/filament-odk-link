<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource\Pages;

use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLocalIndicator extends CreateRecord
{
    protected static string $resource = LocalIndicatorResource::class;


    protected function getRedirectUrl(): string
    {
        return LocalIndicatorResource::getUrl('index');
    }
}
