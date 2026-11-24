<?php

namespace Stats4sd\OdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\XlsformModuleVersionResource;

class ManageXlsformModuleVersion extends ManageRecords
{
    protected static string $resource = XlsformModuleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
