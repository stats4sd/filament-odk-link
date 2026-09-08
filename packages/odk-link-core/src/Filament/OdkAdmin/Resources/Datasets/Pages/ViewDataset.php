<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages;

use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use phpDocumentor\Reflection\Types\ClassString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;

class ViewDataset extends ViewRecord
{
    protected static string $resource = DatasetResource::class;

    /**
     * @phpstan-return Dataset
     */
    public function getRecord(): Model | Dataset
    {
        /** @var Dataset $record */
        $record = parent::getRecord();

        return $record;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Dataset: ' . $this->getRecord()->name;
    }
}
