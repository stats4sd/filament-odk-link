<?php

namespace Stats4sd\FilamentOdkLink\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class Team extends Model implements WithXlsforms
{
    use HasXlsforms;
}
