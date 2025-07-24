<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\CreateDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\EditDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\ListDatasets;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\ViewDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\RelationManagers\VariablesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\RelationManagers\XlsformTemplateSourcesRelationManager;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\RelationManagers\XlsformTemplatesRelationManager;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

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

        if (Filament::hasTenancy()) {
            $owner = Filament::getTenant();
            $ownerField = Forms\Components\Hidden::make('owner_id')
                ->default($owner->getKey());
        } else {
            $ownerField = Forms\Components\Select::make('owner_id')
                ->relationship('owner')
                ->label('Does a specific team own this dataset, or is it shared amongst all APNI projects?');
        }

        return [
            $ownerField,

            Forms\Components\Section::make('')
            Forms\Components\TextInput::make('name')
                ->label('Enter the name of the dataset')
                ->helperText('This should be the plural name for the people, objects or ideas represented by each entity in the dataset. For example: "farms", "villages", "enumerators", "treatments"'),

            Forms\Components\Textarea::make('description')
                ->label('Enter a brief description of the dataset')
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Section::make('Variables')
                ->description(fn(): HtmlString => new HtmlString('Every dataset requires a primary key to uniquely identify each entity. By default, this platform will create a uuid value for every entity in the dataset. You may also wish to include a custom unique identifier, e.g. a code that is used throughout the project (or program).<br/><br/>
                  The dataset also requires a variable to act as a "label".This is the text that will be shown to enumerators if the dataset is used in an ODK form.'))
                ->schema([

                    Forms\Components\Select::make('custom_key')
                        ->options([
                            1 => 'Yes',
                            0 => 'No',
                        ])
                        ->label('Does this dataset have a custom unique identifier defined by the project?')
                        ->helperText('For example, you may have assigned unique codes for each farm.')
                        ->live()
                        ->afterStateUpdated(fn ($state, $set) => $state === '0' ? $set('primary_key', 'uuid') : null),

                    TextInput::make('primary_key')
                        ->label('Enter the name of the variable that includes your unique identifier')
                        ->visible(fn (Forms\Get $get) => $get('custom_key') === '1'),

                    TextInput::make('label')
                        ->label('Enter the variable name that should be used as the main "label" when displaying entities in the dataset')
                        ->nullable(),

                ]),
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
