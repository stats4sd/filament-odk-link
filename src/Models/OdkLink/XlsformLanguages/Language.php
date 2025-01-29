<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use App\Models\Reference\Country;
use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Language extends Model
{

    public function xlsformTemplateLanguages(): HasMany
    {
        return $this->hasMany(XlsformModuleVersionLocale::class);
    }

    public function locales(): HasMany
    {
        return $this->hasMany(Locale::class);
    }

    // Does this work?
    public function defaultLocale(): HasOne
    {
        return $this->hasOne(Locale::class)->where('is_default', true);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_language', 'language_id', 'country_id');
    }

    public function languageLabel(): Attribute
    {
        return new Attribute(
            get: fn() => "{$this->name} ({$this->iso_alpha2})",
        );
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('locale_id');
    }
}
