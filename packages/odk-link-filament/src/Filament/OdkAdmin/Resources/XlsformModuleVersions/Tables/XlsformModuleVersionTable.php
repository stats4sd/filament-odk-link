<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class XlsformModuleVersionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('xlsformModule.form.title')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('xlsformModule.name')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default version of this module?'),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('survey_rows_count')
                    ->label('# Survey rows')
                    ->counts('surveyRows'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
