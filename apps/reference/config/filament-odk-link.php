<?php

use App\Models\Team;
use App\Models\User;
use App\OdkLink\ReferenceRoleResolver;
use Stats4sd\FilamentOdkLink\Models\Continent;
use Stats4sd\FilamentOdkLink\Models\Country;
use Stats4sd\FilamentOdkLink\Models\OdkLink\AppUser;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ParentDatasetPivot;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Platform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\TemplateEntityList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\XlsformModuleVersionLocale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Models\Region;
use Stats4sd\FilamentOdkLink\Support\NullSubmissionProcessor;

// config for Stats4sd/OdkLink
return [

    'models' => [

        'form_owner' => env('ODK_FORM_OWNER_MODEL', Team::class),
        'user_model' => env('ODK_USER_MODEL', User::class),
        'registry' => [
            Continent::class,
            Country::class,
            Region::class,
            AppUser::class,
            ChoiceList::class,
            ChoiceListEntry::class,
            Dataset::class,
            DatasetVariable::class,
            Entity::class,
            EntityValue::class,
            LanguageString::class,
            OdkDataset::class,
            OdkProject::class,
            ParentDatasetPivot::class,
            Platform::class,
            RequiredMedia::class,
            Submission::class,
            SurveyRow::class,
            TemplateEntityList::class,
            Xlsform::class,
            XlsformModule::class,
            XlsformModuleVersion::class,
            XlsformTemplate::class,
            XlsformTemplateSection::class,
            XlsformVersion::class,
            Language::class,
            LanguageStringType::class,
            Locale::class,
            XlsformModuleVersionLocale::class,
        ],
    ],

    'odk' => [

        /**
         * Tells the system which Aggregation system is in use. Possible values are:
         * - odk-central
         */
        'aggregator' => env('ODK_SERVICE', 'odk-central'),

        /**
         * The base url for the service (without the trailing '/').
         * If you use the public Kobotoolbox, this will be
         *  - 'https://kf.kobotoolbox.org' or
         *  - 'https://kobo.humanitarianresponse.info'
         *
         * If you use a custom installation of ODK Central or Kobotoolbox, it will be the base url to your service.
         */
        'url' => env('ODK_URL'),
        'base_endpoint' => env('ODK_URL', '').'/v1',

        'platform_project_id' => env('ODK_PLATFORM_PROJECT_ID'),

        /**
         * Username and password for the main platform account
         * The platform requires a 'primary' user account on the ODK Central / KoboToolbox server to manage deployments of ODK forms.
         * This account will *own* every form published by the platform.
         *
         * We recommend not using an account that individuals typically use or have access to, to avoid mismatch between forms deployed and forms in the Laravel database.
         */
        'username' => env('ODK_USERNAME', ''),
        'password' => env('ODK_PASSWORD', ''),

        // the password to be used for individual project accounts
        // TODO: consider options for allowing users to set their own passwords (which we cannot keep in plain text, so we must ask the user for it every time).
        // TODO: consider how to hash this - maybe each project has a unique seed that combines with the main ODK_PASSWORD to generate this.
        'project-password' => env('ODK_PROJECT_PASSWORD', env('ODK_PASSWORD')),
    ],

    'storage' => [
        'xlsforms' => env('ODK_XLSFORMS_DISK', 'public'),
        'media' => env('ODK_MEDIA_DISK', 'public'),
    ],

    'roles' => [
        // the role that a user must have in order to see *all* forms, and not just the ones owned by an entity linked to the user.
        'xlsform-admin' => env('XLSFORM_ADMIN_ROLE', 'admin'),
    ],

    'owners' => [
        'main_type' => env('MAIN_OWNER_TYPE', 'team'),
    ],

    'contracts' => [
        'submission_processor' => NullSubmissionProcessor::class,
        'role_resolver' => ReferenceRoleResolver::class,
        'current_owner_resolver' => null,
        'operation_notifier' => null,
    ],
];
