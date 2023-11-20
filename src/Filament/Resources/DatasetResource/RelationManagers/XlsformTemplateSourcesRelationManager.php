<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\DatasetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;

class XlsformTemplateSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'xlsformTemplateSources';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Populated by...';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Xlsform Template')
                    ->url(fn (Model $record) => XlsformTemplateResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('active_xlsforms_count')->counts('xlsforms')
                    ->label('# of Active XLsforms'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
