<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas;

use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

class DatasetForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->schema(fn (?Dataset $record) => static::getCreateFormFields($record));;
    }

    public static function getCreateFormFields(?Dataset $record = null): array
    {

        if (Filament::hasTenancy()) {
            $owner = Filament::getTenant();
            $ownerField = Hidden::make('owner_id')
                ->default($owner->getKey());
        } else {
            $ownerField = Select::make('owner_id')
                ->relationship('owner', 'name')
                ->label('Does a specific team own this dataset, or is it shared amongst all projects?');
        }

        return [
            $ownerField,

            Section::make('Information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->label('Enter the name of the dataset')
                        ->unique(modifyRuleUsing: fn(Unique $rule) => $rule->where('owner_id', Filament::getTenant()?->id ?? null))
                        ->validationMessages([
                            'unique' => 'Your project already has a dataset with this name. Please use a unique name to help clearly identify different datasets.'
                        ])
                        ->helperText('This should be the plural name for the people, objects or ideas represented by each entity in the dataset. For example: "farms", "villages", "enumerators", "treatments"'),

                    Textarea::make('description')
                        ->required()
                        ->label('Enter a brief description of the dataset')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Variables')
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
                        ->required(),

                ]),
            Repeater::make('variables')
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

}