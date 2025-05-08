<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait IsLookupList
{
    // is the current entry a 'global' entry?
    /** @return Attribute<bool, never> */
    protected function isGlobalEntry(): Attribute
    {
        return new Attribute(
            get: fn(): bool => $this->isGLobal(),
        );
    }

    protected function isGlobal(): bool
    {
        return $this->owner_id === null;
    }

    /** @return Attribute<bool, never> */
    protected function isCustomisedEntry(): Attribute
    {
        return new Attribute(
            get: fn(): bool => $this->isCustomised(),
        );
    }

    protected function isCustomised(): bool
    {
        return $this->owner_id !== null;
    }


    /** @return BelongsTo<HasXlsforms, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.team_model'), 'owner_id');
    }

}
