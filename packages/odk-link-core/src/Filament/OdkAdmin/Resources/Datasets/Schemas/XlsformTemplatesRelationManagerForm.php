<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class XlsformTemplatesRelationManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
