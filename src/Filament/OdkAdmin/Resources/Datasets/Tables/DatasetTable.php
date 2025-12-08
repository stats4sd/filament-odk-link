<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Tables;

use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class DatasetTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('entity_model')
                    ->label('Database Table')
                    ->formatStateUsing(fn ($state) => Str::of(collect(Str::ucsplit($state))->last())->lower()->plural()),
                TextColumn::make('variables_count')
                    ->label('# of Variables defined')
                    ->counts('variables'),
            ])
            ->filters([
                //
            ])
            ->recordUrl(
                fn ($record) => route('filament.admin.resources.datasets.view', ['record' => $record])
            )
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
