<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use Filament\Actions;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class ViewXlsformTemplate extends ViewRecord
{
    protected static string $resource = XlsformTemplateResource::class;

    /**
     * @phpstan-return XlsformTemplate
     */
    public function getRecord(): Model|XlsformTemplate
    {
        /** @var XlsformTemplate $record */
        $record = parent::getRecord();

        return $record;
    }

    public function getTitle(): string
    {
        return self::getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('make_template_available')
                ->label('Make Template Available')
                ->icon('heroicon-o-pencil')
                ->disabled(fn($record) => $record->available == true)
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    $this->makeTemplateAvailable($record);
                }),
            Actions\Action::make('update_xlsform_template')
                ->label('Replace XLSForm')
                ->icon('heroicon-o-document-arrow-up')
                ->form(XlsformTemplateResource::getCreateFields())
                ->fillForm(fn() => [
                    'title' => self::getRecord()->title,
                ])
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
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->label('Edit Media & Data'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function makeTemplateAvailable(XlsformTemplate $record): XlsformTemplate
    {
        $record->available = true;
        $record->save();

        return $record;
    }
}
