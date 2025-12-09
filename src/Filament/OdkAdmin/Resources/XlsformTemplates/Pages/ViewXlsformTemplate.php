<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Pages;

use Filament\Actions;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformValidationHelper;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplates\Schemas\XlsformTemplateForm;

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
                ->disabled(fn ($record) => $record->available == true)
                ->action(function (array $data, XlsformTemplate $record, Get $get) {
                    $this->makeTemplateAvailable($record);
                }),
            Actions\Action::make('update_xlsform_template')
                ->label('Replace XLSForm')
                ->icon('heroicon-o-document-arrow-up')
                ->schema(XlsformTemplateForm::getCreateFields())
                ->fillForm(function () {
                    $record = $this->getRecord();

                    return [
                        'title' => $record->title,
                    ];
                })
                ->action(function (array $data, XlsformTemplate $record) {
                    try {

                        $record->title = $data['title'];
                        $record->newXlsfile = $data['newXlsfile'];

                        $pathName = $record->newXlsfile->getPathName();

                        $orOtherErrors = XlsformValidationHelper::validateTypeOrOther($pathName);
                        $languageErrors = XlsformValidationHelper::validateColumnHeadersWithLanguageString($pathName);

                        $errorMessages = $orOtherErrors->merge($languageErrors);

                        // show error messages if any
                        if ($errorMessages->count() > 0) {

                            // fail the wizard step, keep user in step 1

                            // Add the error messages to a fake field. In Filament 3, only 1 error is shown on a single field.
                            // Filament 4 is updated to allow devs to show multiple errors for a single field if required.
                            // To show all errors, we add a custom Shout() component that reads from the errorBag.
                            // TODO: refactor this when Filament 4 is released.
                            throw ValidationException::withMessages(['data.fake-field' => $errorMessages->toArray()]);
                        }

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
                            ->send();

                        $this->getRecord()->refresh();

                        throw ValidationException::withMessages(['data.fake-field' => $e->getMessage()]);
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
