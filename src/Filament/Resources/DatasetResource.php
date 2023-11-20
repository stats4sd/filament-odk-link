<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources;

use Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource\Pages;
use Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource\RelationManagers;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(self::getCreateFormFields());
    }

    public static function getCreateFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('primary_key')
                ->hint('If this dataset is being populated by and ODK form, this field should be present in the form.')
                ->required(),
            Forms\Components\TextArea::make('description'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('primary_key'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
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
            ]);
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
                ])
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\XlsformTemplateSourcesRelationManager::class,
            RelationManagers\XlsformTemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatasets::route('/'),
            'create' => Pages\CreateDataset::route('/create'),
            'edit' => Pages\EditDataset::route('/{record}/edit'),
            'view' => Pages\ViewDataset::route('/{record}'),
        ];
    }
}
