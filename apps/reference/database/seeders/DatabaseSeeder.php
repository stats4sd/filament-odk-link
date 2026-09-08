<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Both role names are hard-coded or defaulted in the package: `Super Admin` receives
        // import notifications, `admin` (config roles.xlsform-admin) sees every form.
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $admin = Role::findOrCreate(config('filament-odk-link.roles.xlsform-admin'), 'web');

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Reference Admin', 'password' => 'password'],
        );
        $user->syncRoles([$superAdmin, $admin]);

        $team = Team::query()->firstOrCreate(['name' => 'Reference Team']);
        $team->users()->syncWithoutDetaching([$user->id]);

        // XlsformTemplateResource::getFormOwner() returns Platform::first(). The package's own
        // PlatformSeeder returns early when ODK_URL is empty, so create the row here.
        if (! Platform::query()->exists()) {
            Platform::query()->create();
        }
    }
}
