<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

/**
 * The form owner. This is the model `filament-odk-link.models.form_owner` points at,
 * and the tenant for the team panel.
 */
class Team extends Model implements WithXlsforms
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use HasXlsforms;

    protected $fillable = [
        'name',
    ];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
