<?php

namespace Stats4sd\FilamentOdkLink\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface RoleResolver
{
    /** @return Collection<int, Model&PlatformUser> */
    public function notificationRecipients(): Collection;
}
