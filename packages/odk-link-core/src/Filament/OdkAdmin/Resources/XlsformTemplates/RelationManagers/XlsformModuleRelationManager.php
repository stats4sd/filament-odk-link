<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformModuleRelationManagerForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Tables\XlsformModuleRelationManagerTable;

class XlsformModuleRelationManager extends RelationManager
{
    protected static string $relationship = 'xlsformModules';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->schema(XlsformModuleRelationManagerForm::schema());
    }

    public function table(Table $table): Table
    {
        return XlsformModuleRelationManagerTable::configure($table);
    }
}
