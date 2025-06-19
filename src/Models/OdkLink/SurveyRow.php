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
use Illuminate\Support\Str;
use ShiftOneLabs\LaravelCascadeDeletes\CascadesDeletes;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;

class SurveyRow extends Model implements WithLanguageStrings
{
    use CascadesDeletes;
    use HasLanguageStrings;

    protected static function booted(): void
    {

        // When a survey row is changed, we need to recompile any draft xlsforms that include it.
        static::saved(function (self $surveyRow) {
           $surveyRow->xlsformModuleVersion->xlsforms()->update([
              'draft_needs_update' => true,
           ]);
        });

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

    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
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
