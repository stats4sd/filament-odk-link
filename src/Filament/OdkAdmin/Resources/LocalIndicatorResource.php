<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LocalIndicator;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource\Pages;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\LocalIndicatorResource\RelationManagers;

class LocalIndicatorResource extends Resource
{
    protected static ?string $model = LocalIndicator::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // change page title by changing label
    protected static ?string $label = 'Custom Module';

    // change navigation label
    protected static ?string $navigationLabel = 'Custom Module';

    protected static ?string $navigationGroup = 'Custom Module';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('team_id')
                    ->required()
                    ->default(auth()->user()->latestTeam->id),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                // TODO: remove domain_id when tidying table local_indicators
                Forms\Components\Hidden::make('domain_id')
                    ->default(1)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalIndicators::route('/'),
            'create' => Pages\CreateLocalIndicator::route('/create'),
            'edit' => Pages\EditLocalIndicator::route('/{record}/edit'),
        ];
    }
}
