<?php

use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;
use Stats4sd\OdkLink\Core\Services\UpdateXlsformTitleInFile;

beforeEach(function () {
    $this->filePath = tempnam(sys_get_temp_dir(), 'xlsform') . '.xlsx';

    copy(__DIR__ . '/../../fixtures/valid-form.xlsx', $this->filePath);
});

afterEach(function () {
    @unlink($this->filePath);
});

function readSettingsSheet(string $filePath): array
{
    $rows = IOFactory::load($filePath)->getSheetByName('settings')->toArray();

    return array_combine($rows[0], $rows[1]);
}

it('writes the form title and a slug form_id into the settings sheet', function () {
    UpdateXlsformTitleInFile::process(new Xlsform(['title' => 'Renamed Survey']), $this->filePath);

    $settings = readSettingsSheet($this->filePath);

    expect($settings['form_title'])->toBe('Renamed Survey')
        ->and($settings['form_id'])->toBe('renamed-survey')
        ->and($settings['version'])->toBe('1');
});

it('keeps the deployed odk_id as the form_id', function () {
    $xlsform = new Xlsform(['title' => 'Renamed Survey']);
    $xlsform->odk_id = 'existing-odk-id';

    UpdateXlsformTitleInFile::process($xlsform, $this->filePath);

    expect(readSettingsSheet($this->filePath)['form_id'])->toBe('existing-odk-id');
});

it('rejects a workbook without a settings sheet', function () {
    $spreadsheet = IOFactory::load($this->filePath);
    $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($spreadsheet->getSheetByName('settings')));
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($this->filePath);

    UpdateXlsformTitleInFile::process(new Xlsform(['title' => 'X']), $this->filePath);
})->throws(Exception::class, 'The file requires a "settings" worksheet.');
