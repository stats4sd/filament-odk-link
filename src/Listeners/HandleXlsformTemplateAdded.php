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
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class HandleXlsformTemplateAdded
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        ray('hi');

        $model = $event->media->model;
        ray($model);

        // only process xlsform module versions or templates
        if (!$model instanceof XlsformModuleVersion && !$model instanceof XlsformTemplate) {
            return;
        }

        $filePath = $event->media->getPath();
        $moduleVersions = collect();

        // for xlsform templates, create all the included xlsform modules.
        if ($model instanceof XlsformTemplate) {
            $moduleVersions = $this->createModules($filePath, $model);
        }

        if ($model instanceof XlsformModuleVersion) {
            $moduleVersions = collect([$model]);
        }

        $this->processXlsformTemplate($filePath, $moduleVersions);

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

    public function processXlsformTemplate(string $filePath, Collection $moduleVersions, string $moduleColumn = 'module'): void
    {
        // Get the translatable headings from the Xlsform workbook;
        $translatableHeadings = (new XlsformTranslationHelper)->getTranslatableColumnsFromFile($filePath);

        $moduleVersions->each(function (XlsformModuleVersion $moduleVersion) use ($translatableHeadings, $filePath, $moduleColumn) {

            // make sure all the choice_lists are imported;
            (new XlsformTemplateChoiceListImport($moduleVersion, $moduleColumn))->queue($filePath);

            // TODO: add validation check to make sure all names are unique in Survey + choices sheet...

            // Import the XLSform workbook to survey rows and choice list entries;
            (new XlsformTemplateWorkbookImport($moduleVersion, $translatableHeadings, $moduleColumn))->queue($filePath)
                ->chain([
                    new FinishSurveyRowImport($moduleVersion),
                    new FinishChoiceListEntryImport($moduleVersion),
                    new LinkModuleVersionToLocales($moduleVersion, $translatableHeadings),

                    new ImportAllLanguageStrings($filePath, $moduleVersion, $translatableHeadings),
                ]);

        });
    }
}
