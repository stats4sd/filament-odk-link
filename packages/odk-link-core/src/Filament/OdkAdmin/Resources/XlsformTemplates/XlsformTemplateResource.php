<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateInfoList;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Tables\XlsformTemplateTable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformTemplates;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use UnitEnum;

// Use this resource for an admin panel
// This resource is for templates that can be made available to all platform users

class XlsformTemplateResource extends Resource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static string | null | BackedEnum $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string | UnitEnum | null $navigationGroup = 'ODK Forms and Datasets';

    protected static WithXlsformTemplates $formOwner;

    public static function getFormOwner(): WithXlsformTemplates
    {
        return Platform::first();
    }

    public static function form(Schema $schema): Schema
    {
        return XlsformTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return XlsformTemplateTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return XlsformTemplateInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\XlsformModuleRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListXlsformTemplates::route('/'),
            'create' => Pages\CreateXlsformTemplate::route('/create'),
            'edit' => Pages\EditXlsformTemplate::route('/{record}/edit'),
            'view' => Pages\ViewXlsformTemplate::route('/{record}'),
        ];
    }
}
