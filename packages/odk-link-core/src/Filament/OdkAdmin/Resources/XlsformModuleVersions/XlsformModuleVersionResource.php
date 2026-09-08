<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Pages\ManageXlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Schemas\XlsformModuleVersionForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Tables\XlsformModuleVersionTable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use UnitEnum;

class XlsformModuleVersionResource extends Resource
{
    protected static ?string $model = XlsformModuleVersion::class;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'ODK Forms and Datasets';

    public static function form(Schema $schema): Schema
    {
        return XlsformModuleVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return XlsformModuleVersionTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageXlsformModuleVersion::route('/'),
        ];
    }
}
