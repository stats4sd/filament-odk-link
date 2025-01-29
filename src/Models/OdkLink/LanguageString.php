<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Znck\Eloquent\Relations\BelongsToThrough;

class LanguageString extends Model
{



    use \Znck\Eloquent\Traits\BelongsToThrough;

    protected $casts = [
        'updated_during_import' => 'boolean',
    ];

    // A language string is linked to either a SurveyRow or a ChoiceListEntry;
    public function linkedEntry(): MorphTo
    {
        return $this->morphTo('linked_entry');
    }

    public function languageStringType(): BelongsTo
    {
        return $this->belongsTo(LanguageStringType::class);
    }

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }

    public function language(): BelongsToThrough
    {
        return $this->belongsToThrough(Language::class, Locale::class);
    }
}
