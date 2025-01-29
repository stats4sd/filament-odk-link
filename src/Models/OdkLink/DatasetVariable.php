<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetVariable extends Model
{
    protected $table = 'dataset_variables';

    protected $primaryKey = 'id';

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return BelongsToMany<Entity, $this> */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_values')
            ->using(EntityValue::class)
            ->withPivot('value');
    }

    /** @return HasMany<EntityValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(EntityValue::class, 'entity_id');
    }
}
