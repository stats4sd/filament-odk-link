<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsForms;

class Platform extends Model implements WithXlsforms
{
    use HasXlsForms;

    protected $table = 'platforms';



    protected $casts = [
        'name',
    ];

    /** @return Attribute<string, never> */
    public function name(): Attribute
    {
        return new Attribute(
            get: fn (): string => config('app.name', 'Laravel Platform') . ' Platform.php' . $this->id,
        );
    }
}
