<?php

namespace Stats4sd\OdkLink\Core\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stats4sd\OdkLink\Core\Contracts\PlatformUser;

class User extends Authenticatable implements PlatformUser
{
    use Notifiable;

    protected $guarded = [];
}
