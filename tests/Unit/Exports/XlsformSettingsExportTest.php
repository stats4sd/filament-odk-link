<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformSettingsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

// XlsformSettingsExport builds the single-row `settings` sheet of the exported
// XLSForm workbook. It reads only the Xlsform's title / odk_id, so an unsaved
// instance is enough (no global-scope query is triggered).

it('uses the odk_id as the form_id when the form has been deployed', function () {
    $xlsform = new Xlsform(['title' => 'My Survey']);
    $xlsform->odk_id = 'odk-form-123';

    $row = (new XlsformSettingsExport($xlsform))->collection()->first();

    expect($row['form_id'])->toBe('odk-form-123')
        ->and($row['form_title'])->toBe('My Survey')
        ->and($row['allow_choice_duplicates'])->toBe('yes');
});

it('falls back to a slug of the title when the form has no odk_id', function () {
    $xlsform = new Xlsform(['title' => 'My Survey']);

    $row = (new XlsformSettingsExport($xlsform))->collection()->first();

    expect($row['form_id'])->toBe(Str::slug('My Survey'));
});

it('exposes the expected headings and sheet title', function () {
    $export = new XlsformSettingsExport(new Xlsform(['title' => 'X']));

    expect($export->headings())->toBe([
        'form_id',
        'form_title',
        'version',
        'instance_name',
        'allow_choice_duplicates',
    ])
        ->and($export->title())->toBe('settings');
});

function giveFormALanguage(Xlsform $xlsform, string $name, string $isoAlpha2): void
{
    $languageId = DB::table('languages')->insertGetId([
        'name' => $name,
        'iso_alpha2' => $isoAlpha2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $localeId = DB::table('locales')->insertGetId([
        'language_id' => $languageId,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('language_owner')->insert([
        'language_id' => $languageId,
        'owner_id' => $xlsform->owner_id,
        'locale_id' => $localeId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('exports the template instance_name when the form has all the questions it refers to', function () {
    $template = makeXlsformTemplate();
    $template->updateQuietly(['instance_name' => 'concat("GLOBAL:", ${interviewer_name})']);
    $xlsform = makeXlsformWithQuestions($template, ['meta' => ['interviewer_name']]);

    $row = (new XlsformSettingsExport($xlsform))->collection()->first();

    expect($row['instance_name'])->toBe('concat("GLOBAL:", ${interviewer_name})');
});

it('exports the default instance_name when the form lacks a question the template refers to', function () {
    Log::shouldReceive('warning')->once();
    $template = makeXlsformTemplate();
    $template->updateQuietly(['instance_name' => 'concat(${farm_var_participant_name})']);
    $xlsform = makeXlsformWithQuestions($template, ['meta' => ['interviewer_name']]);

    $row = (new XlsformSettingsExport($xlsform))->collection()->first();

    expect($row['instance_name'])->toBe('"instance"');
});

it('exports default_language when the form has a language with that name', function () {
    $template = makeXlsformTemplate();
    $template->updateQuietly(['default_language' => 'English (en)']);
    $xlsform = makeXlsformWithQuestions($template, ['meta' => ['interviewer_name']]);
    giveFormALanguage($xlsform, 'English', 'en');

    $export = new XlsformSettingsExport($xlsform);

    expect($export->collection()->first()['default_language'])->toBe('English (en)')
        ->and($export->headings())->toBe([
            'form_id',
            'form_title',
            'version',
            'instance_name',
            'default_language',
            'allow_choice_duplicates',
        ]);
});

it('leaves default_language out and logs a warning when the form has no language with that name', function () {
    Log::shouldReceive('warning')->once();
    $template = makeXlsformTemplate();
    $template->updateQuietly(['default_language' => 'English (en)']);
    $xlsform = makeXlsformWithQuestions($template, ['meta' => ['interviewer_name']]);
    giveFormALanguage($xlsform, 'Spanish', 'es');

    $export = new XlsformSettingsExport($xlsform);

    expect($export->collection()->first())->not->toHaveKey('default_language')
        ->and($export->headings())->not->toContain('default_language');
});

it('leaves default_language out when the template has none', function () {
    $xlsform = makeXlsformWithQuestions(makeXlsformTemplate(), ['meta' => ['interviewer_name']]);

    expect((new XlsformSettingsExport($xlsform))->headings())->not->toContain('default_language');
});

it('gives the same settings row on every call', function () {
    $xlsform = makeXlsformWithQuestions(makeXlsformTemplate(), ['meta' => ['interviewer_name']]);
    $export = new XlsformSettingsExport($xlsform);

    expect($export->collection()->first())->toBe($export->collection()->first());
});
