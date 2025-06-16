<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources;

use Awcodes\FilamentTableRepeater\Components\TableRepeater;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource\Pages\CreateTeamCustomXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource\Pages\EditTeamCustomXlsformTemplate;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource\Pages\ListTeamCustomXlsformTemplates;
use Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamCustomXlsformTemplateResource\Pages\ViewTeamCustomXlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

// Use this resource for a panel scoped to a team
// This resource is for a team to add their own custom templates

class TeamCustomXlsformTemplateResource extends Resource
{
    protected static ?string $model = XlsformTemplate::class;

    public static ?string $label = 'Custom Xlsform Templates';

    protected static ?string $slug = 'custom-xlsform-templates';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Custom ODK Templates';

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getCurrentPanel()->getPlugin('stats4sd-odk-link-team')->getShouldRegisterNavigation();
    }

    //  manually scope to Team tenant
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('owner', function (Builder $query) {
                $query->where('id', Filament::getTenant()->getKey());
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ViewColumn::make('required_fixed_media_count')
                    ->label('Fixed Media')
                    ->view('filament-odk-link::filament.tables.columns.required-fixed-media-count'),
                Tables\Columns\ViewColumn::make('required_data_media_count')
                    ->label('Datasets')
                    ->view('filament-odk-link::filament.tables.columns.required-data-media-count'),
                Tables\Columns\CheckboxColumn::make('available')
                    ->label('Available for use?')
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_version')
                    ->label('Deployed?')
                    ->state(fn (Xlsformtemplate $record) => $record->xlsforms->count() > 0)
                    ->boolean(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->label('Edit Media & Data'),
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
                Section::make('Xlsform Details')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('xlsfile_name')
                            ->url(fn (?XlsformTemplate $record): string => $record?->getFirstMediaUrl('xlsform_file')),
                        IconEntry::make('available')
                            ->label('Available to Platform users?')
                            ->icon(fn (bool $state): string => match ($state) {
                                false => 'heroicon-o-no-symbol',
                                true => 'heroicon-o-check-circle',
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
                                        default => 'heroicon-o-question-circle',
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
                            ->inlineLabel(),
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
                                        ->inlineLabel(),
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

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamCustomXlsformTemplates::route('/'),
            'create' => CreateTeamCustomXlsformTemplate::route('/create'),
            'edit' => EditTeamCustomXlsformTemplate::route('/{record}/edit'),
            'view' => ViewTeamCustomXlsformTemplate::route('/{record}'),
        ];
    }
}
