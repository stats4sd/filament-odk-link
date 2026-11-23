<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;

class EmptyRoleResolver implements RoleResolver
{
    public function notificationRecipients(): Collection
    {
        return collect();
    }
}
