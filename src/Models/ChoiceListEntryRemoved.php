<?php

namespace Stats4sd\FilamentOdkLink\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;

class ChoiceListEntryRemoved extends Model
{
    /** @return BelongsTo<ChoiceListEntry, $this> */
    public function choiceListEntry(): BelongsTo
    {
        return $this->belongsTo(ChoiceListEntry::class);
    }

    /** @return MorphTo */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
