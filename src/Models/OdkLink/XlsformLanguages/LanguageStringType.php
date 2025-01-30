<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;

class LanguageStringType extends Model
{

    /** @return HasMany<LanguageString, $this> */
    public function languageStrings(): HasMany
    {
        return $this->hasMany(LanguageString::class);
    }
}
