<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Facades\Filament;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Stats4sd\FilamentOdkLink\Commands\FilamentOdkLinkCommand;
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
         *
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

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function registeringPackage()
    {
        $this->app->singleton(OdkLinkService::class, function ($app) {
            return new OdkLinkService(config('filament-odk-link.odk.base_endpoint'));
        });
    }

    public function packageRegistered(): void
    {
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Widget Registration
        Filament::registerWidgets([

        ]);

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-odk-link/{$file->getFilename()}"),
                ], 'filament-odk-link-stubs');
            }
        }

        // Testing
        Testable::mixin(new TestsFilamentOdkLink());
    }

    protected function getAssetPackageName(): ?string
    {
        return 'stats4sd/filament-odk-link';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('filament-odk-link', __DIR__ . '/../resources/dist/components/filament-odk-link.js'),
            Css::make('filament-odk-link-styles', __DIR__ . '/../resources/dist/filament-odk-link.css'),
            Js::make('filament-odk-link-scripts', __DIR__ . '/../resources/dist/filament-odk-link.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            FilamentOdkLinkCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
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
            '17_create_permission_tables',
            '18_create_teams_table',
            '19_create_role_invites_table',
            '20_create_team_invites_table',
            '21_create_team_members_table',
        ];
    }
}
