<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource;

class ViewDataset extends ViewRecord
{
    protected static string $resource = DatasetResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Dataset: ' . $this->getRecord()->name;
    }
}
