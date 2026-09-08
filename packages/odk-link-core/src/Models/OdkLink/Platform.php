<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformTemplates;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsformTemplates;

class Platform extends Model implements WithXlsformTemplates
{
    use HasXlsformTemplates;

    protected $table = 'platforms';

    protected $guarded = [];

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return new Attribute(
            get: fn (): string => config('app.name', 'Laravel Platform') . ' Platform.php' . $this->id,
        );
    }

    /** @return Attribute<bool, never> */
    protected function shouldReceiveAllXlsformTemplates(): Attribute
    {
        // The platform always has this set to false. Teams may have this set to true or false depending on the application settings requirements.
        return new Attribute(
            get: fn (): bool => false,
        );
    }
}
