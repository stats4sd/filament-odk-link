<?php

namespace Stats4sd\FilamentOdkLink\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property bool $should_receive_all_xlsform_templates
 * @property string $name
 * @property Collection<int, Xlsform> $xlsforms
 * @property Collection<int, Language> $languages
 * @property Collection<int, Locale> $locales
 * @property Collection<int, Locale> $createdLocales
 * @property Collection<int, ChoiceList> $choiceLists
 * @property Collection<int, ChoiceListEntry> $choiceListEntries
 * @property Collection<int, ChoiceListEntry> $choiceListEntriesRemovedFromContext
 * @property Country $country
 * @property Collection<int, XlsformModuleVersion> $xlsformModuleVersions
 * @property Collection<int, XlsformTemplate> $xlsformTemplates
 * @property OdkProject $odkProject
 */
interface FormOwner
{
    public function getName(): string;

    public function xlsforms(): HasMany;

    public function datasets(): HasMany;

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

    // ******** WITH XLSFORM TEMPLATES TOO

    public function xlsformTemplates(): MorphMany;

    public function odkProject(): MorphOne;

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void;
}
