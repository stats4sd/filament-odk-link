<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Stats4sd\FilamentOdkLink\Services\ChoiceLabelDiagnostics;

function choicesWorkbook(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('choices')->fromArray($rows);

    $path = tempnam(sys_get_temp_dir(), 'choices') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

$odkMessage = "ODK Validate Errors: >> XForm is invalid. : <label> node for itemset doesn't exist! [instance(who_family)/root/item/label]";

it('finds the list named in the missing label error', function () use ($odkMessage) {
    expect(ChoiceLabelDiagnostics::listNameFromValidationError($odkMessage))->toBe('who_family');
    expect(ChoiceLabelDiagnostics::listNameFromValidationError('Some other error'))->toBeNull();
});

it('lists the entries that have no label in any language', function () {
    $path = choicesWorkbook([
        ['list_name', 'name', 'label::English (en)', 'label::Spanish (es)'],
        ['who_family', 'female_adult', '', ''],
        ['who_family', 'male_adult', '', 'Hombre adulto'],
        ['yes_no', 'yes', '', ''],
    ]);

    $result = (new ChoiceLabelDiagnostics)->unlabelledEntries($path, 'who_family');

    expect($result['entries'])->toBe(['female_adult']);
    expect($result['languages'])->toBe(['English (en)', 'Spanish (es)']);
});

it('explains the error with the list, the entries and the languages', function () use ($odkMessage) {
    $path = choicesWorkbook([
        ['list_name', 'name', 'label::English (en)'],
        ['who_family', 'female_adult', ''],
        ['who_family', 'male_adult', ''],
    ]);

    $explanation = (new ChoiceLabelDiagnostics)->explain($odkMessage, $path);

    expect($explanation)
        ->toContain("'who_family' has 2 entries")
        ->toContain('English (en)')
        ->toContain('female_adult, male_adult')
        ->toContain(e($odkMessage));
});

it('has nothing to add when every entry of the list has a label', function () use ($odkMessage) {
    $path = choicesWorkbook([
        ['list_name', 'name', 'label::English (en)'],
        ['who_family', 'female_adult', 'Female adult'],
    ]);

    expect((new ChoiceLabelDiagnostics)->explain($odkMessage, $path))->toBeNull();
});

it('has nothing to add for an unrelated error', function () {
    $path = choicesWorkbook([['list_name', 'name', 'label'], ['who_family', 'a', '']]);

    expect((new ChoiceLabelDiagnostics)->explain('Some other error', $path))->toBeNull();
});
