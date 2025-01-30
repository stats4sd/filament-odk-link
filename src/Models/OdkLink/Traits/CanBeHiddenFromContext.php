<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\FilamentOdkLink\Models\ChoiceListEntryRemoved;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;

// Trait to use on LookupEntry models when the user can remove 'global' entries from their own context. E.g. For Crops - a user can remove a crop from their own context, and so it will not show in the shortened list of crops in the ODK form. but will appear in the full list if the enumerator selects "other"...
trait CanBeHiddenFromContext
{
    public function canBeHiddenFromContext(): bool
    {
        return $this->choiceList->can_be_hidden_from_context;
    }

    /** @return HasMany<ChoiceListEntryRemoved, $this> */
    public function choiceListEntriesRemoved(): HasMany
    {
        return $this->hasMany(ChoiceListEntryRemoved::class);
    }

    public function isRemoved(WithXlsforms $team): bool
    {
        return $this->choiceListEntriesRemoved()->where('owner_id', $team->getKey())->where('owner_type', get_class($team))->exists();
    }

    public function toggleRemoved(WithXlsforms $team): void
    {
        if ($this->isRemoved($team)) {
            $team->choiceListEntriesRemoved()->create(['choice_list_entry_id' => $this->id]);
        } else {
            $team->choiceListEntriesRemoved()->where('choice_list_entry_id', $this->id)->delete();
        }
    }
}
