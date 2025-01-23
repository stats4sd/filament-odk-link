<?php

namespace Stats4sd\FilamentOdkLink\Database\Seeders;

use Illuminate\Database\Seeder;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;

class PlatformSeeder extends Seeder
{
    // create single entry to represent the current platform for xlsform_template drafts.

    // If there is an .env variable with an existing platform_odk_project ID, then instead of creating a new project on ODK Central, we will use the existing project. This allows for the same project to be used during development, when for example you may be deleting and re-seeding the database multiple times.

    public function run(): void
    {

        // force exit unless in local env
        if (app()->environment() !== 'local') {
            abort(403, 'This seeder can only be run in the local environment.');
        }

        // check config item existence, and check empty config item
        if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') === '') {
            return;
        }

        // if there is no pre-set platform project ID, create the platform entry with the usual ODK Central project creation.
        if (! config('filament-odk-link.odk.platform_project_id')) {
            $platform = Platform::create();

            // add the platform's odk-project ID to the env file
            $this->setEnvironmentValue($platform->odkProject->id);

            return;
        }

        // create the platform quietly, then quietly create the odk project entry;
        // no app users are needed, because the 'platform' is not a user-facing entity. No xlsforms will be published to the platform project - it is only for testing drafts of xlsform templates.

        /** @var Platform $platform */
        $platform = Platform::forceCreateQuietly();

        $platform->odkProject()->forceCreateQuietly([
            'id' => config('filament-odk-link.odk.platform_project_id'),
            'name' => config('app.name', 'Laravel Platform') . ' Platform',
        ]);
    }

    private function setEnvironmentValue(string $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {

            // if the .env file is set but empty, update it.
            if (str_contains(file_get_contents($path), 'ODK_PLATFORM_PROJECT_ID' . '=')) {
                file_put_contents($path, preg_replace(
                    pattern: '/^ODK_PLATFORM_PROJECT_ID=.*$/',
                    replacement: 'ODK_PLATFORM_PROJECT_ID' . '=' . $value,
                    subject: file_get_contents($path)
                ));

                return;
            }

            // if the .env file is set but the key is not present, add it.
            file_put_contents($path, PHP_EOL . 'ODK_PLATFORM_PROJECT_ID' . '=' . $value, FILE_APPEND);
        }
    }
}
