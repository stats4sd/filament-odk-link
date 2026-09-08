<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdkDataset extends Model
{
    protected $table = 'odk_datasets';

    protected $guarded = [];

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

    /** @return BelongsTo<Model, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.form_owner'), 'owner_id');
    }
}
