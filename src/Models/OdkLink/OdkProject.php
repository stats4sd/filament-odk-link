<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OdkProject extends Model
{

    protected $guarded = [];
    public $incrementing = false;
    public $keyType = 'integer';
    protected $primaryKey = 'id';
    protected $table = 'odk_projects';
    protected $appends = [
        'odk_url',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo(); // can be linked to any Model with the HasXlsforms trait.
    }

    public function appUsers(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    // add this method because it will be called when xlsform->toArray() is called
    public function odkUrl(): Attribute
    {
        return new Attribute(
            get: fn(): ?string => config('odk-link.odk.url') . "/#/projects/" . $this->id,
        );
    }


    // TODO: is this redundant? It's certainly not normalised SQL, as in theory we can get to Xlsforms via the owner, but we don't know the model type of the owner, so it's easier to add odk_project_id to the xlsforms table and add this relationship.
    public function xlsforms(): HasMany
    {
        return $this->hasMany(Xlsform::class);
    }
}
