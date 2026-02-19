<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas;

use Filament\Forms\Components\TextInput;

class XlsformModuleRelationManagerForm
{
    public static function schema(): array
    {
        return [
            TextInput::make('label')
                ->required()
                ->maxLength(255),
        ];
    }
}