<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Facades\Filament;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Testing\TestsFilamentOdkLink;

class FilamentOdkLinkServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-odk-link';

    public static string $viewNamespace = 'filament-odk-link';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });

        $package->hasConfigFile();
        $package->hasMigrations($this->getMigrations());

        // TODO: add translations
        // $package->hasTranslations();

        $package->hasViews(static::$viewNamespace);
    }

    public function registeringPackage()
    {
        // Setup the main service class as a singleton.
        $this->app->singleton(OdkLinkService::class, function ($app) {
            return new OdkLinkService(config('filament-odk-link.odk.base_endpoint'));
        });
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            [
                Css::make('filament-odk-link-styles', __DIR__ . '/../resources/dist/filament-odk-link.css'),
                Js::make('filament-odk-link-scripts', __DIR__ . '/../resources/dist/filament-odk-link.js'),
            ],
            'stats4sd/filament-odk-link'
        );

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-odk-link/{$file->getFilename()}"),
                ], 'filament-odk-link-stubs');
            }
        }

        // Testing
        Testable::mixin(new TestsFilamentOdkLink);
    }


    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        // get all files in the Commands directory
        $files = File::files(__DIR__ . '/Commands');

        return collect($files)->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->map(fn ($filename) => "Stats4sd\\FilamentOdkLink\\Commands\\{$filename}")
            ->toArray();
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            '1_create_datasets_table',
            '2_create_xlsform_templates_table',
            '3_create_xlsforms_table',
            '4_create_xlsform_versions_table',
            '5_create_submissions_table',
            '6_create_required_media_table',
            '7_create_odk_datasets_table',
            '8_create_odk_projects_table',
            '9_create_app_users_table',
            '10_create_entities_table',
            '11_create_entity_values_table',
            '12_create_dataset_variables_table',
            '13_create_platforms_table',
            '14_create_xlsform_template_sections_table',
            '15_create_app_user_assignments_table',
            '16_create_media_table',
            // TODO: remove these? We should not assume the users are using our Teams / Invites stuff.
            '17_create_permission_tables',
            '18_create_teams_table',
            '19_create_role_invites_table',
            '20_create_team_invites_table',
            '21_create_team_members_table',
        ];
    }
}
