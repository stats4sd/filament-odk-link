<?php

/** @noinspection PhpStanGlobal */

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

/**
 * @phpstan-require-extends Model
 */
trait HasXlsforms
{
    use HasXlsformTemplates;

    // Used as the human-readable label for the owners of forms. Uses the same variable name that some Laravel Backpack fields expect (e.g. Relationship)
    // Xls Form titles are in the format `$owner->$nameAttribute . '-' . $xlsform->title`
    public string $identifiableAttribute = 'name';

    // Default 'identifier' is name; this can be overwritten in the main app:
    public function getName(): string
    {
        return $this->name;
    }

    /** @return HasMany<Dataset, $this> */
    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class, 'owner_id');
    }

    // ODK projects might be owned by 'xlsform owners', or the platform itself.
    /** @return MorphOne<OdkProject, $this> */
    public function odkProject(): MorphOne
    {
        return $this->morphOne(OdkProject::class, 'owner');
    }

    /** @return HasMany<Xlsform, $this> */
    public function xlsforms(): HasMany
    {
        return $this->hasMany(Xlsform::class, 'owner_id');
    }

    /** @return BelongsToMany<Language, $this> */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'language_owner', 'owner_id', 'language_id')
            ->withPivot(['locale_id']);
    }

    // Use the same pivot table as language...
    /** @return BelongsToMany<Locale, $this> */
    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'language_owner', 'owner_id', 'locale_id')
            ->withPivot(['language_id']);
    }

    /** @return HasMany<Locale, $this> */
    public function createdLocales(): HasMany
    {
        return $this->hasMany(Locale::class, 'creator_id');
    }

    // For tracking completion status of choice lists by team
    /** @return BelongsToMany<ChoiceList, $this> */
    public function choiceLists(): BelongsToMany
    {
        return $this->belongsToMany(ChoiceList::class, 'choice_list_owner', 'owner_id', 'choice_list_id')
            ->withPivot(['is_complete']);
    }

    // Localised choice list entries
    /** @return HasMany<ChoiceListEntry, $this> */
    public function choiceListEntries(): HasMany
    {
        return $this->hasMany(ChoiceListEntry::class, 'owner_id');
    }

    /** @return BelongsToMany<ChoiceListEntry, $this> */
    public function choiceListEntriesRemovedFromContext(): BelongsToMany
    {
        return $this->belongsToMany(ChoiceListEntry::class, 'choice_list_entries_removed_owner', 'owner_id', 'choice_list_entry_id');
    }

    public function markLookupListAsComplete(ChoiceList $choiceList): ?bool
    {
        $this->choiceLists()->sync([$choiceList->id => ['is_complete' => 1]], detaching: false);

        return $this->hasCompletedLookupList($choiceList);
    }

    public function markLookupListAsInComplete(ChoiceList $choiceList): ?bool
    {
        $this->choiceLists()->detach($choiceList->id);

        return $this->hasCompletedLookupList($choiceList);
    }

    public function hasCompletedLookupList(ChoiceList $choiceList): ?bool
    {
        return $this->choiceLists()->where('choice_lists.id', $choiceList->id)->first()?->pivot->is_complete;
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'owner_id');
    }

    /** @return HasMany<XlsformModuleVersion, $this> */
    public function xlsformModuleVersions(): HasMany
    {
        return $this->hasMany(XlsformModuleVersion::class, 'owner_id');
    }
}
