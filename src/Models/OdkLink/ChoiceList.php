<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;


class ChoiceList extends Model
{

    protected $casts = [
        'properties' => 'collection',
        'can_be_hidden_from_context' => 'boolean',
        'is_localisable' => 'boolean',
    ];

    protected static function booted()
    {
        static::deleting(function (ChoiceList $choiceList) {
            $choiceList->choiceListEntries->each(function (ChoiceListEntry $choiceListEntry) {
                $choiceListEntry->delete();
            });
        });
    }

    //get entries for a specific owner
    /** @return Collection<ChoiceListEntry> */
    public function getOwnedEntries(WithXlsforms $owner): Collection
    {

        return $this->choiceListEntries()
            ->where(function ($query) use ($owner) {
                $query->whereDoesntHave('owner')
                    ->orWhereHas('owner', function ($query) use ($owner) {
                        $query->where($owner->getTable() . '.' . $owner->getKeyName(), $owner->getKey());

                    });
            })
            ->get();
    }

    /** @return HasMany<ChoiceListEntry, $this> */
    public function choiceListEntries(): HasMany
    {
        return $this->hasMany(ChoiceListEntry::class);
    }

    /** @return BelongsTo<XlsformModuleVersion, $this> */
    public function xlsformModuleVersion(): BelongsTo
    {
        return $this->belongsTo(XlsformModuleVersion::class);
    }

    /** @return HasMany<SurveyRow, $this> */
    public function surveyRows(): HasMany
    {
        return $this->hasMany(SurveyRow::class);
    }

}
