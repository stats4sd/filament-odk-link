<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateForm;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformTemplateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->sortable(),
                ViewColumn::make('required_fixed_media_count')
                    ->label('Fixed Media')
                    ->view('filament-odk-link::filament.tables.columns.required-fixed-media-count'),
                ViewColumn::make('required_data_media_count')
                    ->label('Datasets')
                    ->view('filament-odk-link::filament.tables.columns.required-data-media-count'),
                CheckboxColumn::make('available')
                    ->disabled(fn (XlsformTemplate $record) => $record->processing)
                    ->label('Available for use?')
                    ->sortable(),
                TextColumn::make('xlsforms_count')
                    ->label('# Deployments')
                    ->counts('xlsforms'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('update_xlsform_template')
                    ->disabled(fn (XlsformTemplate $record) => $record->processing)
                    ->label('Replace XLSForm')
                    ->icon('heroicon-o-document-arrow-up')
                    ->modalWidth('lg')
                    ->modalContent(fn (XlsformTemplate $record) => XlsformTemplateForm::getCreateFields())
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
                                ->body('There was an error saving the XLSForm Template. ODK Returned the following error: '.$e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            $record->refresh();

                            return;
                        }
                    }),
                EditAction::make()
                    ->label('Edit Media & Data')
                    ->disabled(fn (XlsformTemplate $record) => $record->processing),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
