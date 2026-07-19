<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class XlsformModuleForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->schema([
                TextInput::make('label')
                    ->label('Enter the readable name of the module')
                    ->hint('e.g. "Dietary Diversity"')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Enter the name of the module')
                    ->hint('e.g. "dietary_diversity"')
                    ->required()
                    ->maxLength(255),
                Checkbox::make('can_be_replaced')
                ->label('Can this module be fully replaced by teams with a local customised version?'),
                Checkbox::make('can_be_extended')
                ->label('Can teams add additional questions to this module during localisation?'),
            ])
            ->columns(1);
    }
}