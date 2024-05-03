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

        // if there is no pre-set platform project ID, create the platform entry with the usual ODK Central project creation.
        if(!config('filament-odk-link.odk.platform_project_id')) {
            $platform = Platform::create();

            //add the platform's odk-project ID to the env file
            if ($platform->odkProject) {
                $this->setEnvironmentValue('ODK_PLATFORM_PROJECT_ID', $platform->odkProject->id);
            }gs
