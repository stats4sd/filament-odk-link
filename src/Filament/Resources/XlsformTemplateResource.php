<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources;

use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;
use Stats4sd\FilamentOdkLink\Forms\Components\HtmlBlock;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

class XlsformTemplateResource extends Resource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
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
            ]);
    }

    public static function getCreateFields(): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->autofocus()
                ->required()
                ->maxLength(64)
                ->placeholder(__('Title'))
                ->default(function () {
                    // get the title from url if it exists in the query string
                    return request()?->query('title');
                }),
            Forms\Components\SpatieMediaLibraryFileUpload::make('xlsfile')
                ->collection('xlsform_file')
                ->preserveFilenames()
                ->downloadable()
                ->autofocus()
                ->required()
                ->placeholder(__('File')),
        ];
    }

    public static function getStaticMediaFields(): array
    {

        return [
            Forms\Components\Repeater::make('requiredFixedMedia')
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

                    Forms\Components\SpatieMediaLibraryFileUpload::make('file')
                        ->preserveFilenames()
                        ->downloadable()
                        ->required(),
                ]),
        ];
    }

    public static function getDatasetMediaFields(): array
    {
        return [
            Forms\Components\Repeater::make('requiredDataMedia')
                ->label(function (?XlsformTemplate $record) {
                    $label = "<h4 class='font-bold text-xl'>Link Required Datasets</h4>";

                    if ($record?->requiredDataMedia()->count() > 0) {
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
                    Forms\Components\Toggle::make('is_static')
                        ->label('Is this a static media file?')
                        ->default(false)
                        ->live(),

                    // for static media
                    Forms\Components\SpatieMediaLibraryFileUpload::make('file')
                        ->preserveFilenames()
                        ->downloadable()
                        ->required()
                        ->visible(fn(Get $get): bool => $get('is_static')),

                    // for non-static media (linked to datasets)
                    Forms\Components\Select::make('dataset_id')
                        ->label('Select a dataset')
                        ->relationship('dataset', 'name')
                        ->visible(fn(Get $get): bool => !$get('is_static')),

                ]),
        ];
    }

    public static function getXlsformSectionFields(): array
    {
        return [

            HtmlBlock::make('title')
                ->content(fn(?XlsformTemplate $record): HtmlString => new HtmlString("
                <h3 class='text-xl'>$record->title - Form Structure</h3>
                <p>On this page, you can review the structure of the data that will come from form submissions. The 'main survey' section includes all the variables that are not in repeat groups. You should choose or create a dataset for the form submissions to populate.</p>

            ")),
            Forms\Components\Fieldset::make('rootSection')
                ->label('Main Survey')
                ->relationship('rootSection')
                ->schema([
                    Forms\Components\ViewField::make('schema')
                        ->view('filament-odk-link::filament.forms.components.xlsform-section-schema-modal-link')
                        ->registerActions([
                            Action::make('viewSchema')
                                ->label('View variable list')
                                ->icon('heroicon-o-eye')
                                ->form(function (?XlsformTemplateSection $record) {
                                    return [
                                        TableRepeater::make('schema')
                                            ->label('List of variables in the main survey')
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->addable(false)
                                            ->schema([
                                                Forms\Components\TextInput::make('name')->disabled()->hiddenLabel(),
                                                Forms\Components\TextInput::make('type')->disabled()->hiddenLabel(),
                                            ]),
                                    ];
                                })
                                ->fillForm(fn(?XlsformTemplateSection $record): array => [
                                    'schema' => $record->schema,
                                ])
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close'),
                        ])
                        ->visible(fn(?XlsformTemplateSection $record): bool => $record?->schema->count() >= 5),

                    Forms\Components\Select::make('dataset_id')
                        ->relationship('dataset', 'name')
                        ->label('Select which dataset the submissions should be linked to')
                        ->createOptionForm(DatasetResource::getCreateFormFields())
                        ->createOptionModalHeading('Create New Dataset'),
                ]),

            Forms\Components\Repeater::make('repeatingSections')
                ->columns([
                    'md' => 2,
                    'sm' => 1,
                ])
                ->label(function (?XlsformTemplate $record) {
                    $label = "<h3 class='text-lg'>Repeat Groups</h3><p class='font-light'>This form also has {$record?->repeatingSections()->count()} repeat groups within the form. The data from these repeat groups should be linked to a different dataset. For example, in a household survey, you may link the 'main' survey submission data to a dataset called 'Households', and a repeat group asking information from each member to a dataset called 'Household Members'.</p><br/>";

                    return new HtmlString($label);
                })
                ->itemLabel(fn(array $state): ?string => $state['structure_item'] ?? null)
                ->visible(fn(?XlsformTemplate $record): bool => $record->repeatingSections()->count() > 0)
                ->relationship()
                ->addable(false)
                ->deletable(false)
                ->schema([
                    Forms\Components\ViewField::make('schema')
                        ->view('filament-odk-link::filament.forms.components.xlsform-section-schema-modal-link')
                        ->registerActions([
                            Action::make('viewSchema')
                                ->label('View variable list')
                                ->icon('heroicon-o-eye')
                                ->form(function (?XlsformTemplateSection $record) {
                                    return [
                                        TableRepeater::make('schema')
                                            ->label(fn(?XlsformTemplateSection $record) => "List of variables in the $record->structure_item repeat group")
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->addable(false)
                                            ->schema([
                                                Forms\Components\TextInput::make('name')->disabled()->hiddenLabel(),
                                                Forms\Components\TextInput::make('type')->disabled()->hiddenLabel(),
                                            ]),
                                    ];
                                })
                                ->fillForm(fn(?XlsformTemplateSection $record): array => [
                                    'schema' => $record->schema,
                                ])
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close'),
                        ]),
                    Forms\Components\Select::make('dataset_id')
                        ->relationship('dataset', 'name')
                        ->label('Select which dataset the submissions should be linked to')
                        ->createOptionForm(DatasetResource::getCreateFormFields())
                        ->createOptionModalHeading('Create New Dataset'),
                ])];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dataset.name'),
                Tables\Columns\ViewColumn::make('required_fixed_media_count')
                    ->label('Fixed Media')
                    ->view('filament-odk-link::filament.tables.columns.required-fixed-media-count'),
                Tables\Columns\ViewColumn::make('required_data_media_count')
                    ->label('Datasets')
                    ->view('filament-odk-link::filament.tables.columns.required-data-media-count'),
                Tables\Columns\CheckboxColumn::make('available')
                    ->label('Available for use?')
                    ->sortable(),

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

    public static function infoList(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Xls File')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('xlsfile_name')
                            ->url(fn(?XlsformTemplate $record): string => $record?->getFirstMediaUrl('xlsform_file')),
                        IconEntry::make('available')
                            ->label('Available to Platform users?')
                            ->icon(fn(bool $state): string => match ($state) {
                                false => 'heroicon-o-no-symbol',
                                true => 'heroicon-o-check-circle',
                            }),
                    ])
                    ->columns([
                        'lg' => 3,
                        'md' => 2,
                        'sm' => 1,
                    ]),
                RepeatableEntry::make('requiredFixedMedia')
                    ->schema([
                        TextEntry::make('name')
                            ->url(fn(?RequiredMedia $record): string => $record->getFirstMediaUrl()),
                        TextEntry::make('type'),
                        IconEntry::make('status')
                            ->icon(fn(int $state): string => match ($state) {
                                1 => 'heroicon-o-check-circle',
                                0 => 'heroicon-o-x-circle',
                            })
                            ->color(fn(int $state): string => match ($state) {
                                1 => 'success',
                                0 => 'gray',
                            }),
                    ])
                    ->columns([
                        'lg' => 3,
                        'md' => 2,
                        'sm' => 1,
                    ]),

                RepeatableEntry::make('requiredDataMedia')
                    ->schema([
                        TextEntry::make('name')
                            ->url(fn(?RequiredMedia $record): string => $record->getFirstMediaUrl()),
                        TextEntry::make('full_type'),
                        IconEntry::make('status')
                            ->icon(fn(int $state): string => match ($state) {
                                1 => 'heroicon-o-check-circle',
                                0 => 'heroicon-o-x-circle',
                            })
                            ->color(fn(int $state): string => match ($state) {
                                1 => 'success',
                                0 => 'gray',
                            }),
                    ])->columns([
                        'lg' => 3,
                        'md' => 2,
                        'sm' => 1,
                    ]),

                Section::make('Main Survey')
                    ->schema([
                        RepeatableEntry::make('schema')
                            ->label('List of variables in the main survey')
                            ->schema([
                                TextEntry::make('name')->hiddenLabel(),
                                TextEntry::make('type')->hiddenLabel(),
                            ])
                            ->visible(fn(?XlsformTemplate $record): bool => $record->rootSection->schema->count() < 5),

                        ViewEntry::make('schema')
                            ->view('filament-odk-link::filament.infolists.components.xlsform-section-schema-modal-link')
                            ->registerActions([
                                \Filament\Infolists\Components\Actions\Action::make('viewSchema')
                                    ->label('View variable list')
                                    ->icon('heroicon-o-eye')
                                    ->form(function (?XlsformTemplate $record) {
                                        return [
                                            TableRepeater::make('schema')
                                                ->label('List of variables in the main survey')
                                                ->deletable(false)
                                                ->reorderable(false)
                                                ->addable(false)
                                                ->schema([
                                                    Forms\Components\TextInput::make('name')->disabled()->hiddenLabel(),
                                                    Forms\Components\TextInput::make('type')->disabled()->hiddenLabel(),
                                                ]),
                                        ];
                                    })
                                    ->fillForm(function (?XlsformTemplate $record): array {
                                        return [
                                            'schema' => $record?->rootSection->schema,
                                        ];
                                    })
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Close'),
                            ])
                            ->visible(fn(?XlsformTemplate $record): bool => $record->rootSection->schema->count() >= 5),

                        TextEntry::make('rootSection.dataset.name')->label('Submission data is added to:')
                            ->url(fn(?XlsformTemplate $record): string => DatasetResource::getUrl('view', ['record' => $record->rootSection->dataset_id])),

                    ]),

                Section::make('Repeat Groups')
                    ->schema([
                        RepeatableEntry::make('repeatingSections')
                            ->columns([
                                'lg' => 3,
                                'md' => 2,
                                'sm' => 1,
                            ])
                            ->hiddenLabel()
                            ->schema(function ($state) {
                                return [
                                    TextEntry::make('structure_item')->label('Repeat Name'),

                                    ViewEntry::make('schema')
                                        ->view('filament-odk-link::filament.forms.components.xlsform-section-schema-modal-link')
                                        ->registerActions([
                                            \Filament\Infolists\Components\Actions\Action::make('viewSchema')
                                                ->label('View variable list')
                                                ->icon('heroicon-o-eye')
                                                ->form(function (?XlsformTemplateSection $record) {
                                                    return [
                                                        TableRepeater::make('schema')
                                                            ->label('List of variables in the repeat group')
                                                            ->deletable(false)
                                                            ->reorderable(false)
                                                            ->addable(false)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('name')->disabled()->hiddenLabel(),
                                                                Forms\Components\TextInput::make('type')->disabled()->hiddenLabel(),
                                                            ]),
                                                    ];
                                                })
                                                ->fillForm(fn(?XlsformTemplateSection $record): array => [
                                                    'schema' => $record->schema,
                                                ])
                                                ->modalSubmitAction(false)
                                                ->modalCancelActionLabel('Close'),
                                        ]),

                                    TextEntry::make('dataset.name')->label('Target Dataset'),
                                ];
                            }),

                    ])
                    ->visible(fn(?XlsformTemplate $record): bool => $record->repeatingSections->count() > 0),

                Section::make('Draft Testing')
                    ->schema([

                        // show reminder text
                        TextEntry::make('reminder')
                            ->label('Please be remindeed that the form is only a draft. The ODK submissions sent to it will not be kept. It may not work well until you have added example csv files to any required datasets.'),

                        // show QR code of ODK form draft version
                        ViewEntry::make('qr_code')
                            ->label('Scan the QR code below in ODK Collect to view the test form.')
                            ->view('filament-odk-link::filament.infolists.entries.draft-testing-qr-code'),

                        // open URL in browser new tab
                        TextEntry::make('enketo_draft_url')->label('Click below link to view ODK form in browser')
                            ->url(fn(?XlsformTemplate $record): string => $record->enketo_draft_url)
                            ->openUrlInNewTab(),

                    ]),

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListXlsformTemplates::route('/'),
            'create' => Pages\CreateXlsformTemplate::route('/create'),
            'edit' => Pages\EditXlsformTemplate::route('/{record}/edit'),
            'view' => Pages\ViewXlsformTemplate::route('/{record}'),
        ];
    }
}
