<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class Language extends Model
{
    /** @return HasMany<XlsformModuleVersionLocale, $this> */
    public function xlsformModuleVersionLocales(): HasMany
    {
        return $this->hasMany(XlsformModuleVersionLocale::class);
    }

    /** @return HasMany<Locale, $this> */
    public function locales(): HasMany
    {
        return $this->hasMany(Locale::class);
    }

    /** @return HasOne<Locale, $this> */
    public function defaultLocale(): HasOne
    {
        return $this->hasOne(Locale::class)->where('is_default', true);
    }

    // TODO: fix country!!

    /** @return BelongsToMany<Country, $this> */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_language', 'language_id', 'country_id');
    }

    /** @return Attribute<string, never> */
    public function languageLabel(): Attribute
    {
        return new Attribute(
            get: fn () => "{$this->name} ({$this->iso_alpha2})",
        );
    }

    /** @return BelongsToMany<Model, $this> */
    public function owners(): BelongsToMany
    {
        return $this->BelongsToMany
        (config('filament-odk-link.models.team_model'), 'language_owner', 'language_id', 'owner_id')
            ->withPivot(['locale_id']);
    }
}
