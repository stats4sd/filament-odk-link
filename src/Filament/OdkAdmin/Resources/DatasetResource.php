<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\CreateDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\EditDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\ListDatasets;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\ViewDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\RelationManagers\VariablesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\RelationManagers\XlsformTemplateSourcesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\RelationManagers\XlsformTemplatesRelationManager;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Services\HelperService;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static ?string $navigationGroup = 'ODK Forms and Datasets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(fn (?Dataset $record) => static::getCreateFormFields($record));
    }

    public static function getCreateFormFields(?Dataset $record = null): array
    {
        $models = HelperService::getModels()
            ->mapWithKeys(function ($model) {
                return [
                    $model => (new $model)->getTable(),
                ];
            });

        return [
            Forms\Components\TextInput::make('name')
                ->label('Name of the dataset'),
            Forms\Components\Select::make('entity_model')
                ->label('Which Database table does this dataset represent?')
                ->options($models),
            Forms\Components\Textarea::make('description')
                ->label('Enter a brief description of the dataset')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Hidden::make('primary_key')
                ->default('id'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('entity_model')
                    ->label('Database Table')
                    ->formatStateUsing(fn ($state) => Str::of(collect(Str::ucsplit($state))->last())->lower()->plural()),
                Tables\Columns\TextColumn::make('variables_count')
                    ->label('# of Variables defined')
                    ->counts('variables'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
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

    public static function getRelations(): array
    {
        return [
            XlsformTemplateSourcesRelationManager::class,
            XlsformTemplatesRelationManager::class,
            VariablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatasets::route('/'),
            'create' => CreateDataset::route('/create'),
            'edit' => EditDataset::route('/{record}/edit'),
            'view' => ViewDataset::route('/{record}'),
        ];
    }
}
