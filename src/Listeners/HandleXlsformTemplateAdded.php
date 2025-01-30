<?php

namespace Stats4sd\FilamentOdkLink\Listeners;

use App\Models\Xlsforms\XlsformTemplate;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XLsformModuleImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateChoiceListImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateWorkbookImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishChoiceListEntryImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishSurveyRowImport;
use Stats4sd\FilamentOdkLink\Jobs\ImportAllLanguageStrings;
use Stats4sd\FilamentOdkLink\Jobs\LinkModuleVersionToLocales;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class HandleXlsformTemplateAdded
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $model = $event->media->model;

        // only process xlsform module vesrsions or templates
        if (!$model instanceof XlsformModuleVersion && !$model instanceof XlsformTemplate) {
            return;
        }


        $filePath = $event->media->getPath();
        $moduleVersions = collect();

        // for xlsformtemplates, create all the included xlsformmodules.
        if ($model instanceof XlsformTemplate) {
            $moduleVersions = $this->createModules($filePath, $model);
        }

        if ($model instanceof XlsformModuleVersion) {
            $moduleVersions = collect([$model]);
        }

        // for a single module version upload, just run the process once
        $this->processXlsformTemplate($filePath, $moduleVersions);

    }

    public function createModules(string $filePath, XlsformTemplate $model): Collection
    {
        // no queue as this is a small / quick import.
        (new XLsformModuleImport($model))->import($filePath);

        // get the 'default' version of all xlsform module versions for each module linked to the xlsform template.
        return $model
            ->xlsformModules
            ->map(fn(XlsformModule $module) => $module
                ->xlsformModuleVersions
                ->filter(fn(XlsformModuleVersion $xlsformModuleVersion) => $xlsformModuleVersion->is_default)
            )
            ->flatten();
    }

    public function processXlsformTemplate(string $filePath, Collection $moduleVersions): void
    {
        // Get the translatable headings from the XLSform workbook;
        $translatableHeadings = (new XlsformTranslationHelper())->getTreanslatableColumnsFromFile($filePath);

        $moduleVersions->each(function (XlsformModuleVersion $moduleVersion) use ($translatableHeadings, $filePath) {

            // make sure all the choice_lists are imported;
            (new XlsformTemplateChoiceListImport($moduleVersion))->queue($filePath);

            // TODO: add validation check to make sure all names are unique in Survey + choices sheet...

            // Import the XLSform workbook to survey rows and choice list entries;
            (new XlsformTemplateWorkbookImport($moduleVersion, $translatableHeadings))->queue($filePath)
                ->chain([
                    new FinishSurveyRowImport($moduleVersion),
                    new FinishChoiceListEntryImport($moduleVersion),
                    new LinkModuleVersionToLocales($moduleVersion, $translatableHeadings),

                    new ImportAllLanguageStrings($filePath, $moduleVersion, $translatableHeadings),
                ]);

        });
    }


}
