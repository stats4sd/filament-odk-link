<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A user holding both roles the package relies on, attached to one team, with the
 * Platform row the admin resources expect. Mirrors DatabaseSeeder.
 */
function seededAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles([
        Role::findOrCreate('Super Admin', 'web'),
        Role::findOrCreate(config('filament-odk-link.roles.xlsform-admin'), 'web'),
    ]);

    $team = Team::factory()->create();
    $team->users()->attach($user);

    if (! Platform::query()->exists()) {
        Platform::query()->create();
    }

    return $user;
}
