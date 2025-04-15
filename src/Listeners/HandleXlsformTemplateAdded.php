<?php

namespace Stats4sd\FilamentOdkLink\Listeners;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformModuleImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateChoiceListImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateWorkbookImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishChoiceListEntryImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishSurveyRowImport;
use Stats4sd\FilamentOdkLink\Jobs\ImportAllLanguageStrings;
use Stats4sd\FilamentOdkLink\Jobs\LinkModuleVersionToLocales;
use Stats4sd\FilamentOdkLink\Jobs\PrepareSurveyRowPaths;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class HandleXlsformTemplateAdded
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {

        /** @var XlsformModuleVersion | XlsformTemplate $model */
        $model = $event->media->model;

        // only process xlsform module versions or templates
        if (!$model instanceof XlsformModuleVersion && !$model instanceof XlsformTemplate) {
            return;
        }

        $filePath = $event->media->getPath();
        $moduleVersion = null;

        // for xlsform templates, create all the included xlsform modules.
        if ($model instanceof XlsformTemplate) {
            $moduleVersions = $this->createModules($filePath, $model);
        }

        if ($model instanceof XlsformModuleVersion) {
            $moduleVersion = $model;
        }

        $this->processXlsformTemplate($filePath, $model);

    }

    public function createModules(string $filePath, XlsformTemplate $model): Collection
    {
        // no queue as this is a small / quick import.
        (new XlsformModuleImport($model))->import($filePath);

        // get the 'default' version of all xlsform module versions for each module linked to the xlsform template.
        return $model
            ->xlsformModules
            ->map(
                fn(XlsformModule $module) => $module
                    ->xlsformModuleVersions
                    ->filter(fn(XlsformModuleVersion $xlsformModuleVersion) => $xlsformModuleVersion->is_default)
            )
            ->flatten();
    }

    public function processXlsformTemplate(string $filePath, XlsformModuleVersion | XlsformTemplate $model, string $moduleColumn = 'module'): void
    {
        // Get the translatable headings from the Xlsform workbook;
        $translatableHeadings = (new XlsformTranslationHelper)->getTranslatableColumnsFromFile($filePath);


        // make sure all the choice_lists are imported;
        (new XlsformTemplateChoiceListImport($model, $moduleColumn))->queue($filePath);

        // TODO: add validation check to make sure all names are unique in Survey + choices sheet...

        // Import the XLSform workbook to survey rows and choice list entries;
        (new XlsformTemplateWorkbookImport($model, $translatableHeadings, $moduleColumn))->queue($filePath)
            ->chain([
                new PrepareSurveyRowPaths($model),
                new FinishSurveyRowImport($model),
                new FinishChoiceListEntryImport($model),
                new LinkModuleVersionToLocales($model, $translatableHeadings),

                new ImportAllLanguageStrings($filePath, $model, $translatableHeadings),
            ]);


    }
}
