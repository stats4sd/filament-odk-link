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
    protected $keyType = 'string';
    public $incrementing = false;


    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_values')
            ->using(EntityValue::class)
            ->withPivot('value');
    }

    public function values(): HasMany
    {
        return $this->hasMany(EntityValue::class, 'entity_id');
    }

}
