<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages;

use Filament\Resources\Pages\CreateRecord;
use Stats4sd\FilamentOdkLink\Filament\Traits\RedirectsToListAfterSave;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;

class CreateDataset extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = DatasetResource::class;
}
