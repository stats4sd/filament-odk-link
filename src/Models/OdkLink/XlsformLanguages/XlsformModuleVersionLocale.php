<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Znck\Eloquent\Relations\BelongsToThrough;

class XlsformModuleVersionLocale extends Pivot
{
    use \Znck\Eloquent\Traits\BelongsToThrough;

    /** @return BelongsTo<XlsformModuleVersion, $this> */
    public function xlsformModule(): BelongsTo
    {
        return $this->belongsTo(XlsformModuleVersion::class);
    }

    /** @return BelongsTo<Locale, $this> */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }

    /** @return BelongsToThrough<Language, $this> */
    public function language(): BelongsToThrough
    {
        return $this->belongsToThrough(Language::class, Locale::class);
    }

    public function languageStrings(): HasMany
    {
        return $this->hasMany(LanguageString::class);
    }

    /** @return Attribute<string, never>  */
    protected function localeLanguageLabel(): Attribute
    {
        return new Attribute(
            get: fn () => $this->locale->languageLabel,
        );
    }

    // was this created from importing a Xlsform template file?
    // if false, then this it was created through the platform as an extra translation
    /** @return Attribute<bool, never>  */
    protected function isAddedFromXlsformTemplate(): Attribute
    {
        return new Attribute(
            get: fn (): bool => $this->locale->is_default,
        );
    }

    /** @return Attribute<string, never> */
    protected function status(): Attribute
    {
        return new Attribute(
            get: function (): string {

                if ($this->has_language_strings && ! $this->needs_update) {
                    return 'Ready for use';
                } elseif (! $this->has_language_strings) {
                    return 'Not added';
                } elseif ($this->has_language_strings && $this->needs_update) {
                    return 'Out of date';
                }

                return 'Unknown';
            }
        );
    }
}
