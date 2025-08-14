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

            Forms\Components\Section::make('Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label('Enter the name of the dataset')
                        ->helperText('This should be the plural name for the people, objects or ideas represented by each entity in the dataset. For example: "farms", "villages", "enumerators", "treatments"'),
                    Forms\Components\Textarea::make('description')
                        ->required()
                        ->label('Enter a brief description of the dataset')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Variables')
                ->description(fn (): HtmlString => new HtmlString('Every dataset requires a primary key to uniquely identify each entity. By default, this platform will create a uuid value for every entity in the dataset. You may also wish to include a custom unique identifier, e.g. a code that is used throughout the project (or program).<br/><br/>
                  The dataset also requires a variable to act as a "label".This is the text that will be shown to enumerators if the dataset is used in an ODK form.'))
                ->schema([

                    TextInput::make('custom_key')
                        ->label('Please enter the variable name for the primary key (unique identifier)')
                        ->helperText('This variable must be unique across the dataset')
                        ->notIn(['uuid'])
                        ->validationMessages([
                            'not_in' => 'uuid is a restricted variable name. Please use a different name.',
                        ])
                        ->helperText('E.g. "farm_code", "project_number", "id",')
                        ->required(),

                    TextInput::make('label')
                        ->label('Enter the variable name to be used as the main "label" when displaying entities in the dataset')
                        ->helperText('E.g. "farm_name", "treatment_name" etc. This is the variable that will be shown to enumerators in an ODK form, or as labels for an analysis output.')
                        ->notIn(['uuid'])
                        ->validationMessages([
                            'not_in' => 'uuid is a restricted variable name. Please use a different name.',
                        ])
                        ->required()
                        ->nullable(),

                ]),
            Forms\Components\Repeater::make('variables')
                ->label('Optionally, add extra variables that you know the dataset will contain. This is optional, and additional variables will be added automatically when you import data from csv or ODK form submissions.')
                ->relationship('variables')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->helperText('How does this variable appear in code or in ODK forms?')
                        ->notIn(['uuid'])
                        ->validationMessages([
                            'not_in' => 'uuid is a restricted variable name. Please use a different name.',
                        ])
                        ->live(),
                    TextInput::make('label')
                        ->label('Label')
                        ->helperText('A readable label for the variable'),
                ])
                ->itemLabel(fn (array $state) => $state['name'] ?? '~new variable~')
                ->default([])
                ->addActionLabel('Add variable'),
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
