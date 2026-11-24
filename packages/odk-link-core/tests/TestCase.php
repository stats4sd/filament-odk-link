<?php

namespace Stats4sd\OdkLink\Core\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Stats4sd\OdkLink\Core\OdkLinkCoreServiceProvider;
use Stats4sd\OdkLink\Core\Tests\Models\Team;
use Stats4sd\OdkLink\Core\Tests\Models\User;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            static fn (string $modelName) => 'Stats4sd\\OdkLink\\Core\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExcelServiceProvider::class,
            MediaLibraryServiceProvider::class,
            OdkLinkCoreServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The package registers its migrations for *publishing* only
        // (runsMigrations is false), so the suite loads them explicitly.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Host-app-supplied tables (the form-owner model) that the package
        // migrations constrain against. The `0001_` prefix sorts this ahead of
        // the package's `000_`/`001_`/`002_` migrations, which depend on it.
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');
    }

    public function getEnvironmentSetUp($app): void
    {
        // Testbench ships a `testing` connection (in-memory sqlite).
        config()->set('database.default', 'testing');

        // The package does not ship the form-owner / user models — point them
        // at the lightweight test doubles in tests/Models.
        config()->set('odk-link.models.form_owner', Team::class);
        config()->set('odk-link.models.user_model', User::class);

        // Default to the package's "local-only" mode: an empty odk.url disables
        // the model hooks that auto-create ODK Central projects/app-users on
        // create (see HasXlsformTemplates::bootHasXlsformTemplates). Tests that
        // exercise ODK behaviour opt in by setting odk.url + Http::fake().
        // base_endpoint stays populated so service-layer tests have a known host
        // (the OdkLinkService singleton bakes it in at registration time).
        config()->set('odk-link.odk.url', '');
        config()->set('odk-link.odk.base_endpoint', 'https://odk.test/v1');
        config()->set('odk-link.odk.username', 'platform@odk.test');
        config()->set('odk-link.odk.password', 'secret');
        config()->set('odk-link.odk.platform_project_id', 1);

        // Keep file-producing code (xlsform/media storage) off the real disk.
        config()->set('odk-link.storage.xlsforms', 'xlsforms');
        config()->set('odk-link.storage.media', 'media');
        config()->set('filesystems.disks.xlsforms', ['driver' => 'local', 'root' => storage_path('framework/testing/xlsforms')]);
        config()->set('filesystems.disks.media', ['driver' => 'local', 'root' => storage_path('framework/testing/media')]);
    }
}
