<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class XlsformModuleRelationManagerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('default_survey_rows_count')
                    ->label('# Survey Rows')
                    ->counts('defaultSurveyRows'),
                TextColumn::make('default_choice_lists_count')
                    ->label('# Choice Lists')
                    ->counts('defaultChoiceLists'),
                TextColumn::make('defaultLocales.language_label')
                    ->label('Available Locales'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
