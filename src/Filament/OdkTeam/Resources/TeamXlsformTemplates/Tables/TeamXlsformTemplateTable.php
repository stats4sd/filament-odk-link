<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkTeam\Resources\TeamXlsformTemplates\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Forms\Components\TextInput;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TeamXlsformTemplateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                IconColumn::make('has_version')
                    ->label('In use?')
                    ->state(fn (Xlsformtemplate $record) => $record->xlsforms->where('owner_id', Filament::getTenant()->getKey())->count() > 0)
                    ->boolean(),
                TextColumn::make('owner_type')
                    ->label('Source')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return $record->owner_type === Platform::class ? 'Global' : 'Project';
                    })
                    ->color(function ($record) {
                        return $record->owner_type === Platform::class ? 'gray' : 'success';
                    }),
                ViewColumn::make('required_fixed_media_count')
                    ->label('Fixed Media')
                    ->view('filament-odk-link::filament.tables.columns.required-fixed-media-count'),
                ViewColumn::make('required_data_media_count')
                    ->label('Datasets')
                    ->view('filament-odk-link::filament.tables.columns.required-data-media-count'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // TODO: setup a helper function that a) returns the current tenant as a "WithXlsforms" class, and b) makes sure that devs realise the Filament tenant must implement this interface.
                Action::make('deploy')
                    ->label('Deploy Form')
                    ->hidden(fn (Xlsformtemplate $record) => $record->xlsforms->where('owner_id', Filament::getTenant()->getKey())->count() > 0)
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->form([
                        TextInput::make('title')
                            ->label('Please give the form a title.')
                            ->default(fn (XlsformTemplate $record) => HelperService::getCurrentOwner()->name.' - '.$record->title)
                            ->hint('Note that ODK form titles cannot be longer than 64 characters.'),
                    ])
                    ->action(function (XlsformTemplate $record, array $data) {

                        $xlsform = $record->xlsforms()->create([
                            'owner_id' => Filament::getTenant()->getKey(),
                            'owner_type' => config('filament-odk-link.models.form_owner'),
                            'title' => $data['title'],
                        ]);

                        // publish the new form
                        $xlsform->refresh();
                        $xlsform->publishForm();

                    }),
                Action::make('download file')
                    ->label('Download XLS File')
                    ->url(fn ($record) => $record->getFirstMediaUrl('xlsform_file')),

            ]);
    }
}