<?php

namespace Stats4sd\FilamentOdkLink\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Tests\Database\Factories\TeamFactory;

class Team extends Model implements WithXlsforms
{
    use HasFactory;
    use HasXlsforms;

    protected $guarded = [];

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }
}
