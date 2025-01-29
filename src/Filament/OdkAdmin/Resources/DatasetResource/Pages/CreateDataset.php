<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

class CreateDataset extends CreateRecord
{
    protected static string $resource = DatasetResource::class;

}
