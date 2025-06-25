<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource\Pages\EditChoiceLists;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource\Pages\ListChoiceLists;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ChoiceListResource\RelationManagers\ChoiceListEntriesRelationManager;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;

class ChoiceListResource extends Resource
{
    protected static ?string $model = ChoiceList::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Survey and Datasets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('xlsform_module_version_id')
                    ->relationship('xlsformModuleVersion', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_default', true)),
                Forms\Components\TextInput::make('list_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_localisable')
                    ->helperText('Should this list appear on the front-end for teams to edit?')
                    ->required(),
                Forms\Components\Repeater::make('properties.extra_properties')
                    ->columnSpanFull()
                    ->addActionLabel('Add extra property')
                    ->itemLabel(fn (array $state): string => $state['name'] ?? 'New Property')
                    ->label('Should users have space to add extra properties to these entries? For example, crops may require "expected yield".')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('The name of the property (the variable name for calculations)'),
                        Forms\Components\TextInput::make('label')
                            ->label('The label to show to users when asking for this property'),
                        Forms\Components\TextArea::make('hint')
                            ->label('Helper text to show to users when asking for this property'),
                    ]),
            ]);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('template.title')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('list_name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_localisable')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_dataset')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\IconColumn::make('can_be_hidden_from_context')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_custom_handling')
                    ->sortable()
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_localisable')
                    ->query(fn (Builder $query) => $query->where('is_localisable', true)),
                Tables\Filters\Filter::make('is_dataset')
                    ->query(fn (Builder $query) => $query->where('is_dataset', true)),
                Tables\Filters\Filter::make('can_be_hidden_from_context')
                    ->query(fn (Builder $query) => $query->where('can_be_hidden_from_context', true)),
                Tables\Filters\Filter::make('has_custom_handling')
                    ->query(fn (Builder $query) => $query->where('has_custom_handling', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ChoiceListEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChoiceLists::route('/'),
            'edit' => EditChoiceLists::route('/{record}'),
        ];
    }
}
