<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class LinkModuleVersionToLocales implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;

    /** @var Collection<Language> */
    public Collection $languages;

    /**
     * Create a new job instance.
     */
    public function __construct(public XlsformModuleVersion|XlsformTemplate $model, Collection $headings)
    {
        $this->languages = $headings->map(
            fn (Collection $headings) => $headings
                ->map(
                    fn (string $heading) => (new XlsformTranslationHelper)
                        ->getLanguageFromColumnHeader($heading)
                )
        )->flatten();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $locales = $this->languages->map(function (Language $language): Locale {

            if ($language->defaultLocale) {
                return $language->defaultLocale;
            }

            // otherwise create it;
            return $language->defaultLocale()->create(['is_default' => true]);

        })->unique();

        // get set of moduleVersions to sync
        if ($this->model instanceof XlsformModuleVersion) {
            $xlsformModuleVersions = collect([$this->model]);
        } else {
            $xlsformModuleVersions = $this->model->xlsformModules->map(fn (XlsformModule $module) => $module->defaultXlsformVersion);
        }

        foreach ($xlsformModuleVersions as $xlsformModuleVersion) {

            $xlsformModuleVersion->locales()->syncWithPivotValues(
                ids: $locales->pluck('id')->toArray(),
                values: ['needs_update' => false, 'updated_during_import' => true],
                detaching: false,
            );

            // TODO: refactor this so we don't have n+1 database hits for each part.
            // mark locale pivots as needing update if they were not updated during this import
            $xlsformModuleVersion->locales()
                ->wherePivot('updated_during_import', false)
                ->get()
                ->each(fn (Locale $locale) => $xlsformModuleVersion->locales()->updateExistingPivot($locale->id, ['needs_update' => true]));

            // mark all locales as no longer updated during import
            $xlsformModuleVersion->locales()
                ->wherePivot('updated_during_import', true)
                ->get()
                ->each(fn (Locale $locale) => $xlsformModuleVersion->locales()->updateExistingPivot($locale->id, ['needs_update' => false]));
        }
    }
}
