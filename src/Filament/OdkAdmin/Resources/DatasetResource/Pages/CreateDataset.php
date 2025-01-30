<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\Traits\RedirectsToListAfterSave;

class CreateDataset extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = DatasetResource::class;
}
