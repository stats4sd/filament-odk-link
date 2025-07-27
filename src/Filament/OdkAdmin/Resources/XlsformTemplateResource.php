<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources;

use Illuminate\Database\Eloquent\Relations\Relation;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\RelationManagers;
use Awcodes\Shout\Components\Shout;
use Awcodes\Shout\Components\ShoutEntry;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Forms\Components\HtmlBlock;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformTemplates;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

// Use this resource for an admin panel
// This resource is for templates that can be made available to all platform users

class XlsformTemplateResource extends Resource
{
    protected static ?string $model = XlsformTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'ODK Forms and Datasets';

    protected static WithXlsformTemplates $formOwner;

    public static function getFormOwner(): WithXlsformTemplates
    {
        return Platform::first();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('owner_type', '=', Platform::class);
    }

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
                ->disabledOn(['edit'])
                ->default(function () {
                    // get the title from url if it exists in the query string
                    return request()->query('title');
                }),

            Shout::make('file_info')
                ->content(new HtmlString('Please upload a valid Xlsform file. If you have not yet validated your form, we recommend you do so here: <a target="_blank" href="https://getodk.org/xlsform/" class="underline text-primary-800">https://getodk.org/xlsform/</a>.<br/><br/>Note that while in regular ODK the "settings" worksheet is optional, this system requires it, so please make sure you have a settings worksheet with at least the form_id and form_title variables added. See the <a href="https://docs.getodk.org/xlsform/#the-settings-sheet">ODK documentation here</a> for more information.')),
            Forms\Components\FileUpload::make('newXlsfile')
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
            Shout::make('validation_info')
                ->color('danger')
                ->visible(fn($livewire): bool => $livewire->getErrorBag()->any())
                ->content(fn ($livewire): HtmlString => new HtmlString(collect($livewire->getErrorBag()->all())->join('<br/><br/>'))),

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
                        $label .= '<p>The Form requires the following media items. Please either upload static csv files to be used, or mark the item(s) as localisable for each team. </p>';
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
                    Shout::make('dataset_info')
                        ->content('This platform is not set up to support ODK Entities. This csv file will be created based on individual team\'s choice list entries, which are editable through the front-end of this platform.')
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
                                            ->headers([
                                                Header::make('name'),
                                                Header::make('type'),
                                            ])
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
                                            ->headers([
                                                Header::make('name'),
                                                Header::make('type'),
                                            ])
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
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->sortable(),
                Tables\Columns\ViewColumn::make('required_fixed_media_count')
                    ->label('Fixed Media')
                    ->view('filament-odk-link::filament.tables.columns.required-fixed-media-count'),
                Tables\Columns\ViewColumn::make('required_data_media_count')
                    ->label('Datasets')
                    ->view('filament-odk-link::filament.tables.columns.required-data-media-count'),
                Tables\Columns\CheckboxColumn::make('available')
                    ->disabled(fn(XlsformTemplate $record) => $record->processing)
                    ->label('Available for use?')
                    ->sortable(),
                Tables\Columns\TextColumn::make('xlsforms_count')
                    ->label('# Deployments')
                    ->counts('xlsforms'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('update_xlsform_template')
                    ->disabled(fn(XlsformTemplate $record) => $record->processing)
                    ->label('Replace XLSForm')
                    ->icon('heroicon-o-document-arrow-up')
                    ->form(self::getCreateFields())
                    ->fillForm(function (XlsformTemplate $record) {
                        return [
                            'title' => $record->title,
                        ];
                    })
                    ->action(function (array $data, XlsformTemplate $record) {
                        try {

                            $record->title = $data['title'];
                            $record->newXlsfile = $data['newXlsfile'];

                            $record = $record->testOnOdkCentral();

                            $record->save();

                            Notification::make('xlsform_template_updated')
                                ->title('XLSForm Template Updated')
                                ->body('The XLSForm Template has been updated successfully.')
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {

                            Notification::make('xlsform_template_not_saved')
                                ->title('XLSForm Template Not Saved')
                                ->body('There was an error saving the XLSForm Template. ODK Returned the following error: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            $this->getRecord()->refresh();

                            $this->halt();
                        }
                    }),
                Tables\Actions\EditAction::make()->label('Edit Media & Data')
                    ->disabled(fn(XlsformTemplate $record) => $record->processing),
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
                ShoutEntry::make('Processing')
                    ->columnSpan('full')
                    ->visible(fn(?XlsformTemplate $record): bool => $record?->processing)
                    ->content('This Form is currently being processed, and is not yet available for use. This should only take a few minutes after being updated. If you see this notification for more than a few minutes, please contact support.'),
                Section::make('Xlsform Details')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('xlsfile_name')
                            ->url(fn(?XlsformTemplate $record): string => $record?->getFirstMediaUrl('xlsform_file')),
                        IconEntry::make('available')
                            ->label('Available to Platform users?')
                            ->icon(fn(bool $state): string => match ($state) {
                                false => 'heroicon-o-no-symbol',
                                true => 'heroicon-o-check-circle',
                            })
                            ->color(fn(bool $state): string => match ($state) {
                                false => 'gray',
                                true => 'success',
                            }),
                    ])
                    ->columns([
                        'xl' => 3,
                        'lg' => 2,
                        'md' => 1,
                    ]),
                Section::make('Attached media files')
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('requiredFixedMedia')
                            ->schema([
                                TextEntry::make('name')
                                    ->url(fn(?RequiredMedia $record): string => $record->getFirstMediaUrl()),
                                TextEntry::make('type'),
                                IconEntry::make('status')
                                    ->icon(fn(int $state): string => match ($state) {
                                        1 => 'heroicon-o-check-circle',
                                        default => 'heroicon-o-x-circle',
                                    })
                                    ->color(fn(int $state): string => match ($state) {
                                        1 => 'success',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns([
                                '2xl' => 3,
                                'xl' => 2,
                                'lg' => 1,
                            ]),

                        RepeatableEntry::make('requiredDataMedia')
                            ->schema([
                                TextEntry::make('name')
                                    ->url(fn(?RequiredMedia $record): string => $record->getFirstMediaUrl()),
                                TextEntry::make('full_type'),
                                IconEntry::make('status')
                                    ->icon(fn(int $state): string => match ($state) {
                                        1 => 'heroicon-o-check-circle',
                                        default => 'heroicon-o-x-circle',
                                    })
                                    ->color(fn(int $state): string => match ($state) {
                                        1 => 'success',
                                        default => 'gray',
                                    }),
                            ])->columns([
                                'lg' => 3,
                                'md' => 2,
                                'sm' => 1,
                            ]),
                    ]),
                Section::make('Main Survey')
                    ->collapsed()
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
                                                ->headers([
                                                    Header::make('name'),
                                                    Header::make('type'),
                                                ])
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
                            ->placeholder('No dataset linked')
                            ->inlineLabel()
                            ->url(function (?XlsformTemplate $record): ?string {

                                if ($record->rootSection->dataset_id) {
                                    return DatasetResource::getUrl('view', ['record' => $record->rootSection->dataset_id]);
                                }

                                // if no dataset is linked, return null
                                return null;
                            }),

                    ]),

                Section::make('Repeat Groups')
                    ->collapsed()
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
                                                            ->headers([
                                                                Header::make('name'),
                                                                Header::make('type'),
                                                            ])
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

                                    TextEntry::make('dataset.name')->label('Data from this repeat group is added to:')
                                        ->placeholder('No dataset linked')
                                        ->inlineLabel()
                                        ->url(function (XlsformTemplateSection $record): ?string {

                                            if ($record->dataset_id) {
                                                return DatasetResource::getUrl('view', ['record' => $record->dataset_id]);
                                            }

                                            // if no dataset is linked, return null
                                            return null;
                                        }),
                                ];
                            }),

                    ])
                    ->visible(fn(?XlsformTemplate $record): bool => $record->repeatingSections->count() > 0),

                Section::make('Draft Testing')
                    ->collapsed()
                    ->schema([

                        // show reminder text
                        TextEntry::make('reminder')
                            ->label('You may use the QR code and link below to test this form template before making it available to teams. Any submissions sent to this draft version will not be saved. If you want to do a more comprehensive test and review the data in the platform, we recommend using a "test" team and deploying a live version of the form to that team.'),

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

    public static function getRelations(): array
    {
        return [
            RelationManagers\XlsformModuleRelationManager::class,
        ];
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
