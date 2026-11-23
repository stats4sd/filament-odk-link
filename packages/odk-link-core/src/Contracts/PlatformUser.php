<?php

namespace Stats4sd\FilamentOdkLink\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/** Implement with Laravel's Notifiable trait, including database and broadcast routing. */
interface PlatformUser extends Authenticatable
{
    public function notify($instance);

    public function routeNotificationFor($driver, $notification = null);
}
