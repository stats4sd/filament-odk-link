<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\DatasetResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Schemas\DatasetForm;
use Stats4sd\FilamentOdkLink\Forms\Components\HtmlBlock;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

class XlsformTemplateInfoList
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->schema([
                Callout::make()
                    ->info()
                    ->columnSpan('full')
                    ->visible(fn (?XlsformTemplate $record): bool => $record?->processing)
                    ->description('This Form is currently being processed, and is not yet available for use. This should only take a few minutes after being updated. If you see this notification for more than a few minutes, please contact support.'),
                Section::make('Xlsform Details')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('xlsfile_name')
                            ->url(fn (?XlsformTemplate $record): string => $record?->getFirstMediaUrl('xlsform_file')),
                        IconEntry::make('available')
                            ->label('Available to Platform users?')
                            ->icon(fn (bool $state): string => match ($state) {
                                false => 'heroicon-o-no-symbol',
                                true => 'heroicon-o-check-circle',
                            })
                            ->color(fn (bool $state): string => match ($state) {
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
                                    ->url(fn (?RequiredMedia $record): string => $record->getFirstMediaUrl()),
                                TextEntry::make('type'),
                                IconEntry::make('status')
                                    ->icon(fn (int $state): string => match ($state) {
                                        1 => 'heroicon-o-check-circle',
                                        default => 'heroicon-o-x-circle',
                                    })
                                    ->color(fn (int $state): string => match ($state) {
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
                                    ->url(fn (?RequiredMedia $record): string => $record->getFirstMediaUrl()),
                                TextEntry::make('full_type'),
                                IconEntry::make('status')
                                    ->icon(fn (int $state): string => match ($state) {
                                        1 => 'heroicon-o-check-circle',
                                        default => 'heroicon-o-x-circle',
                                    })
                                    ->color(fn (int $state): string => match ($state) {
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
                            ->visible(fn (?XlsformTemplate $record): bool => $record->rootSection->schema->count() < 5),

                        ViewEntry::make('schema')
                            ->view('filament-odk-link::filament.infolists.components.xlsform-section-schema-modal-link')
                            ->registerActions([
                                Action::make('viewSchema')
                                    ->label('View variable list')
                                    ->icon('heroicon-o-eye')
                                    ->schema(function (?XlsformTemplate $record) {
                                        return [
                                            Repeater::make('schema')
                                                ->label('List of variables in the main survey')
                                                ->deletable(false)
                                                ->reorderable(false)
                                                ->addable(false)
                                                // ->headers([
                                                //     Header::make('name'),
                                                //     Header::make('type'),
                                                // ])
                                                ->schema([
                                                    TextInput::make('name')->disabled()->hiddenLabel(),
                                                    TextInput::make('type')->disabled()->hiddenLabel(),
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
                            ->visible(fn (?XlsformTemplate $record): bool => $record->rootSection->schema->count() >= 5),

                        TextEntry::make('rootSection.dataset.name')->label('Submission data is added to:')
                            ->placeholder('No dataset linked')
                            ->inlineLabel()
                            ->url(function (?XlsformTemplate $record): ?string {

                                // if ($record->rootSection->dataset_id) {
                                //     return DatasetResource::getUrl('view', ['record' => $record->rootSection->dataset_id]);
                                // }

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
                                            Action::make('viewSchema')
                                                ->label('View variable list')
                                                ->icon('heroicon-o-eye')
                                                ->schema(function (?XlsformTemplateSection $record) {
                                                    return [
                                                        Repeater::make('schema')
                                                            ->label('List of variables in the repeat group')
                                                            ->deletable(false)
                                                            ->reorderable(false)
                                                            ->addable(false)
                                                            // ->headers([
                                                            //     Header::make('name'),
                                                            //     Header::make('type'),
                                                            // ])
                                                            ->schema([
                                                                TextInput::make('name')->disabled()->hiddenLabel(),
                                                                TextInput::make('type')->disabled()->hiddenLabel(),
                                                            ]),
                                                    ];
                                                })
                                                ->fillForm(fn (?XlsformTemplateSection $record): array => [
                                                    'schema' => $record->schema,
                                                ])
                                                ->modalSubmitAction(false)
                                                ->modalCancelActionLabel('Close'),
                                        ]),

                                    TextEntry::make('dataset.name')->label('Data from this repeat group is added to:')
                                        ->placeholder('No dataset linked')
                                        ->inlineLabel()
                                        ->url(function (XlsformTemplateSection $record): ?string {

                                            // if ($record->dataset_id) {
                                            //     return DatasetResource::getUrl('view', ['record' => $record->dataset_id]);
                                            // }

                                            // if no dataset is linked, return null
                                            return null;
                                        }),
                                ];
                            }),

                    ])
                    ->visible(fn (?XlsformTemplate $record): bool => $record->repeatingSections->count() > 0),

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
                            ->url(fn (?XlsformTemplate $record): string => $record->enketo_draft_url)
                            ->openUrlInNewTab(),

                    ]),

            ])
            ->columns(1);
    }

    public static function getXlsformSectionFields(): array
    {
        return [

            HtmlBlock::make('title')
                ->content(fn (?XlsformTemplate $record): HtmlString => new HtmlString("
                <h3 class='text-xl'>$record->title - Form Structure</h3>
                <p>On this page, you can review the structure of the data that will come from form submissions. The 'main survey' section includes all the variables that are not in repeat groups. You should choose or create a dataset for the form submissions to populate.</p>

            ")),
            Fieldset::make('rootSection')
                ->label('Main Survey')
                ->relationship('rootSection')
                ->schema([
                    ViewField::make('schema')
                        ->view('filament-odk-link::filament.forms.components.xlsform-section-schema-modal-link')
                        ->registerActions([
                            Action::make('viewSchema')
                                ->label('View variable list')
                                ->icon('heroicon-o-eye')
                                ->schema(function (?XlsformTemplateSection $record) {
                                    return [
                                        Repeater::make('schema')
                                            ->label('List of variables in the main survey')
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->addable(false)
                                            // ->headers([
                                            //     Header::make('name'),
                                            //     Header::make('type'),
                                            // ])
                                            ->schema([
                                                TextInput::make('name')->disabled()->hiddenLabel(),
                                                TextInput::make('type')->disabled()->hiddenLabel(),
                                            ]),
                                    ];
                                })
                                ->fillForm(function (?XlsformTemplateSection $record): array {
                                    return [
                                        'schema' => $record?->schema,
                                    ];
                                })
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close'),
                        ])
                        ->visible(fn (?XlsformTemplateSection $record): bool => $record?->schema->count() >= 5),

                    Select::make('dataset_id')
                        ->relationship('dataset', 'name')
                        ->label('Select which dataset the submissions should be linked to')
                        ->createOptionForm(DatasetForm::getCreateFormFields())
                        ->createOptionModalHeading('Create New Dataset'),
                ]),

            Repeater::make('repeatingSections')
                ->columns([
                    'md' => 2,
                    'sm' => 1,
                ])
                ->label(function (?XlsformTemplate $record) {
                    $label = "<h3 class='text-lg'>Repeat Groups</h3><p class='font-light'>This form also has {$record?->repeatingSections()->count()} repeat groups within the form. The data from these repeat groups should be linked to a different dataset. For example, in a household survey, you may link the 'main' survey submission data to a dataset called 'Households', and a repeat group asking information from each member to a dataset called 'Household Members'.</p><br/>";

                    return new HtmlString($label);
                })
                ->itemLabel(fn (array $state): ?string => $state['structure_item'] ?? null)
                ->visible(fn (?XlsformTemplate $record): bool => $record->repeatingSections()->count() > 0)
                ->relationship()
                ->addable(false)
                ->deletable(false)
                ->schema([
                    ViewField::make('schema')
                        ->view('filament-odk-link::filament.forms.components.xlsform-section-schema-modal-link')
                        ->registerActions([
                            Action::make('viewSchema')
                                ->label('View variable list')
                                ->icon('heroicon-o-eye')
                                ->schema(function (?XlsformTemplateSection $record) {
                                    return [
                                        Repeater::make('schema')
                                            ->label(fn (?XlsformTemplateSection $record) => "List of variables in the $record->structure_item repeat group")
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->addable(false)
                                            // ->headers([
                                            //     Header::make('name'),
                                            //     Header::make('type'),
                                            // ])
                                            ->schema([
                                                TextInput::make('name')->disabled()->hiddenLabel(),
                                                TextInput::make('type')->disabled()->hiddenLabel(),
                                            ]),
                                    ];
                                })
                                ->fillForm(function (?XlsformTemplateSection $record): array {
                                    return [
                                        'schema' => $record?->schema,
                                    ];
                                })
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close'),
                        ]),
                    Select::make('dataset_id')
                        ->relationship('dataset', 'name')
                        ->label('Select which dataset the submissions should be linked to')
                        ->createOptionForm(DatasetForm::getCreateFormFields())
                        ->createOptionModalHeading('Create New Dataset'),
                ]),
        ];
    }
}
