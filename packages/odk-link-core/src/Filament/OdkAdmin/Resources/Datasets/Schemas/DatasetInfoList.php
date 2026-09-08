<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DatasetInfoList
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Dataset Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('primary_key'),
                        TextEntry::make('description'),
                    ])
                    ->columns([
                        'lg' => 3,
                        'md' => 2,
                        'sm' => 1,
                    ]),
            ]);
    }
}
