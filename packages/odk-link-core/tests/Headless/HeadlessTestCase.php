<?php

namespace Stats4sd\FilamentOdkLink\Tests\Headless;

use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Stats4sd\FilamentOdkLink\OdkLinkCoreServiceProvider;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;
use Stats4sd\FilamentOdkLink\Tests\Models\User;

class HeadlessTestCase extends TestCase
{
    public function ignorePackageDiscoveriesFrom(): array
    {
        return ['*'];
    }

    protected function getPackageProviders($app): array
    {
        return [ExcelServiceProvider::class, MediaLibraryServiceProvider::class, OdkLinkCoreServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/migrations');
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('filament-odk-link.models.form_owner', Team::class);
        config()->set('filament-odk-link.models.user_model', User::class);
        config()->set('filament-odk-link.odk.url', '');
    }
}
