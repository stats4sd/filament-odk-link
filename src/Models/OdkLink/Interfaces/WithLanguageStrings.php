<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

interface WithLanguageStrings
{
    public function languageStrings(): MorphMany;

    public function getLanguageString(string $type, Locale $locale): ?string;
}
