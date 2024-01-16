<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetVariable extends Model
{
    protected $table = 'dataset_variables';



    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
