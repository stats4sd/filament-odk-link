<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Forms\Components\Wizard;
use Filament\Support\Exceptions\Halt;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformValidationHelper;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateValidator;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;

class CreateXlsformTemplate extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = XlsformTemplateResource::class;

    // override form from HasWizard trait to add step to url
    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getSubmitFormAction())
                    ->skippable($this->hasSkippableSteps())
                    ->persistStepInQueryString(),
            ])
            ->columns(null);
    }

    public function getSteps(): array
    {
        return [
            Step::make('1. Xlsform')
                ->description('Upload your XLSForm file and give it a title')
                ->schema(
                    XlsformTemplateResource::getCreateFields(),
                )
                ->afterValidation(function (Get $get) {

                    try {
                        // find the full file path of the uploaded xlsform template excel file
                        $pathName = collect($get('newXlsfile'))->first()->getPathName();


                        // call helper function to perform custom validation for type or_other
                        $errorMessages = XlsformValidationHelper::validateTypeOrOther($pathName);

                        // show error messages if any
                        if ($errorMessages->count() > 0) {
                            $i = 0;

                            // show each error message in a notification
                            foreach ($errorMessages as $errorMessage) {
                                $i++;

                                Notification::make('xlsform_template_odk_variable_type_validation_failed_' . $i)
                                    ->title('XLSForm Template ODK variable type validation failed')
                                    ->body('Error: '. $errorMessage)
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                            // fail the wizard step, keep user in step 1
                            throw new Halt();
                        }


                        // call helper function to perform custom validation for unspecified language or non-existed language
                        $errorMessages = XlsformValidationHelper::validateColumnHeadersWithLanguageString($pathName);

                        // show error messages if any
                        if ($errorMessages->count() > 0) {
                            $i = 0;

                            // show each error message in a notification
                            foreach ($errorMessages as $errorMessage) {
                                $i++;

                                Notification::make('xlsform_template_language_validation_failed_' . $i)
                                    ->title('XLSForm Template language validation failed')
                                    ->body('Error: '. $errorMessage)
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                            // fail the wizard step, keep user in step 1
                            throw new Halt();
                        }


                        // wait to trigger the saved event until the xlsform file is attached.

                        // find the correct owner
                        $owner = (static::getResource())::getFormOwner();

                        /** @var XlsformTemplate $xlsformTemplate */
                        $xlsformTemplate = XlsformTemplate::make([
                            'title' => $get('title'),
                            'newXlsfile' => collect($get('newXlsfile'))->first(),
                        ]);

                        $xlsformTemplate->owner()->associate($owner);

                        $xlsformTemplate = $xlsformTemplate->testOnOdkCentral();

                        $xlsformTemplate->save();

                        Notification::make('xlsform_template_updated')
                            ->title('XLSForm Template Updated')
                            ->body('The XLSForm Template has been saved successfully.')
                            ->success()
                            ->persistent()
                            ->send();

                        return redirect($this->getResource()::getUrl('edit', ['record' => $xlsformTemplate]));
                    } catch (\Throwable $e) {

                        $notificationBody = 'There was an error saving the XLSForm Template. ODK Returned the following error: ' . $e->getMessage();

                        if ($e->getMessage() == '') {
                            $notificationBody = 'There was an error saving the XLSForm Template. Please refer to validation error message in other notification(s) for correction then try again.';
                        }

                        Notification::make('xlsform_template_not_saved')
                            ->title('XLSForm Template Not Saved')
                            ->body($notificationBody)
                            ->danger()
                            ->persistent()
                            ->send();

                        return redirect($this->getResource()::getUrl('create').'?step=1-xlsform&title='.urlencode($get('title')));
                    }

                }),

            Step::make('2. Add Media Files')
                ->description('Add any static media required by the form')
                ->schema([]),
            Step::make('3. Link Required Datasets')
                ->description('Add / link external datasets for lookup tables')
                ->schema([]),
            Step::make('4. Review Xlsform Structure')
                ->description('How should the collected data be handled?')
                ->schema([]),
        ];
    }
}
