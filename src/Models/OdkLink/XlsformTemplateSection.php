<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class XlsformTemplateSection extends Pivot
{
    protected $table = 'xlsform_template_sections';

    protected $casts = [
        'schema' => 'collection',
    ];

    protected static function booted(): void
    {
        // always sort by is_repeat, then by id
        static::addGlobalScope('sort', function ($query) {
            $query->orderBy('is_repeat', 'asc')->orderBy('id', 'asc');
        });
    }

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

    /** @return BelongsTo<XlsformTemplate, $this> */
    public function xlsformTemplate(): BelongsTo
    {
        return $this->belongsTo(XlsformTemplate::class);
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
