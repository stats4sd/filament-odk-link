<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource;

class EditDataset extends EditRecord
{
    protected static string $resource = DatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
