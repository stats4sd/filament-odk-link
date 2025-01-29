<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

class ListDatasets extends ListRecords
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
