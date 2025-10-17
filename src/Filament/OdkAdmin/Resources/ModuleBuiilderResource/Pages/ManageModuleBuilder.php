<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ModuleBuilderResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ModuleBuilderResource;

class ManageModuleBuilder extends ManageRecords
{
    protected static string $resource = ModuleBuilderResource::class;

    protected static ?string $title = 'Custom Modules';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New custom module'),
        ];
    }
}
