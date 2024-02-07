<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsForms;

class Platform extends Model
{
    use HasXlsForms;

    protected $table = 'platforms';



    protected $casts = [
        'name',
    ];

    public function name(): Attribute
    {
        return new Attribute(
            get: fn (): string => config('app.name', 'Laravel Platform') . ' Platform.php' . $this->id,
        );
    }
}
