<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdkDataset extends Model
{
    protected $table = 'datasets';

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @return BelongsTo<OdkProject, $this> */
    public function odkProject(): BelongsTo
    {
        return $this->belongsTo(OdkProject::class);
    }
}
