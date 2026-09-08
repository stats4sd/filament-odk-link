<?php

use Illuminate\Support\Facades\Schema;

it('runs all package migrations on the test connection', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'teams',
    'media',
    'datasets',
    'xlsform_templates',
    'xlsforms',
    'xlsform_modules',
    'xlsform_module_versions',
    'choice_lists',
    'survey_rows',
    'choice_list_entries',
    'choice_list_owner',
    'xlsform_versions',
    'submissions',
    'required_media',
    'odk_projects',
    'odk_datasets',
    'app_users',
    'entities',
    'dataset_variables',
    'entity_values',
    'platforms',
    'xlsform_template_sections',
    'app_user_assignments',
    'continents',
    'regions',
    'countries',
    'choice_list_entries_removed_owner',
    'languages',
    'locales',
    'xlsform_module_version_locale',
    'language_string_types',
    'language_strings',
    'language_owner',
    'locale_owner',
    'dataset_parents',
]);
