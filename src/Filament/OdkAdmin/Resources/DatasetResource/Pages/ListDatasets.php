<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages;

use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource;

class ListDatasets extends ListRecords
{
    protected static string $resource = DatasetResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'lookup_tables' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('lookup_table', '=', 1)),
            'survey_datasets' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('lookup_table', '=', 0)),

        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->createAnother(false),
        ];
    }
}
