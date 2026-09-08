<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use ShiftOneLabs\LaravelCascadeDeletes\CascadesDeletes;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasLanguageStrings;

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

    protected $guarded = [];

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

    // Return full 'type'; including correct list_name for selects
    /** @return Attribute<string, never> */
    protected function typeAndChoiceList(): Attribute
    {
        return new Attribute(
            get: function () {
                if (Str::contains($this->type, 'select')) {
                    $listName = $this->choiceList?->list_name;

                    // TODO: update items linked to Dataset instead of ChoiceList
                    // HACK: workaround is to ignore this if there is no linked list
                    if ($listName) {
                        return Str::before($this->type, ' ').' '.$listName;
                    }
                }

                return $this->type;
            }
        );
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
