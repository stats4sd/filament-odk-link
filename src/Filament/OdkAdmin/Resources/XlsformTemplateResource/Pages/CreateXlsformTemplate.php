<?php

namespace Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformTemplateResource\Pages;

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

                        $file = collect($get('xlsfile'))->first();

                        // wait to trigger the saved event until the xlsform file is attached.
                        $xlsformTemplate = XlsformTemplate::create([
                            'title' => $get('title'),
                            'xlsfile_temp' => collect($get('xlsfile'))->first(),
                        ]);

                        ray($xlsformTemplate);

                        dd('hi there');


                        if (!$result) {
                            $xlsformTemplate->delete();
                            Notification::make('xlsform_template_not_saved')
                                ->title('XLSForm Template Not Saved')
                                ->body('There was an error saving the XLSForm Template. It looks like the uploaded Xlsfile is not a valid XLSForm file.')
                                ->danger()
                                ->send();

                            return redirect($this->getResource()::getUrl('create') . '?step=1-xlsform&title=' . urlencode($get('title')));
                        }

                        $xlsformTemplate->save();

                        return redirect($this->getResource()::getUrl('edit', ['record' => $xlsformTemplate]));
                    } catch (RequestException $e) {

                        ray($e->getMessage());

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
