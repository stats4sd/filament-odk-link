<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class OdkProject extends Model
{
    public $incrementing = false;

    public $keyType = 'integer';

    protected $table = 'odk_projects';

    protected $appends = [
        'odk_url',
    ];

    /** @return BelongsTo<HasXlsforms, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.team_model'), 'owner_id');
    }

    /** @return HasMany<AppUser, $this> */
    public function appUsers(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    // add this method because it will be called when xlsform->toArray() is called

    /** @return Attribute<string, never> */
    protected function odkUrl(): Attribute
    {
        return new Attribute(
            get: fn(): string => config('filament-odk-link.odk.url') . '/#/projects/' . $this->id,
        );
    }

    // TODO: is this redundant? It's certainly not normalised SQL, as in theory we can get to Xlsforms via the owner, but we don't know the model type of the owner, so it's easier to add odk_project_id to the xlsforms table and add this relationship.

    /** @return HasMany<Xlsform, $this> */
    public function xlsforms(): HasMany
    {
        return $this->hasMany(Xlsform::class);
    }
}
