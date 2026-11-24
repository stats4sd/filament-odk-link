<?php

namespace Stats4sd\OdkLink\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface RoleResolver
{
    /** @return Collection<int, Model&PlatformUser> */
    public function notificationRecipients(): Collection;
}
