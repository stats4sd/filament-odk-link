<?php

namespace Stats4sd\FilamentOdkLink\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Stats4sd\FilamentOdkLink\FilamentOdkLinkServiceProvider;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            static fn (string $modelName) => 'Stats4sd\\FilamentOdkLink\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
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
            ExcelServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentOdkLinkServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The package registers its migrations for *publishing* only
        // (runsMigrations is false), so the suite loads them explicitly.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Host-app-supplied tables (the form-owner model) that the package
        // migrations constrain against. The `0001_` prefix sorts this ahead of
        // the package's `000_`/`001_`/`002_` migrations, which depend on it.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function getEnvironmentSetUp($app): void
    {
        // Testbench ships a `testing` connection (in-memory sqlite).
        config()->set('database.default', 'testing');

        // The package does not ship the form-owner / user models — point them
        // at the lightweight test doubles in tests/Models.
        config()->set('filament-odk-link.models.form_owner', Team::class);

        // Default to the package's "local-only" mode: an empty odk.url disables
        // the model hooks that auto-create ODK Central projects/app-users on
        // create (see HasXlsformTemplates::bootHasXlsformTemplates). Tests that
        // exercise ODK behaviour opt in by setting odk.url + Http::fake().
        // base_endpoint stays populated so service-layer tests have a known host
        // (the OdkLinkService singleton bakes it in at registration time).
        config()->set('filament-odk-link.odk.url', '');
        config()->set('filament-odk-link.odk.base_endpoint', 'https://odk.test/v1');
        config()->set('filament-odk-link.odk.username', 'platform@odk.test');
        config()->set('filament-odk-link.odk.password', 'secret');
        config()->set('filament-odk-link.odk.platform_project_id', 1);

        // Keep file-producing code (xlsform/media storage) off the real disk.
        config()->set('filament-odk-link.storage.xlsforms', 'xlsforms');
        config()->set('filament-odk-link.storage.media', 'media');
        config()->set('filesystems.disks.xlsforms', ['driver' => 'local', 'root' => storage_path('framework/testing/xlsforms')]);
        config()->set('filesystems.disks.media', ['driver' => 'local', 'root' => storage_path('framework/testing/media')]);
    }
}
