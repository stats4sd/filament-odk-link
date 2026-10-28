<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function writeWorkbook(array $settingsRows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('survey')->fromArray([['type', 'name']]);

    if ($settingsRows !== []) {
        $spreadsheet->createSheet()->setTitle('settings')->fromArray($settingsRows);
    }

    $path = tempnam(sys_get_temp_dir(), 'settings-test-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/settings-test-*') as $file) {
        unlink($file);
    }
});

it('reads instance_name and default_language from the settings sheet', function () {
    $path = writeWorkbook([
        ['form_title', 'default_language', 'instance_name'],
        ['My Form', 'English (en)', 'concat("GLOBAL:", ${interviewer_name})'],
    ]);

    $settings = makeXlsformTemplate()->readSettingsFromFile($path);

    expect($settings)->toBe([
        'instance_name' => 'concat("GLOBAL:", ${interviewer_name})',
        'default_language' => 'English (en)',
    ]);
});

it('reads the settings columns whatever their order', function () {
    $path = writeWorkbook([
        ['form_title', 'form_id', 'version', 'instance_name', 'default_language'],
        ['My Form', 'my_form', '1', 'concat("REGISTRATION:", ${participant_name})', 'English (en)'],
    ]);

    $settings = makeXlsformTemplate()->readSettingsFromFile($path);

    expect($settings['instance_name'])->toBe('concat("REGISTRATION:", ${participant_name})')
        ->and($settings['default_language'])->toBe('English (en)');
});

it('returns null for a column the settings sheet does not have or leaves blank', function () {
    $path = writeWorkbook([
        ['form_title', 'default_language', 'instance_name'],
        ['My Form', 'English (en)', ''],
    ]);

    $settings = makeXlsformTemplate()->readSettingsFromFile($path);

    expect($settings['instance_name'])->toBeNull()
        ->and($settings['default_language'])->toBe('English (en)');
});

it('returns null when the workbook has no settings sheet', function () {
    $path = writeWorkbook([]);

    expect(makeXlsformTemplate()->readSettingsFromFile($path))->toBeNull();
});

it('returns null when the template has no stored file', function () {
    config(['media-library.media_model' => Media::class]);

    expect(makeXlsformTemplate()->readSettingsFromFile())->toBeNull();
});

it('saves the settings on the template', function () {
    $path = writeWorkbook([
        ['default_language', 'instance_name'],
        ['English (en)', 'concat("WOMEN\'S FORM:", ${interviewername})'],
    ]);
    $template = makeXlsformTemplate();

    $template->syncSettingsFromFile($path);

    expect($template->fresh()->instance_name)->toBe('concat("WOMEN\'S FORM:", ${interviewername})')
        ->and($template->fresh()->default_language)->toBe('English (en)');
});

it('clears settings that the new file no longer has', function () {
    $template = makeXlsformTemplate();
    $template->updateQuietly(['instance_name' => 'concat("OLD:", ${name})', 'default_language' => 'Spanish (es)']);
    $path = writeWorkbook([
        ['form_title'],
        ['My Form'],
    ]);

    $template->syncSettingsFromFile($path);

    expect($template->fresh()->instance_name)->toBeNull()
        ->and($template->fresh()->default_language)->toBeNull();
});

it('keeps the stored settings when the new file has no settings sheet', function () {
    $template = makeXlsformTemplate();
    $template->updateQuietly(['instance_name' => 'concat("OLD:", ${name})', 'default_language' => 'Spanish (es)']);
    $path = writeWorkbook([]);

    $template->syncSettingsFromFile($path);

    expect($template->fresh()->instance_name)->toBe('concat("OLD:", ${name})')
        ->and($template->fresh()->default_language)->toBe('Spanish (es)');
});

it('does not touch the other template columns when syncing settings', function () {
    $template = makeXlsformTemplate('Original Title');
    $path = writeWorkbook([
        ['form_title', 'instance_name'],
        ['Different Title', 'concat("X:", ${name})'],
    ]);

    $template->syncSettingsFromFile($path);

    expect($template->fresh()->title)->toBe('Original Title');
});
