<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class XlsformTemplateSection extends Pivot
{
    protected $table = 'xlsform_template_sections';

    protected $guarded = [];

    protected $casts = [
        'schema' => 'collection',
    ];

    protected static function booted()
    {
        // always sort by is_repeat, then by id
        static::addGlobalScope('sort', function ($query) {
            $query->orderBy('is_repeat', 'asc')->orderBy('id', 'asc');
        });
    }

    public function xlsformTemplate(): BelongsTo
    {
        return $this->belongsTo(XlsformTemplate::class);
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
