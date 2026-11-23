<?php

namespace App\OdkLink;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Contracts\RoleResolver;

class ReferenceRoleResolver implements RoleResolver
{
    /** @return Collection<int, User> */
    public function notificationRecipients(): Collection
    {
        if (! Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::role('Super Admin', 'web')->get();
    }
}
