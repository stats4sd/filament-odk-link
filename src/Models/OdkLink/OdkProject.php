<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class OdkProject extends Model
{
    public $incrementing = false;

    public $keyType = 'integer';

    protected $table = 'odk_projects';

    protected $appends = [
        'odk_url',
    ];

    protected $guarded = [];

     /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<AppUser, $this> */
    public function appUsers(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    /** @return Attribute<string, never> */
    protected function odkUrl(): Attribute
    {
        return new Attribute(
            get: fn(): string => config('filament-odk-link.odk.url') . '/#/projects/' . $this->id,
        );
    }
}
