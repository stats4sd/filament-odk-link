<?php

namespace Stats4sd\FilamentOdkLink\Database\Seeders;

use Illuminate\Database\Seeder;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;

class PlatformSeeder extends Seeder
{

    // create single entry to represent the current platform for xlsform_template drafts.

    // If there is an .env variable with an existing platform_odk_project ID, then instead of creating a new project on ODK Central, we will use the existing project. This allows for the same project to be used during development, when for example you may be deleting and re-seeding the database multiple times.

    public function run()
    {
        // check config item existence, and check empty config item
        if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') === '') {
            return;
        }


        // if there is no pre-set platform project ID, create the platform entry with the usual ODK Central project creation.
        if (!config('filament-odk-link.odk.platform_project_id')) {
            $platform = Platform::create();

            //add the platform's odk-project ID to the env file
            if ($platform->odkProject) {
                $this->setEnvironmentValue('ODK_PLATFORM_PROJECT_ID', $platform->odkProject->id);
            }

            return;
        }

        // create the platform quietly, then quietly create the odk project entry;
        // no app users are needed, because the 'platform' is not a user-facing entity. No xlsforms will be published to the platform project - it is only for testing drafts of xlsform templates.

        $platform = Platform::forceCreateQuietly();
        $odkProject = $platform->odkProject()->forceCreateQuietly([
            'id' => config('filament-odk-link.odk.platform_project_id'),
            'name' => config('app.name', 'Laravel Platform') . ' Platform',
        ]);
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {

            // if the .env file is set but empty, update it.
            if (str_contains(file_get_contents($path), $key . '=')) {
                file_put_contents($path, str_replace(
                    $key . '=' . env($key),
                    $key . '=' . $value,
                    file_get_contents($path)
                ));
                return;
            }

            // if the .env file is set but the key is not present, add it.
            file_put_contents($path, PHP_EOL . $key . '=' . $value, FILE_APPEND);
        }
    }
}
