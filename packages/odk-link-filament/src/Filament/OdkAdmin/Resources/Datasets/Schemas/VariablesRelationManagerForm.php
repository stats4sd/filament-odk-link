<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VariablesRelationManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Enter the variable name as it should appear in a dataset (column header)')
                    ->helperText('Ideally, this should be in snake_case (e.g. "productive_activities")'),
                TextInput::make('label')
                    ->label('The label for the variable')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description'),
            ])
            ->columns(1);
    }
}
