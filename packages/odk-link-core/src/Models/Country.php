<?php

namespace Stats4sd\OdkLink\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\OdkLink\Core\Contracts\FormOwner;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\OdkLink\Core\Support\ConfiguredModels;
use Znck\Eloquent\Relations\BelongsToThrough;

class Country extends Model
{
    use \Znck\Eloquent\Traits\BelongsToThrough;

    protected $table = 'countries';

    protected $guarded = [];

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsToThrough<Continent, $this> */
    public function continent(): BelongsToThrough
    {
        return $this->belongsToThrough(Continent::class, Region::class);
    }

    /** @return BelongsTo<Model&FormOwner, $this> */
    public function owners(): BelongsTo
    {
        return $this->belongsTo(app(ConfiguredModels::class)->formOwnerClass(), 'owner_id');
    }

    public function xlsformModuleVersions(): HasMany
    {
        return $this->hasMany(XlsformModuleVersion::class);
    }
}
