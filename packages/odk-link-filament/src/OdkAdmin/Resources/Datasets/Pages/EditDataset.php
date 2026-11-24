<?php

namespace Stats4sd\OdkLink\Filament\OdkAdmin\Resources\Datasets\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\OdkLink\Core\Models\OdkLink\Dataset;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;

class EditDataset extends EditRecord
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
            Actions\DeleteAction::make(),
        ];
    }
}
