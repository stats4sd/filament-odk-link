<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class XlsformTemplateSourcesRelationManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->label('Template title')
                    ->required()
                    ->maxLength(255),
            ])
            ->columns(1);
    }
}