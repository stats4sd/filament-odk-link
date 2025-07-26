<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ParentDatasetPivot extends Pivot
{
    protected $table = 'dataset_parents';

    public $timestamps = true;

    /** @return BelongsTo<Dataset, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Dataset::class, 'parent_id');
    }

    /** @return BelongsTo<Dataset, $this> */
    public function child()
    {
        return $this->belongsTo(Dataset::class, 'child_id');
    }

    /** @return BelongsTo<DatasetVariable, $this> */
    public function foreignKeyVariable(): BelongsTo
    {
        return $this->belongsTo(DatasetVariable::class, 'foreign_key_variable_id');
    }
}
