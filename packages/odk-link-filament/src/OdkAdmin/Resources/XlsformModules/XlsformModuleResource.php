<?php

namespace Stats4sd\OdkLink\Filament\OdkAdmin\Resources\XlsformModules;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModule;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\XlsformModules\Pages\ManageXlsformModule;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\XlsformModules\Schemas\XlsformModuleForm;
use Stats4sd\OdkLink\Filament\OdkAdmin\Resources\XlsformModules\Tables\XlsformModuleTable;
use UnitEnum;

class XlsformModuleResource extends Resource
{
    protected static ?string $model = XlsformModule::class;

    protected static string | null | BackedEnum $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | UnitEnum | null $navigationGroup = 'ODK Forms and Datasets';

    public static function form(Schema $schema): Schema
    {
        return XlsformModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return XlsformModuleTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageXlsformModule::route('/'),
        ];
    }
}
