<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas\XlsformTemplateSourcesRelationManagerForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Tables\XlsformTemplateSourcesRelationManagerTable;

class XlsformTemplateSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'xlsformTemplateSources';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Populated by...';
    }

    public function form(Schema $schema): Schema
    {
        return XlsformTemplateSourcesRelationManagerForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return XlsformTemplateSourcesRelationManagerTable::configure($table);
    }
}
