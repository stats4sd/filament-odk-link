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
        if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') == '') {
            return;
        }

        // check config item existence
        if (config('filament-odk-link.odk.platform_project_id') === null) {
            return;
        }

        // and check empty config item
        if (config('filament-odk-link.odk.platform_project_id') == '') {
            $platform = Platform::create();

            //add the platform's odk-project ID to the env file
            $this->setEnvironmentValue('ODK_PLATFORM_PROJECT_ID', $platform->odkProject->id);

            return;
        }

        // create the platform quietly, then quietly create the odk project entry;

        // Question: If we call forceCreateQuitely(), there is no app_users record for platform model

        $platform = Platform::forceCreateQuietly();
        $odkProject = $platform->odkProject()->forceCreateQuietly([
            'id' => config('filament-odk-link.odk.platform_project_id'),
            'name' => config('app.name', 'Laravel Platform') . ' Platform',
        ]);
    }

    private function setEnvironmentValue($key, $value): void
    {
        $path = base_path('.env');

        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $key . '=' . env($key),
                $key . '=' . $value,
                file_get_contents($path)
            ));
        }
    }
}
