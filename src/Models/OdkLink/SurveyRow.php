<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Hoa\Compiler\Llk\Rule\Choice;
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

class SurveyRow extends Model implements HasLanguageStrings
{
    use CascadesDeletes;

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

    /** @return Attribute<string, never> */
    public function defaultHint(): Attribute
    {
        return new Attribute(
            get: fn() => $this->languageStrings()
                ->whereHas('language', fn($query) => $query->where('languages.iso_alpha2', 'en'))
                ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', 'hint'))
                ->first()?->text
        );
    }

    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
    }


    //* *************** FOR EXPORT TO XLSFORM FILE *****************

    public function generateXlsformRow(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            ...$this->getLanguageStrings('label'),
            ...$this->getLanguageStrings('hint'),
            'required' => $this->required,
            ...$this->getLanguageStrings('required_message'),
            'calculation' => $this->calculation,
            'relevant' => $this->relevant,
            ...$this->getLanguageStrings('relevant_message'),
            'appearance' => $this->appearance,
            'constraint' => $this->constraint,
            ...$this->getLanguageStrings('constraint_message'),
            'choice_filter' => $this->choice_filter,
            'repeat_count' => $this->repeat_count,
            ...$this->getLanguageStrings('mediaimage'),
            'default' => $this->default,
            ...$this->properties->toArray(), // includes media items that are not per-language (e.g. "media::image")
        ];
    }

    public function getLanguageStrings(string $type): array
    {
        return $this->languageStrings()
            ->whereHas('languageStringType', fn($query) => $query->where('language_string_types.name', $type))
            ->get()
            ->mapWithKeys(function ($languageString) {


                $key = $this->expandMediaColumnHeaders($languageString->languageStringType->name);
                $key = "$key::{$languageString->language->name} ({$languageString->language->iso_alpha2})";

                $value = $languageString->text;

                return [$key => $value];

            })->toArray();

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
