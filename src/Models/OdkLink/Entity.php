<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Entity extends Model
{
    protected $table = 'entities';

    // e.g. for an entity created from a repeat group item, the parent entity will be the entity created from the repeat group's parent (the main form or, if it's a nested repeat group, the parent group).
    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return MorphTo */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<EntityValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(EntityValue::class, 'entity_id');
    }

    /** @return BelongsToMany<DatasetVariable, $this> */
    public function datasetVariables(): BelongsToMany
    {
        return $this->belongsToMany(DatasetVariable::class, 'entity_values')
            ->using(EntityValue::class)
            ->withPivot('value');
    }

    // Dan: comment getAttribute() function temporary to avoid throwing error when adding polymorphic relationship

    // /*
    //  * Override getAttribute() to check the entity_values table first.
    //  */
    // public function getAttribute($key)
    // {

    //     // if the default getAttribute() returns something, great! Do that
    //     if ($value = parent::getAttribute($key)) {
    //         return $value;
    //     }

    //     /*
    //      * If the requested attribute is in the dataset variables list, check the values() relationship
    //      */
    //     if ($this->getVariableList()->contains($key)) {
    //         return $this->values()->whereHas('datasetVariable', function (Builder $query) use ($key) {
    //             $query->where('dataset_variables.name', $key);
    //         })->first()?->value;
    //     }

    //     /*
    //      * Otherwise, attempt to defer to the linked model:
    //      */
    //     return $this->model->getAttribute($key);

    // }

}
