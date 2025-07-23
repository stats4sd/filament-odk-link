<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use Filament\Forms\Get;
use Filament\Forms\Form;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Wizard;
use Filament\Support\Exceptions\Halt;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Stats4sd\FilamentOdkLink\Imports\XlsImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
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

                        logger('CreateXlsformTemplate.getSteps()->afterValidation()...');

                        // wait to trigger the saved event until the xlsform file is attached.

                        // find the correct owner
                        $owner = (static::getResource())::getFormOwner();

                        /** @var XlsformTemplate $xlsformTemplate */
                        $xlsformTemplate = XlsformTemplate::make([
                            'title' => $get('title'),
                            'newXlsfile' => collect($get('newXlsfile'))->first(),
                        ]);

                        $xlsformTemplate->owner()->associate($owner);

                        // TODO: check the uploaded excel file type column, throw error if it contains "or_other"


                        // hardcode temporary for testing
                        $unsupportedTypeFound = true;

                        if ($unsupportedTypeFound) {
                            throw new Halt('Type "or_other" is not recommended. Please do below updates on xlsform template and then try again. 1. Manually add an "Other" option to choices list. 2. Use a follow-up text question that is only relevant if "Other" is selected');
                        }


                        logger('before calling $xlsformTemplate->testOnOdkCentral()');

                        $xlsformTemplate = $xlsformTemplate->testOnOdkCentral();

                        logger('after calling $xlsformTemplate->testOnOdkCentral()');

                        $xlsformTemplate->save();

                        Notification::make('xlsform_template_updated')
                            ->title('XLSForm Template Updated')
                            ->body('The XLSForm Template has been saved successfully.')
                            ->success()
                            ->persistent()
                            ->send();

                        return redirect($this->getResource()::getUrl('edit', ['record' => $xlsformTemplate]));
                    } catch (\Throwable $e) {

                        Notification::make('xlsform_template_not_saved')
                            ->title('XLSForm Template Not Saved')
                            ->body('There was an error saving the XLSForm Template. ODK Returned the following error: '.$e->getMessage())
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
