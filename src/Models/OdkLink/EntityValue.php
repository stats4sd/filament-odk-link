<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EntityValue extends Model
{
    protected $table = 'entity_values';

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<OdkProject, $this> */
    public function odkProject(): BelongsTo
    {
        return $this->belongsTo(OdkProject::class);
    }

    /** @return BelongsTo<DatasetVariable, $this> */
    public function datasetVariable(): BelongsTo
    {
        return $this->belongsTo(DatasetVariable::class);
    }
}
