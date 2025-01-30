<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;

// Trait to use on LookupEntry models when the user can remove 'global' entries from their own context. E.g. For Crops - a user can remove a crop from their own context, and so it will not show in the shortened list of crops in the ODK form. but will appear in the full list if the enumerator selects "other"...
trait CanBeHiddenFromContext
{
    public function canBeHiddenFromContext(): bool
    {
        return $this->choiceList->can_be_hidden_from_context;
    }

    // TODO: fix this to use polymorphic 'owner' instead of team reference.
    public function teamRemoved(): BelongsToMany
    {
        return $this->BelongsToMany(Team::class, 'choice_list_entries_removed');
    }

    public function isRemoved(WithXlsforms $team): bool
    {
        return $this->teamRemoved->contains($team);
    }

    public function toggleRemoved(WithXlsforms $team): array
    {
        return $this->teamRemoved()->toggle([$team->getKey()]);
    }
}
