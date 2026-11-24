<?php

namespace Stats4sd\OdkLink\Core\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\OdkLink\Core\Imports\XlsformTemplate\XlsformModuleImport;
use Stats4sd\OdkLink\Core\Imports\XlsformTemplate\XlsformTemplateChoiceListImport;
use Stats4sd\OdkLink\Core\Imports\XlsformTemplate\XlsformTemplateWorkbookImport;
use Stats4sd\OdkLink\Core\Jobs\FinishChoiceListEntryImport;
use Stats4sd\OdkLink\Core\Jobs\FinishSurveyRowImport;
use Stats4sd\OdkLink\Core\Jobs\FinishXlsformTemplateImport;
use Stats4sd\OdkLink\Core\Jobs\ImportAllLanguageStrings;
use Stats4sd\OdkLink\Core\Jobs\LinkModuleVersionToLocales;
use Stats4sd\OdkLink\Core\Jobs\PrepareSurveyRowPaths;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModule;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformTemplate;
use Stats4sd\OdkLink\Core\Services\XlsformTranslationHelper;

class HandleXlsformTemplateAdded
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {

        /** @var XlsformModuleVersion | XlsformTemplate | Model $model */
        $model = $event->media->model;

        // only process xlsform module versions or templates
        if (! $model instanceof XlsformModuleVersion && ! $model instanceof XlsformTemplate) {
            return;
        }

        $filePath = $event->media->getPath();

        // for xlsform templates, create all the included xlsform modules and sync entity lists.
        if ($model instanceof XlsformTemplate) {
            $this->createModules($filePath, $model);
            $model->syncEntityListsFromFile();
        }

        $model->updateQuietly(['processing' => true]);
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
                fn (XlsformModule $module) => $module
                    ->xlsformModuleVersions
                    ->filter(fn (XlsformModuleVersion $xlsformModuleVersion) => $xlsformModuleVersion->is_default)
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
                new FinishXlsformTemplateImport($model),
            ]);

    }
}
