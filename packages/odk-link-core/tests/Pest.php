<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// Run package + test migrations for every test that touches the database.
// ArchTest and other pure tests are unaffected (no DB queries = no migration cost beyond setup).
uses(RefreshDatabase::class)->in(__DIR__);

/*
 * Shared fixture builders for the import/export tests.
 *
 * The form hierarchy's booted() hooks fire ODK / media side-effects on save, so
 * (as elsewhere in the suite) we insert the FK chain at the DB level and re-read
 * the models. This keeps the graph creation off the model event path.
 */

function makeXlsformTemplate(string $title = 'Test Template'): XlsformTemplate
{
    $id = DB::table('xlsform_templates')->insertGetId([
        'title' => $title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return XlsformTemplate::find($id);
}

/**
 * Insert a module + its default version under a template. Returns the version.
 *
 * @param  array<string, mixed>  $moduleAttrs  extra columns for xlsform_modules (e.g. row_names, default_order)
 */
function addModuleVersion(XlsformTemplate $template, string $name, array $moduleAttrs = []): XlsformModuleVersion
{
    $moduleId = DB::table('xlsform_modules')->insertGetId([
        'xlsform_template_id' => $template->id,
        'name' => $name,
        'label' => $name,
        'created_at' => now(),
        'updated_at' => now(),
        ...$moduleAttrs,
    ]);

    $versionId = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $moduleId,
        'name' => 'Global '.$name,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return XlsformModuleVersion::find($versionId);
}

function addChoiceList(XlsformModuleVersion $version, string $listName, array $attrs = []): ChoiceList
{
    $id = DB::table('choice_lists')->insertGetId([
        'xlsform_module_version_id' => $version->id,
        'list_name' => $listName,
        'created_at' => now(),
        'updated_at' => now(),
        ...$attrs,
    ]);

    return ChoiceList::find($id);
}

function addChoiceListEntry(ChoiceList $choiceList, string $name, array $attrs = []): ChoiceListEntry
{
    $id = DB::table('choice_list_entries')->insertGetId([
        'choice_list_id' => $choiceList->id,
        'name' => $name,
        'properties' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
        ...$attrs,
    ]);

    return ChoiceListEntry::find($id);
}

function makeLocale(string $isoAlpha2 = 'en'): Locale
{
    $languageId = DB::table('languages')->insertGetId([
        'iso_alpha2' => $isoAlpha2,
        'name' => $isoAlpha2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localeId = DB::table('locales')->insertGetId([
        'language_id' => $languageId,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Locale::find($localeId);
}

function makeLanguageStringType(string $name = 'label'): LanguageStringType
{
    $id = DB::table('language_string_types')->insertGetId([
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return LanguageStringType::find($id);
}
