<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Hoa\Compiler\Llk\Rule\Choice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use ShiftOneLabs\LaravelCascadeDeletes\CascadesDeletes;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\HasLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class SurveyRow extends Model implements HasLanguageStrings
{
    use CascadesDeletes;

    protected static function booted(): void
    {
        static::deleting(function ($surveyRow) {
            $surveyRow->languageStrings()->delete();
        });
    }

    protected array $cascadeDeletes = ['languageStrings'];

    protected $casts = [
        'properties' => 'collection',
        'required' => 'boolean',
        'updated_during_import' => 'boolean',
    ];

    /** @return BelongsTo<XlsformModuleVersion, $this> */
    public function xlsformModuleVersion(): BelongsTo
    {
        return $this->belongsTo(XlsformModuleVersion::class);
    }

    /** @return MorphMany<LanguageString, $this> */
    public function languageStrings(): MorphMany
    {
        return $this->morphMany(LanguageString::class, 'linked_entry');
    }

    /** @return MorphOne<LanguageString, $this> */
    public function defaultLabel(): MorphOne
    {
        return $this->morphOne(LanguageString::class, 'linked_entry')
            ->whereHas('language', fn($query) => $query->where('languages.iso_alpha2', 'en'))
            ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', 'label'));
    }

    /** @return MorphOne<LanguageString, $this> */
    public function defaultHint(): MorphOne
    {
        return $this->morphOne(LanguageString::class, 'linked_entry')
            ->whereHas('language', fn($query) => $query->where('languages.iso_alpha2', 'en'))
            ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', 'hint'));
    }

    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
    }

    public function getLanguageString(string $type, Locale $locale): ?string
    {
        return $this->languageStrings()
            ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', $type))
            ->whereHas('locale', fn(Builder $query) => $query->where('locales.id', $locale->id))
            ->first()?->text;
    }

    public function expandMediaColumnHeaders(string $type)
    {

        // fix for mediaimage needing to be media::image, etc.
        if ($type === 'mediaimage') {
            return 'media::image';
        }

        if ($type === 'mediaaudio') {
            return 'media::audio';
        }

        if ($type === 'mediavideo') {
            return 'media::video';
        }

        return $type;
    }
}
