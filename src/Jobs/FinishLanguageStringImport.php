<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class FinishLanguageStringImport implements ShouldQueue
{
    use Queueable;


    public function __construct(public XlsformModuleVersion|XlsformTemplate $model, public string $heading)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // need to filter the class by the templateLanguage + string  type
        $xlsformTranslationHelper = new XlsformTranslationHelper();

        $language = $xlsformTranslationHelper->getLanguageFromColumnHeader($this->heading);
        $languageStringType = $xlsformTranslationHelper->getLanguageStringTypeFromColumnHeader($this->heading);

        $this->model
            ->surveyLanguageStrings()
            ->where('language_string_type_id', $languageStringType->id)
            ->where('locale_id', $language->defaultLocale->id)
            ->update(['language_strings.updated_during_import' => false]);

        $this->model
            ->choiceListEntryLanguageStrings()
            ->where('language_string_type_id', $languageStringType->id)
            ->where('locale_id', $language->defaultLocale->id)
            ->update(['language_strings.updated_during_import' => false]);

    }
}
