<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

use App\Services\HelperService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

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

                        // wait to trigger the saved event until the xlsform file is attached.
                        /** @var XlsformTemplate $xlsformTemplate */
                        $xlsformTemplate = XlsformTemplate::make([
                            'title' => $get('title'),
                            'newXlsfile' => collect($get('newXlsfile'))->first(),
                        ]);

                        $xlsformTemplate->owner()->associate(Platform::first());

                        $xlsformTemplate = $xlsformTemplate->testOnOdkCentral();

                        $xlsformTemplate->save();

                        Notification::make('xlsform_template_updated')
                            ->title('XLSForm Template Updated')
                            ->body('The XLSForm Template has been updated successfully.')
                            ->success()
                            ->persistent()
                            ->send();

                        return redirect($this->getResource()::getUrl('edit', ['record' => $xlsformTemplate]));
                    } catch (\Throwable $e) {

                        ray($e);

                        Notification::make('xlsform_template_not_saved')
                            ->title('XLSForm Template Not Saved')
                            ->body('There was an error saving the XLSForm Template. ODK Returned the following error: ' . $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return redirect($this->getResource()::getUrl('create') . '?step=1-xlsform&title=' . urlencode($get('title')));
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
