<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasLanguageStrings
{
    public function languageStrings(): MorphMany;
}
