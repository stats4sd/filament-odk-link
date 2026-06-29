<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Callout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Stats4sd\FilamentOdkLink\Forms\Components\HtmlBlock;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\IsXlsformTemplate;

class XlsformTemplateForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->schema([
                Tabs::make('Label')
                    ->tabs([
                        Tabs\Tab::make('Xlsform File')
                            ->schema(static::getCreateFields()),
                        Tabs\Tab::make('Attached Media Files')
                            ->schema(static::getStaticMediaFields()),
                        Tabs\Tab::make('Attached Datasets')
                            ->schema(static::getDatasetMediaFields()),
                    ]),
            ])
            ->columns(1);
    }

    public static function getCreateFields(): array
    {
        return [
            TextInput::make('title')
                ->autofocus()
                ->required()
                ->maxLength(64)
                ->placeholder(__('Title'))
                ->disabledOn(['edit'])
                ->default(function () {
                    // get the title from url if it exists in the query string
                    return request()->query('title');
                }),

            Callout::make()
                ->info()
                ->description(new HtmlString('Please upload a valid Xlsform file. If you have not yet validated your form, we recommend you do so here: <a target="_blank" href="https://getodk.org/xlsform/" class="underline text-primary-800">https://getodk.org/xlsform/</a>.<br/><br/>Note that while in regular ODK the "settings" worksheet is optional, this system requires it, so please make sure you have a settings worksheet with at least the form_id and form_title variables added. See the <a href="https://docs.getodk.org/xlsform/#the-settings-sheet">ODK documentation here</a> for more information.')),
            FileUpload::make('newXlsfile')
                ->storeFiles(false)
                ->label('Upload your Xlsform File in Excel format')
                ->preserveFilenames()
                ->downloadable()
                ->autofocus()
                ->hiddenOn(['edit'])
                ->disabledOn(['edit'])
                ->placeholder(__('File')),

            // Custom validation display - because Filament fields only show 1 validation error message. This component shows all errors thrown at once.
            // TODO: refactor in Filament 4, when we can set fields to show multiple errors if needed.
            Callout::make()
                ->danger()
                ->visible(fn($livewire): bool => $livewire->getErrorBag()->any())
                ->description(fn($livewire): HtmlString => new HtmlString(collect($livewire->getErrorBag()->all())->join('<br/><br/>'))),

        ];
    }

    public static function getStaticMediaFields(): array
    {

        return [
            Repeater::make('requiredFixedMedia')
                ->label(function (?XlsformTemplate $record) {

                    $label = "<h4 class='font-bold text-xl'>Add Media Files</h4>";

                    if ($record?->requiredFixedMedia()->count() > 0) {
                        $label .= '<p>The Form requires the following media items. Please upload each one here.</p>';
                    } else {
                        $label .= '<p>This form does not require any media files. You may skip this step</p>';
                    }

                    return new HtmlString($label);
                })
                ->relationship()
                ->addable(false)
                ->deletable(false)
                ->schema([

                    HtmlBlock::make('name')
                        ->content(
                            fn(?RequiredMedia $record): HtmlString => new HtmlString("<b>Filename:</b> $record?->name")
                        ),

                    SpatieMediaLibraryFileUpload::make('file')
                        ->preserveFilenames()
                        ->downloadable()
                        ->required(),
                ]),
        ];
    }

    public static function getDatasetMediaFields(): array
    {
        return [
            Repeater::make('requiredDataMedia')
                ->label(function (?XlsformTemplate $record) {
                    $label = "<h4 class='font-bold text-xl'>Link Required Datasets</h4>";

                    if ($record?->requiredDataMedia()->count() > 0) {
                        $label .= '<p>The Form requires the following datasets. Please either upload static csv files to be used, or mark the item(s) as localisable for each team. </p>';
                    } else {
                        $label .= '<p>This form does not require any datasets. You may skip this step</p>';
                    }

                    return new HtmlString($label);
                })
                ->relationship()
                ->addable(false)
                ->deletable(false)
                ->schema(function (?IsXlsformTemplate $record) {
                    $xlsformTemplate = $record;

                    return
                        [
                            HtmlBlock::make('name')
                                ->content(
                                    fn(?RequiredMedia $record): HtmlString => new HtmlString("<b>Filename:</b> $record?->name")
                                ),
                            Toggle::make('is_static')
                                ->label('Is this a static media file?')
                                ->default(false)
                                ->live(),

                            // for static media
                            Group::make()
                                ->visible(fn(Get $get): bool => $get('is_static'))
                                ->schema([
                                    SpatieMediaLibraryFileUpload::make('file')
                                        ->preserveFilenames()
                                        ->downloadable()
                                        ->required(),
                                ]),

                            // for non-static media (linked to datasets)
                            Group::make()
                                ->visible(fn(Get $get, ?RequiredMedia $record): bool => $record?->links_to_dataset && !$get('is_static'))
                                ->schema([
                                    Callout::make()
                                        ->info()
                                        ->description(fn(?RequiredMedia $record): HtmlString => new HtmlString('Select the dataset that contains the list of entries for this linked dataset. When the form is published, the full content of the chosen dataset will be written to a csv file and uploaded to ODK as a file attachment.'))
                                        ->visible(fn(Get $get): bool => !$get('is_static')),
                                    Select::make('dataset_id')
                                        ->relationship('dataset', 'name', modifyQueryUsing: fn(Builder $query) => $query->whereHas('owner', fn(Builder $query) => $query->whereKey($xlsformTemplate->owner_id)))
                                        ->preload()
                                        ->searchable()
                                        ->visible(fn(Get $get): bool => !$get('is_static')),
                                ]),

                            Group::make()
                                ->visible(fn(Get $get, ?RequiredMedia $record): bool => !$record?->links_to_dataset && !$get('is_static'))
                                ->schema([
                                    Callout::make()
                                        ->info()
                                        ->description(fn(?RequiredMedia $record): string => "This csv file will be automatically generated from the linked choice list " . $record->choiceList?->list_name . ". This list is editable by individual teams using versions of this Form Template."),
                                ]),

                        ];
                }),
        ];
    }


}