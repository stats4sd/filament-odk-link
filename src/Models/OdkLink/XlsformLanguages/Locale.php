<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class Locale extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'language_label',
    ];

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
            ->withPivot(['has_language_strings', 'needs_update']);
    }

    /** @return BelongsTo<HasXlsforms, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.team_model'), 'creator_id');
    }

    /** @return Attribute<string, never> */
    protected function languageLabel(): Attribute
    {
        return new Attribute(
            get: fn() => $this->is_default ? $this->language->name . ' (default)' : $this->description,
        );
    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {

        return new Attribute(
            get: function () {

                $owner = HelperService::getCurrentOwner();

                if ($owner) {
                    $xlsforms = $owner->xlsforms;
                } else {
                    $xlsforms = Xlsform::all();
                }

                $moduleVersions = $this->xlsformModuleVersions;
                $allModuleVersions = $xlsforms
                    ->map(
                        fn(Xlsform $xlsform) => $xlsform
                            ->xlsformTemplate
                            ->xlsformModules
                            ->map(fn(XlsformModule $xlsformModule) => $xlsformModule->defaultXlsformVersion)
                    )->flatten();

                if ($moduleVersions->count() === 0) {
                    return 'Not uploaded';
                }

                if ($moduleVersions->count() < $allModuleVersions->count()) {
                    return 'Translations incomplete';
                }

                /** @phpstan-ignore-next-line
                 * Ignoring because phpstan/larastan doesn't yet support easy handling of pivot values, and the workaround seem not worth it here.
                 * https://github.com/larastan/larastan/issues/1774
                 */
                if ($moduleVersions->every(fn($moduleVersion) => !$moduleVersion->pivot->needs_update && $moduleVersion->pivot->has_language_strings)) {
                    return 'Ready for use';
                }

                return 'Needs update';
            }
        );
    }

    /** @return Attribute<string, never> */
    protected function odkLabel(): Attribute
    {
        return new Attribute(
            get: fn() => $this->language->name . ' (' . $this->language->iso_alpha2 . ')',
        );
    }

    /** @return Attribute<bool, never> */
    protected function isEditable(): Attribute
    {
        return new Attribute(
            get: fn() => $this->creator?->getKey() === HelperService::getCurrentOwner()->getKey(),
        );
    }

    // Are translations for this locale being edited by the current team?

    /** @return Attribute<bool, never> */
    protected function isEditing(): Attribute
    {
        return new Attribute(
            get: fn() => $this->is_editable && $this->status !== 'Ready for use',
        );
    }
}
