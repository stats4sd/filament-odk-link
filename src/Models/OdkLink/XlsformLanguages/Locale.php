<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Services\HelperService;

class Locale extends Model
{
    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'language_label',
    ];

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
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

    public function creator(): MorphTo
    {
        return $this->morphTo('creator');
    }

    // TODO: fix to use polymorphic relationship.
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'language_team', 'locale_id', 'team_id');
    }

    /** @return Attribute<string, never> */
    protected function languageLabel(): Attribute
    {
        return new Attribute(
            get: fn () => $this->description ?? $this->language->name . ' (default)',
        );
    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {

        return new Attribute(
            get: function () {

                $owner = HelperService::getCurrentOwner();
                $xlsforms = collect();
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

                if ($moduleVersions->count() === 0) {
                    return 'Not uploaded';
                }

                if ($moduleVersions->count() < $allModuleVersions->count()) {
                    return 'Translations incomplete';
                }

                if ($moduleVersions->every(fn ($moduleVersion) => ! $moduleVersion->pivot->needs_update && $moduleVersion->pivot->has_language_strings)) {
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
            get: fn () => $this->language->name . ' (' . $this->language->iso_alpha2 . ')',
        );
    }

    /** @return Attribute<bool, never> */
    protected function isEditable(): Attribute
    {
        return new Attribute(
            get: fn () => $this->createdBy?->id === HelperService::getCurrentOwner()->id,
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
}
