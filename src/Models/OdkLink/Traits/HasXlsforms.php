<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\ChoiceListEntryRemoved;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageOwner;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LocaleOwner;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

trait HasXlsforms
{
    /** @throws BindingResolutionException */
    protected static function booted(): void
    {
        parent::booted();

        // check if we are in local-only (no-ODK link) mode
        if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') == '') {
            return;
        }

        $odkLinkService = app()->make(OdkLinkService::class);

        // when the model is created; automatically create an associated project on ODK Central;
        static::created(static function (self $owner) use ($odkLinkService) {

            // check if we are in local-only (no-ODK link) mode
            if (!config('filament-odk-link.odk.url')) {
                return;
            }

            $owner->createLinkedOdkProject($odkLinkService, $owner);
        });
    }

    // Used as the human-readable label for the owners of forms. Uses the same variable name that some Laravel Backpack fields expect (e.g. Relationship)
    // Xls Form titles are in the format `$owner->$nameAttribute . '-' . $xlsform->title`
    public string $identifiableAttribute = 'name';

    /** @return HasMany<Xlsform, $this> */
    public function xlsforms(): HasMany
    {
        return $this->HasMany(Xlsform::class, 'owner_id');
    }

    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    // This is still a morph relationship because XlsformTemplates might be owned by multiple types of entity.
    /** @return MorphMany<XlsformTemplate, $this> */
    public function xlsformTemplates(): MorphMany
    {
        return $this->morphMany(XlsformTemplate::class, 'owner');
    }

    // ODK projects might be owned by 'xlsform owners', or the platform itself.
    /** @return MorphOne<OdkProject, $this> */
    public function odkProject(): MorphOne
    {
        return $this->morphOne(OdkProject::class, 'owner');
    }

    public function createLinkedOdkProject(OdkLinkService $odkLinkService): void
    {
        $odkProjectInfo = $odkLinkService->createProject($this->name);
        $odkProject = $this->odkProject()->create([
            'id' => $odkProjectInfo['id'],
            'name' => $odkProjectInfo['name'],
            'archived' => $odkProjectInfo['archived'],
        ]);

        // create an app user + assign to all forms in the project by giving them the admin role;
        $odkAppUserInfo = $odkLinkService->createProjectAppUser($odkProject);

        $odkProject->appUsers()->create([
            'id' => $odkAppUserInfo['id'],
            'display_name' => $odkAppUserInfo['displayName'],
            'type' => 'field_key', // legacy term for "App User" in ODK Central;
            'token' => $odkAppUserInfo['token'], // the token required to generate the ODK QR Code;
            'can_access_all_forms' => true,
        ]);
    }

    /** @return Attribute<string, never> */
    protected function odkQrCode(): Attribute
    {
        return new Attribute(
            get: function (): ?string {

                if (!$this->odkProject?->appUsers->first()) {
                    return null;
                }

                return $this->odkProject->appUsers->first()->qr_code_string;
            }
        );
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
        return $this->belongsToMany(ChoiceListEntry::class, 'choice_list_owner', 'owner_id', 'choice_list_id')
            ->withPivot(['is_complete']);
    }

    // Localised choice list entries
    /** @return HasMany<ChoiceListEntry, $this> */
    public function choiceListEntries(): HasMany
    {
        return $this->belongsToMany(ChoiceListEntry::class, 'owner_id');
    }

    /** @return BelongsToMany<ChoiceListEntry, $this> */
    public function choiceListEntriesRemovedFromContext(): BelongsToMany
    {
        return $this->belongsToMany(ChoiceListEntry::class, 'choice_list_entries_removed_owner', 'owner_id', 'choice_list_entry_id');
    }

    /** @return ?bool */
    public function markLookupListAsComplete(ChoiceList $choiceList): ?bool
    {
        $this->choiceLists()->sync([$choiceList->id => ['is_complete' => 1]], detaching: false);

        return $this->hasCompletedLookupList($choiceList);
    }

    /** @return ?bool */
    public function markLookupListAsInComplete(ChoiceList $choiceList): ?bool
    {
        $this->choiceLists()->detach($choiceList->id);

        return $this->hasCompletedLookupList($choiceList);
    }

    /** @return ?bool */
    public function hasCompletedLookupList(ChoiceList $choiceList): ?bool
    {
        return $this->choiceLists()->where('choice_lists.id', $choiceList->id)->first()?->pivot->is_complete;
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'owner_id');
    }
}
