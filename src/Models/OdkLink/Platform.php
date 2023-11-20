<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsForms;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    use HasXlsForms;

    protected $table = 'platforms';
    protected $guarded = [];

    protected $casts = [
        'name',
    ];

    public function name(): Attribute
    {
        return new Attribute(
            get: fn(): string => config('app.name', 'Laravel Platform') . ' Platform.php' . $this->id,
        );
    }
}
