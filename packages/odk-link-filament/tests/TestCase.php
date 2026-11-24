<?php

namespace Stats4sd\OdkLink\Filament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Stats4sd\OdkLink\Filament\OdkLinkFilamentServiceProvider;
use Stats4sd\OdkLink\Filament\Tests\Models\Team;
use Stats4sd\OdkLink\Filament\Tests\Models\User;

class TestCase extends Orchestra
{
    public function ignorePackageDiscoveriesFrom(): array
    {
        return ['*'];
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            ExcelServiceProvider::class,
            MediaLibraryServiceProvider::class,
            OdkLinkFilamentServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The core registers its migrations for publishing only, so load them from the installed package.
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/stats4sd/odk-link-core/database/migrations');
    }

    public function getEnvironmentSetUp($app): void
    {
        // A fresh vendor/ has no testbench skeleton .env, so pin a key for anything that encrypts (Livewire, sessions).
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('database.default', 'testing');
        config()->set('odk-link.models.form_owner', Team::class);
        config()->set('odk-link.models.user_model', User::class);
        config()->set('odk-link.odk.url', '');
    }
}
