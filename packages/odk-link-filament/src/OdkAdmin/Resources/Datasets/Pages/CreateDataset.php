<?php

namespace Stats4sd\OdkLink\Filament\OdkAdmin\Resources\Datasets\Pages;

use Filament\Resources\Pages\CreateRecord;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;
use Stats4sd\OdkLink\Filament\Traits\RedirectsToListAfterSave;

class CreateDataset extends CreateRecord
{
    use RedirectsToListAfterSave;

    protected static string $resource = DatasetResource::class;
}
