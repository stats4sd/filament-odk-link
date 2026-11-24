<?php

namespace Stats4sd\OdkLink\Core\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\OdkLink\Core\Contracts\FormOwner;
use Stats4sd\OdkLink\Core\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\OdkLink\Core\Tests\Database\Factories\TeamFactory;

class Team extends Model implements FormOwner
{
    use HasFactory;
    use HasXlsforms;

    protected $guarded = [];

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }
}
