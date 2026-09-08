<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\XlsformModuleResource;

class ManageXlsformModule extends ListRecords
{
    protected static string $resource = XlsformModuleResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\CreateAction::make(),
    //     ];
    // }
}
