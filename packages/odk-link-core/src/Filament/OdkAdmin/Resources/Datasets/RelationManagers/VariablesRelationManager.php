<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas\VariablesRelationManagerForm;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Tables\VariablesRelationManagerTable;

class VariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'variables';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return VariablesRelationManagerForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return VariablesRelationManagerTable::configure($table);
    }

}
