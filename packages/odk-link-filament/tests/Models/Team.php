<?php

namespace Stats4sd\FilamentOdkLink\Filament\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class Team extends Model implements FormOwner
{
    use HasXlsforms;

    protected $guarded = [];
}
