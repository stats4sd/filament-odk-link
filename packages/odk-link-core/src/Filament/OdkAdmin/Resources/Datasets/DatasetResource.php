<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\CreateDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\EditDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\ListDatasets;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\ViewDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\RelationManagers\VariablesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\RelationManagers\XlsformTemplateSourcesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\RelationManagers\XlsformTemplatesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas\DatasetForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas\DatasetInfoList;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Tables\DatasetTable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use UnitEnum;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static string|UnitEnum|null $navigationGroup = 'ODK Forms and Datasets';

    public static function form(Schema $schema): Schema
    {
        return DatasetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatasetTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DatasetInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            XlsformTemplateSourcesRelationManager::class,
            XlsformTemplatesRelationManager::class,
            VariablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatasets::route('/'),
            'create' => CreateDataset::route('/create'),
            'edit' => EditDataset::route('/{record}/edit'),
            'view' => ViewDataset::route('/{record}'),
        ];
    }
}
