<?php

namespace Stats4sd\OdkLink\Core\Support;

use Illuminate\Support\Collection;
use Stats4sd\OdkLink\Core\Contracts\RoleResolver;

class EmptyRoleResolver implements RoleResolver
{
    public function notificationRecipients(): Collection
    {
        return collect();
    }
}
