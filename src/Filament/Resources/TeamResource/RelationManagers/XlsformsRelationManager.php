<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\ViewField;
use Illuminate\Database\Eloquent\Builder;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Filament\Resources\RelationManagers\RelationManager;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformsRelationManager extends RelationManager
{
    protected static string $relationship = 'xlsforms';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\Select::make('xlsform_template_id')
                    ->relationship(
                        name: 'xlsformTemplate',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query) => $query->where('available', true)
                    )
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('title', $state ? XlsformTemplate::find($state)->title : '')),

                Forms\Components\TextInput::make('title')
                    ->helperText('By default, this is the title of the Template you select. If you want multiple instances of the same form template, you should give each a unique title.')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                // // show QR code
                // ViewField::make('qr_code')
                //     ->label('Scan the QR code below in ODK Collect to view the test form.')
                //     ->view('filament-odk-link::filament.forms.components.draft-testing-qr-code'),

                // // show Enteko link as a clickable link
                // ViewField::make('enketo_draft_url')
                //     ->label('Click below link to view ODK form in browser')
                //     ->view('filament-odk-link::filament.forms.components.clickable-link'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->grow(false),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\ViewColumn::make('team_datasets_required')
                    ->view('filament-odk-link::filament.tables.columns.team-datasets-required'),
                Tables\Columns\TextColumn::make('submissions_count')->counts('submissions')
                    ->label('No. of Submissions'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Xlsform to Team')
                    ->after(function (Xlsform $record) {

                        $odkLinkService = app()->make(OdkLinkService::class);

                        if (! $record->xlsfile) {
                            $record->updateXlsfileFromTemplate();
                        }

                        UpdateXlsformTitleInFile::dispatchSync($record);

                        $record->refresh();
                        $record->deployDraft($odkLinkService);

                    }),
            ])
            ->actions([

                // add Publish button
                Tables\Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-m-arrow-up-tray')
                ->requiresConfirmation()
                ->action(function (Xlsform $record) {
                    $odkLinkService = app()->make(OdkLinkService::class);

                    // create draft if there is no draft yet
                    if (!$record->has_draft) {
                        $odkLinkService->createDraftForm($record);
                    }

                    // call API to publish form in ODK central
                    $odkLinkService->publishForm($record);
                }),

                // add Pull Submissions button
                Tables\Actions\Action::make('pull_submissions')
                ->label('Pull Submissions')
                ->icon('heroicon-m-arrow-down-tray')
                ->action(function (Xlsform $record) {
                    $odkLinkService = app()->make(OdkLinkService::class);

                    // call API to pull submissions from ODK central
                    $odkLinkService->getSubmissions($record);
                }),

                // add Pull Submissions button
                Tables\Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-m-document-arrow-down')
                ->action(function (Xlsform $record) {
                    $odkLinkService = app()->make(OdkLinkService::class);

                    // call API to pull submissions from ODK central
                    $odkLinkService->exportAsExcelFile($record);

                }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function infoList(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('submissions_count')->counts('submissions')
                    ->label('No. of Submissions'),
            ]);
    }
}
