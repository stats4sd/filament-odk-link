<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatasetVariable extends Model
{
    protected $table = 'dataset_variables';

    protected $guarded = [];

    protected $primaryKey = 'id';

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return HasOne<DatasetVariable, $this> */
    public function datasetParentPivot(): HasOne
    {
        return $this->hasOne(ParentDatasetPivot::class, 'foreign_key_variable_id');
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
        return $this->hasMany(EntityValue::class, 'dataset_variable_name', 'name');
    }
}
