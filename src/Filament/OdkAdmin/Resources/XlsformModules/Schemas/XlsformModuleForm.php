<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class XlsformModuleForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->schema([
                TextInput::make('label')
                    ->label('Enter the readable name of the module type')
                    ->hint('e.g. "Dietary Diversity"')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Enter the name of the group')
                    ->hint('e.g. "dietary_diversity"')
                    ->required()
                    ->maxLength(255),
            ])
            ->columns(1);
    }
}