<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/**
 * @phpstan-require-extends Model
 *
 * @property string $name
 * @property Collection<Xlsform> $xlsforms
 * @property Collection<Language> $languages
 * @property Collection<Locale> $locales
 * @property Collection<Locale> $createdLocales
 * @property Collection<ChoiceList> $choiceLists
 * @property Collection<ChoiceListEntry> $choiceListEntries
 * @property Collection<ChoiceListEntry> $choiceListEntriesRemovedFromContext
 * @property Country $country
 * @property Collection<XlsformModuleVersion> $xlsformModuleVersions
 * @property Collection<XlsformTemplate> $xlsformTemplates
 * @property OdkProject $odkProject
 *
 */
interface WithXlsforms
{
    public function xlsforms(): HasMany;

    public function languages(): BelongsToMany;

    public function locales(): BelongsToMany;

    public function createdLocales(): HasMany;

    public function choiceLists(): BelongsToMany;

    public function choiceListEntries(): HasMany;

    public function choiceListEntriesRemovedFromContext(): BelongsToMany;

    public function markLookupListAsComplete(ChoiceList $choiceList): ?bool;

    public function markLookupListAsInComplete(ChoiceList $choiceList): ?bool;

    public function hasCompletedLookupList(ChoiceList $choiceList): ?bool;

    public function country(): BelongsTo;

    public function xlsformModuleVersions(): HasMany;


    //******** WITH XLSFORM TEMPLATES TOO

    public function xlsformTemplates(): MorphMany;

    public function odkProject(): MorphOne;

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void;

}
