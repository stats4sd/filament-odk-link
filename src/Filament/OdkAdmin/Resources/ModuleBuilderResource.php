<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Filament\Forms;
use App\Models\Team;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Services\HelperService;
use Filament\Resources\Resource;
use Awcodes\Shout\Components\Shout;
use Filament\Forms\Components\Hidden;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\ModuleBuilderResource\Pages\ManageModuleBuilder;

class ModuleBuilderResource extends Resource
{
    protected static ?string $model = XlsformModuleVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Module Builder';

    protected static ?string $navigationLabel = 'Custom Modules';    

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                // Forms\Components\Select::make('xlsform_module_id')
                //     ->relationship('xlsformModule', 'label')
                //     ->getOptionLabelFromRecordUsing(fn (XlsformModule $xlsformModule): string => "{$xlsformModule->xlsformTemplate->title} - $xlsformModule->name")
                //     ->required(),

                // TODO: set owner_id to the selected team
                // owner_id should be the team of logged in user, admin does not belong to any team
                // hardcode it to the first team temporary
                Hidden::make('owner_id')
                    // ->default(HelperService::getCurrentOwner()->id),
                    ->default(Team::first()->id),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                // Shout::make('info')
                //     ->visible(fn (Forms\Get $get): bool => ! $get('is_default'))
                //     ->content('For modules uploaded individually, please upload the Xlsfile with the module questions. Note that in this version of the platform, every question in this module must match in "name" and "type" to an existing question in the Xlsform template. Any questions not already in the template will be ignored.'),
                // Forms\Components\SpatieMediaLibraryFileUpload::make('xlsfile')
                //     ->label('Upload Xlsfile with the module questions.')
                //     ->collection('xlsform_file')
                //     ->preserveFilenames()
                //     ->downloadable()
                //     ->visible(fn (Forms\Get $get): bool => ! $get('is_default'))
                //     ->placeholder(__('File')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // it should only show non default xlsform_module_version records
            ->modifyQueryUsing(function($query) {
                $query->where('is_default', false);
            })
            ->columns([
                // Tables\Columns\TextColumn::make('xlsformModule.form.title')
                //     ->numeric()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('xlsformModule.name')
                //     ->numeric()
                //     ->sortable(),
                // Tables\Columns\IconColumn::make('is_default')
                //     ->boolean()
                //     ->label('Default version of this module?'),
                Tables\Columns\TextColumn::make('owner.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('survey_rows_count')
                    ->label('# Survey rows')
                    ->counts('surveyRows'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageModuleBuilder::route('/'),
        ];
    }
}
