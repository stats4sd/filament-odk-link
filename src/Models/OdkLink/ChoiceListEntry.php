<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\HasLanguageStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\CanBeHiddenFromContext;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\IsLookupList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Znck\Eloquent\Relations\BelongsToThrough;

class ChoiceListEntry extends Model implements HasLanguageStrings
{
    use CanBeHiddenFromContext;
    use IsLookupList;
    use \Znck\Eloquent\Traits\BelongsToThrough;

    protected $casts = [
        'is_localisable' => 'boolean',
        'is_dataset' => 'boolean',
        'properties' => 'collection',
        'updated_during_import' => 'boolean',
    ];

    protected $appends = ['label_array'];

    protected static function booted(): void
    {
        // When Filament has tenancy enabled, we want to scope the choice list entries to the current tenant.
        static::addGlobalScope('owner', function (Builder $query) {

            if ($owner = HelperService::getCurrentOwner()) {

                $query->where('owner_id', '*', function (Builder $query) use ($owner) {
                    $query->where('id', $owner->getKey());
                })
                    ->orWhereNull('owner_id');
            }
        });
    }

    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
    }

    /** @return BelongsToThrough<XlsformModuleVersion, $this> */
    public function xlsformModuleVersion(): BelongsToThrough
    {
        return $this->belongsToThrough(XlsformModuleVersion::class, ChoiceList::class);
    }

    /** @return MorphMany<LanguageString, $this> */
    public function languageStrings(): MorphMany
    {
        return $this->morphMany(LanguageString::class, 'linked_entry');
    }

    // Some choice lists are linked to specific data models to let us add custom information.

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<HasXlsforms | null, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.team_model'), 'owner_id');
    }

    /** @return BelongsToMany<HasXlsforms, $this> */
    public function ownersWhoRemovedFromContext(): BelongsToMany
    {
        return $this->belongsToMany(config('filament-odk-link.models.team_model'), 'choice_list_entries_removed_owner', 'choice_list_entry_id', 'owner_id');
    }


}
