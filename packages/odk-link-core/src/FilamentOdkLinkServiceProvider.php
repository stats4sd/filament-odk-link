<?php

namespace Stats4sd\FilamentOdkLink;

use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Services\XlsformTranslationHelper;
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
        $package->hasRoute('web');
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

        $this->app->singleton(XlsformTranslationHelper::class, function ($app) {
            return new XlsformTranslationHelper;
        });

        $this->app->register(FilamentOdkLinkEventServiceProvider::class);
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            [
                Css::make('filament-odk-link-styles', __DIR__.'/../resources/dist/filament-odk-link.css'),
                Js::make('filament-odk-link-scripts', __DIR__.'/../resources/dist/filament-odk-link.js'),
            ],
            'stats4sd/filament-odk-link'
        );

        // Testing
        Testable::mixin(new TestsFilamentOdkLink);
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        // get all files in the Commands directory
        $files = File::files(__DIR__.'/Commands');

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
            '000_create_datasets_table',
            '0000_create_media_table',
            '001_create_xlsform_templates_table',
            '002_create_xlsforms_table',
            '003_create_xlsform_modules_table',
            '004_create_xlsform_module_versions_table',
            '005_create_choice_lists_table',
            '006_create_survey_rows_table',
            '007_create_choice_list_entries_table',
            '008_create_choice_list_owner_table',
            '009_create_xlsform_versions_table',
            '010_create_submissions_table',
            '011_create_required_media_table',
            '012_create_odk_projects_table',
            '013_create_odk_datasets_table',
            '014_create_app_users_table',
            '015_create_entities_table',
            '016_create_dataset_variables_table',
            '018_create_entity_values_table',
            '020_create_platforms_table',
            '021_create_xlsform_template_sections_table',
            '022_create_app_user_assignments_table',
            '023_create_m49_regions_tables',
            '024_create_choice_list_entries_removed_table',
            '025_create_languages_table',
            '026_create_locales_table',
            '027_create_xlsform_module_version_locale_table',
            '028_create_language_string_types_table',
            '029_create_language_strings_table',
            '030_create_language_owners_table',
            '031_create_locale_owners_table',
            '032_create_selected_xlsform_module_versions_table',
            '033_create_dataset_parents_table',
            '034_create_templates_entity_lists_table',
        ];
    }
}
