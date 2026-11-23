<?php

namespace Stats4sd\FilamentOdkLink\Filament\Tests;

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
use Stats4sd\FilamentOdkLink\Filament\OdkLinkFilamentServiceProvider;
use Stats4sd\FilamentOdkLink\Filament\Tests\Models\Team;
use Stats4sd\FilamentOdkLink\Filament\Tests\Models\User;

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
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/stats4sd/filament-odk-link/database/migrations');
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('filament-odk-link.models.form_owner', Team::class);
        config()->set('filament-odk-link.models.user_model', User::class);
        config()->set('filament-odk-link.odk.url', '');
    }
}
