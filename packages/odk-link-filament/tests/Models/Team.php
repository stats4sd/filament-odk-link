<?php

namespace Stats4sd\OdkLink\Filament\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\OdkLink\Core\Contracts\FormOwner;
use Stats4sd\OdkLink\Core\Models\OdkLink\Traits\HasXlsforms;

class Team extends Model implements FormOwner
{
    use HasXlsforms;

    protected $guarded = [];
}
