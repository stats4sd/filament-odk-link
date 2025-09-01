<?php

// namespace App\Models\Holpa;
namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

// keep model name as LocalIndicator temporary. It is easier and trace in early stage.
// This class will be renamed as CustomModule in later stage
class LocalIndicator extends Model
{
    use HasFactory;

    protected $table = 'local_indicators';

    protected $guarded = ['id'];

    protected static function booted()
    {
        static::created(function (self $localIndicator) {
            $xlsformModuleVersion = $localIndicator->xlsformModuleVersion()->create([
                'owner_id' => $localIndicator->team_id,
                'name' => $localIndicator->name,
                'is_default' => false,
            ]);

            // I have no idea why the above doesn't automatically associate the new module version with the current local indicator...
            $localIndicator->xlsformModuleVersion()->associate($xlsformModuleVersion);
            $localIndicator->save();
        });
    }

    // public function domain(): BelongsTo
    // {
    //     return $this->belongsTo(Domain::class);
    // }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // public function globalIndicator(): BelongsTo
    // {
    //     return $this->belongsTo(GlobalIndicator::class);
    // }

    public function xlsformModuleVersion(): BelongsTo
    {
        return $this->belongsTo(XlsformModuleVersion::class);
    }

}
