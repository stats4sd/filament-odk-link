<?php

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
