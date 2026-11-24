<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Stats4sd\OdkLink\Core\Exports\XlsformExport\XlsformSurveyExport;

// The constructor queries json_keys(), which SQLite lacks, so styles() is exercised
// on an instance built without it.
it('colours group and repeat boundary rows of the survey sheet', function () {
    $export = (new ReflectionClass(XlsformSurveyExport::class))->newInstanceWithoutConstructor();
    $export->locales = collect();

    $sheet = (new Spreadsheet)->getActiveSheet();
    $sheet->fromArray([
        ['module', 'name', 'type'],
        ['m', 'g', 'begin_group'],
        ['m', 'q', 'text'],
        ['m', null, 'end_group'],
        ['m', 'r', 'begin_repeat'],
        ['m', null, 'end_repeat'],
    ]);

    expect($export->styles($sheet))->toBeNull()
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('A2')->getFill()->getStartColor()->getRGB())->toBe('8ED873')
        ->and($sheet->getStyle('A3')->getFill()->getFillType())->toBe('none')
        ->and($sheet->getStyle('A4')->getFill()->getStartColor()->getRGB())->toBe('FA8E78')
        ->and($sheet->getStyle('A5')->getFill()->getStartColor()->getRGB())->toBe('83CAEB')
        ->and($sheet->getStyle('A6')->getFill()->getStartColor()->getRGB())->toBe('E49EDD');
});
