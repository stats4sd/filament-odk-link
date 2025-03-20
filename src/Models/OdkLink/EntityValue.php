<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EntityValue extends Model
{
    protected $table = 'entity_values';

    /** @return BelongsTo<DatasetVariable, $this> */
    public function datasetVariable(): BelongsTo
    {
        return $this->belongsTo(DatasetVariable::class, 'dataset_variable_name', 'name');
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /** @return BelongsTo<OdkProject, $this> */
    public function odkProject(): BelongsTo
    {
        return $this->belongsTo(OdkProject::class);
    }
}
