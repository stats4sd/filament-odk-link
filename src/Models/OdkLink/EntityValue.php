<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityValue extends Model
{

    protected $table = 'datasets';
    protected $guarded = [];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function odkProject(): BelongsTo
    {
        return $this->belongsTo(OdkProject::class);
    }
}
