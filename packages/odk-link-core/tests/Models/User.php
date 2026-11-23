<?php

namespace Stats4sd\FilamentOdkLink\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;

class User extends Authenticatable implements PlatformUser
{
    use Notifiable;

    protected $guarded = [];
}
