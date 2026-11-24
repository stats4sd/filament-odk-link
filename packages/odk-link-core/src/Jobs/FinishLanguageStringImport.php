<?php

namespace Stats4sd\OdkLink\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\OdkLink\Core\Concerns\ResetsProcessingOnFailure;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformTemplate;
use Stats4sd\OdkLink\Core\Services\XlsformTranslationHelper;

class FinishLanguageStringImport implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;

    public function __construct(public XlsformModuleVersion | XlsformTemplate $model, public string $heading) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // need to filter the class by the templateLanguage + string  type
        $xlsformTranslationHelper = new XlsformTranslationHelper;

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
