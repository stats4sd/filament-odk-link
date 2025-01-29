<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class Platform extends Model implements WithXlsforms
{
    use HasXlsforms;

    protected $table = 'platforms';

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return new Attribute(
            get: fn (): string => config('app.name', 'Laravel Platform') . ' Platform.php' . $this->id,
        );
    }

    /** @return Attribute<bool, never> */
    public function shouldReceiveAllXlsformTemplates(): Attribute
    {
        return new Attribute(
            get: fn (): bool => false,
        );
    }
}
