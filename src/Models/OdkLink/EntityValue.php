<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EntityValue extends Model
{
    protected $table = 'entity_values';

    protected $guarded = [];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function odkProject(): BelongsTo
    {
        return $this->belongsTo(OdkProject::class);
    }

    public function translation(): HasOne
    {
        return $this->hasOne(Translation::class);
    }


}
