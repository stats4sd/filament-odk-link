<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource\Pages;

use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\RequestException;
use Stats4sd\FilamentOdkLink\Filament\Resources\XlsformTemplateResource;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class CreateXlsformTemplate extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = XlsformTemplateResource::class;

    public function getSteps(): array
    {
        return [
            Step::make('1. Xlsform')
                ->description('Upload your XLSForm file and give it a title')
                ->schema(
                    XlsformTemplateResource::getCreateFields(),
                )
                ->afterValidation(function (Get $get) {

                    $xlsformTemplate = XlsformTemplate::create([
                        'title' => $get('title'),
                    ]);

                    $files = $get('xlsfile');

                    $xlsformTemplate->addMedia(collect($files)->first())->toMediaCollection('xlsform_file');

                    // this was being triggered on afterCreate. Call it here instead/as well.
                    $this->processRecord($xlsformTemplate);

                    return redirect($this->getResource()::getUrl('edit', ['record' => $xlsformTemplate]));

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

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     */
    protected function afterCreate(): void
    {
        $test = $this->getRecord();

        assert($test instanceof XlsformTemplate);
        $this->processRecord($test);
    }

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     */
    protected function processRecord(XlsformTemplate $record): XlsformTemplate
    {
        $odkLinkService = app()->make(OdkLinkService::class);

        $record->owner()->associate(Platform::first());
        $record->saveQuietly();

        // update form title in xlsfile to match user-given title
        UpdateXlsformTitleInFile::dispatchSync($record);

        $record->refresh();
        $record->deployDraft($odkLinkService);
        $record->getRequiredMedia($odkLinkService);

        // TODO: We need to do the extract section when create and edit
        $record->extractSections();

        return $record;
    }
}
