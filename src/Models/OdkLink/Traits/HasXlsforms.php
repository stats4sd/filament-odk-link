<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Stats4sd\FilamentOdkLink\Models\ChoiceListEntryRemoved;
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
        static::created(static function ($owner) use ($odkLinkService) {

            // check if we are in local-only (no-ODK link) mode
            if (! config('filament-odk-link.odk.url')) {
                return;
            }

            $owner->createLinkedOdkProject($odkLinkService, $owner);
        });
    }

    // Used as the human-readable label for the owners of forms. Uses the same variable name that some Laravel Backpack fields expect (e.g. Relationship)
    // Xls Form titles are in the format `$owner->$nameAttribute . '-' . $xlsform->title`
    public string $identifiableAttribute = 'name';

    public function xlsforms(): MorphMany
    {
        return $this->morphMany(Xlsform::class, 'owner');
    }

    // Private templates are owned by a single form owner.
    // All owners have access to all public templates (templates where available = 1)
    /** @return MorphMany<XlsformTemplate, $this> */
    public function xlsformTemplates(): MorphMany
    {
        return $this->morphMany(XlsformTemplate::class, 'owner');
    }

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

                if (! $this->odkProject?->appUsers->first()) {
                    return null;
                }

                return $this->odkProject->appUsers->first()->qr_code_string;
            }
        );
    }

    /** @return MorphMany<LanguageOwner, $this> */
    public function languagesOwned(): MorphMany
    {
        return $this->morphMany(LanguageOwner::class, 'owner');
    }

    /** @return MorphMany<LocaleOwner, $this> */
    public function localesOwned(): MorphMany
    {
        return $this->morphMany(LocaleOwner::class, 'owner');
    }

    /** @return HasManyThrough<Locale, LocaleOwner, $this> */
    public function locales(): HasManyThrough
    {
        return $this->hasManyThrough(Locale::class, LocaleOwner::class);
    }

    /** @return HasManyThrough<Language, LanguageOwner, $this> */
    public function languages(): HasManyThrough
    {
        return $this->hasManyThrough(Language::class, LanguageOwner::class);
    }

    /** @return MorphMany<ChoiceListEntryRemoved, $this> */
    public function choiceListEntriesRemoved(): MorphMany
    {
        return $this->morphMany(ChoiceListEntryRemoved::class, 'owner');
    }

    /** @return HasManyThrough<ChoiceListEntry, ChoiceListEntryRemoved, $this> */
    public function choiceListEntriesRemovedFromContext(): HasManyThrough
    {
        return $this->hasManyThrough(ChoiceListEntry::class, ChoiceListEntryRemoved::class);
    }
}
