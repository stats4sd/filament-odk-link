<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Schemas;

use Filament\Schemas\Schema;
use Awcodes\Shout\Components\Shout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class XlsformModuleVersionForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->schema([
                Select::make('xlsform_module_id')
                    ->relationship('xlsformModule', 'label')
                    ->getOptionLabelFromRecordUsing(fn (XlsformModule $xlsformModule): string => "{$xlsformModule->xlsformTemplate->title} - $xlsformModule->name"),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Shout::make('info')
                    ->visible(fn (Get $get): bool => ! $get('is_default'))
                    ->content('For modules uploaded individually, please upload the Xlsfile with the module questions. Note that in this version of the platform, every question in this module must match in "name" and "type" to an existing question in the Xlsform template. Any questions not already in the template will be ignored.'),
                SpatieMediaLibraryFileUpload::make('xlsfile')
                    ->label('Upload Xlsfile with the module questions.')
                    ->collection('xlsform_file')
                    ->preserveFilenames()
                    ->downloadable()
                    ->visible(fn (Get $get): bool => ! $get('is_default'))
                    ->placeholder(__('File')),
            ])
            ->columns(1);
    }
}