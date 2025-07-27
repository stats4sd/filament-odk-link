<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\IsXlsformTemplate;
use App\Models\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformValidationHelper;

class CreateXlsformTemplate extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = XlsformTemplateResource::class;

    protected function onValidationError(ValidationException $exception): void
    {

    }

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

                       $this->beforeXlsformTemplateSaved($xlsformTemplate);

                        $xlsformTemplate->save();

                       $this->afterXlsformTemplateSaved($xlsformTemplate);

                        Notification::make('xlsform_template_updated')
                            ->title('XLSForm Template Updated')
                            ->body('The XLSForm Template has been saved successfully.')
                            ->success()
                            ->persistent()
                            ->send();

                        return redirect($this->getResource()::getUrl('edit', ['record' => $xlsformTemplate]));
                    } catch (ValidationException $e) {

                        Notification::make('xlsform_template_not_saved')
                            ->title('XLSForm Template Not Saved')
                            ->body('Please check the file upload and review the error messages.')
                            ->danger()
                            ->persistent()
                            ->send();

                        throw $e;
                    } catch (\Throwable $e) {

                        // Generic catch-all for errors coming back from ODK Central. Convert them into validation errors to be displayed on the front-end.

                        $notificationBody = 'There was an error saving the XLSForm Template. ODK Returned the following error: '.$e->getMessage();

                        if ($e->getMessage() == '') {
                            $notificationBody = 'There was an error saving the XLSForm Template.';
                        }

                        Notification::make('xlsform_template_not_saved')
                            ->title('XLSForm Template Not Saved')
                            ->body($notificationBody)
                            ->danger()
                            ->persistent()
                            ->send();

                        throw ValidationException::withMessages(['data.fake-field' => [$notificationBody]]);
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


    // Placeholder function - can override this function to perform extra actions
    // - after the form is sent to ODK Central and is successfully validated, but before it is saved.
    public function beforeXlsformTemplateSaved(?IsXlsformTemplate $xlsformTemplate = null)
    {}

    // placeholder function. Can override this function to perform extra actions after the $xlsformTemplate is saved
    public function afterXlsformTemplateSaved(IsXlsformTemplate $xlsformTemplate)
    {}
}
