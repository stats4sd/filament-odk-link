<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Locale extends Model implements HasMedia
{
    use HasRelationships;
    use InteractsWithMedia;

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'language_label',
    ];

    protected static function booted(): void
    {
        static::created(function (self $locale) {

            // if the locale is default, check all teams to see if they are linked to the language and do not yet have a locale

            /** @var Collection<WithXlsforms> $owners */
            $owners = config('filament-odk-link.models.form_owner')::all();

            $owners->each(function (WithXlsforms $owner) use ($locale) {
                if (
                    // if the owner is linked to the language
                    $owner->languages->contains($locale->language) &&

                    // and the owner doesn't have a locale set for that language already
                    $owner->locales->filter(fn (Locale $l) => $l->language_id === $locale->language_id)->count() === 0
                ) {

                    // assign the new 'default' locale to that owner/language combo.
                    $owner->languages()->updateExistingPivot($locale->language_id, ['locale_id' => $locale->id]);
                }
            });

            // create link with all default modules (as they will all require language strings to consider the locale as "complete"
            $defaultModuleVersions = XlsformModuleVersion::where('is_default', true)->get()->pluck('id');

            $locale->xlsformModuleVersions()->sync($defaultModuleVersions);
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('xlsform_template_translation_files')
            ->useDisk(config('filament-odk-link.storage.xlsforms'));
    }

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function languageStrings(): HasMany
    {
        return $this->hasMany(LanguageString::class);
    }

    public function xlsformTemplates(): HasManyDeep
    {
        return $this->hasManyDeep(
            related: XlsformTemplate::class,
            through: ['xlsform_module_version_locale', XlsformModule::class]
        );
    }

    /** @return HasMany<XlsformModuleVersionLocale, $this> */
    public function xlsformModuleVersionLocales(): HasMany
    {
        return $this->hasMany(XlsformModuleVersionLocale::class);
    }

    /** @return BelongsToMany<XlsformModuleVersion, $this> */
    public function xlsformModuleVersions(): BelongsToMany
    {
        return $this->belongsToMany(XlsformModuleVersion::class, 'xlsform_module_version_locale', 'locale_id', 'xlsform_module_version_id')
            ->using(XlsformModuleVersionLocale::class)
            ->withPivot(['needs_update']);
    }

    /** @return BelongsToMany<Xlsform, $this> */
    public function xlsforms(): BelongsToMany
    {
        return $this->belongsToMany(Xlsform::class);
    }

    /** @return BelongsTo<Model, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.form_owner'), 'creator_id');
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(config('filament-odk-link.models.form_owner'), 'language_owner', 'locale_id', 'owner_id')
            ->withPivot(['language_id']);
    }

    /** @return Attribute<string, never> */
    protected function languageLabel(): Attribute
    {
        return new Attribute(
            get: fn () => $this->is_default ? $this->language->name . ' (default)' : $this->description,
        );
    }

    public function getStatusForFormTemplate(XlsformTemplate $xlsformTemplate): bool
    {
        $moduleVersions = $xlsformTemplate->defaultXlsformModuleVersions;

        return $this->checkModuleCompleteness($moduleVersions);
    }

    public function getStatusForForm(Xlsform $xlsform): bool
    {
        $moduleVersions = $xlsform->xlsformModuleVersions;

        return $this->checkModuleCompleteness($moduleVersions);

    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {

        return new Attribute(
            get: function () {

                if ($this->processing_count > 0) {
                    return 'Processing';
                }

                $owner = HelperService::getCurrentOwner();

                if ($owner) {
                    $xlsforms = $owner->xlsforms;
                } else {
                    $xlsforms = Xlsform::all();
                }

                $moduleVersions = $this->xlsformModuleVersions;
                $allModuleVersions = $xlsforms
                    ->map(
                        fn (Xlsform $xlsform) => $xlsform
                            ->xlsformTemplate
                            ->xlsformModules
                            ->map(fn (XlsformModule $xlsformModule) => $xlsformModule->defaultXlsformVersion)
                    )->flatten();

                if ($this->is_default) {
                    return 'Ready for use';
                }

                // If the media item is not default (i.e. with strings from the original XlsformTemplate) and there are no uploaded files, mark as not-uploaded
                if (! $this->hasMedia('xlsform_template_translation_files') && ! $this->is_default) {
                    return 'Not uploaded';
                }

                // some media has been uploaded; but not for every xlsform template
                if ($this->media()->count() < $xlsforms->count()) {
                    return 'Translations incomplete';
                }

                // At least one module version "needs_update"
                /** @phpstan-ignore-next-line
                 * Ignoring because phpstan/larastan doesn't yet support easy handling of pivot values, and the workaround seem not worth it here.
                 * https://github.com/larastan/larastan/issues/1774
                 */
                if ($moduleVersions->some(fn ($moduleVersion) => $moduleVersion->pivot->needs_update)) {
                    return 'Needs updating';
                }

                return 'Ready for use';
            }
        );
    }

    /** @return Attribute<string, never> */
    protected function odkLabel(): Attribute
    {
        return new Attribute(
            get: fn () => $this->language->name . ' (' . $this->language->iso_alpha2 . ')',
        );
    }

    /** @return Attribute<bool, never> */
    protected function isEditable(): Attribute
    {
        return new Attribute(
            get: fn () => $this->creator?->getKey() === HelperService::getCurrentOwner()->getKey(),
        );
    }

    // Are translations for this locale being edited by the current team?

    /** @return Attribute<bool, never> */
    protected function isEditing(): Attribute
    {
        return new Attribute(
            get: fn () => $this->is_editable && $this->status !== 'Ready for use',
        );
    }

    private function checkModuleCompleteness(mixed $moduleVersions): mixed
    {
        return $moduleVersions->every(function (XlsformModuleVersion $version) {

            $linked = $version->locales->contains($this->id);

            $upToDate = $version->localeIsUpToDate($this);

            $hasLanguageStrings = $version->languageStrings->count() > 0;

            return $linked && $upToDate && $hasLanguageStrings;

        });
    }
}
