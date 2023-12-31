<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\ImageEntry;
use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\Pages;

class TeamResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // get custom team model
    public static function getModel(): string
    {
        return config('filament-odk-link.models.team_model');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Team Details')
                    ->schema([

                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\FileUpload::make('avatar'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('xlsform_count')
                    ->label('Xlsforms')
                    ->counts('xlsforms'),
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
                Section::make('Team Details')
                    ->columns(6)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->columnSpan(2),
                        TextEntry::make('description')
                            ->getStateUsing(fn ($record) => new HtmlString(preg_replace('/\n/', '<br/>', $record->description)))
                            ->columnSpan(4),
                        ViewEntry::make('qr_code')
                            ->view('filament-odk-link::filament.infolists.components.team-qr-code'),

                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TeamResource\RelationManagers\XlsformsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
            'view' => Pages\ViewTeam::route('/{record}'),
        ];
    }
}
