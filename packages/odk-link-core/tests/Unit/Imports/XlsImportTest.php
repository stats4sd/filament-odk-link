<?php

use Maatwebsite\Excel\Excel;
use Stats4sd\OdkLink\Core\Imports\XlsImport;
use Stats4sd\OdkLink\Core\Services\XlsformValidationHelper;

it('reads the survey, choices and settings sheets of an XLSForm keyed by heading', function () {
    $sheets = (new XlsImport)->toCollection(__DIR__ . '/../../fixtures/valid-form.xlsx', null, Excel::XLSX);

    expect($sheets->keys()->all())->toContain('survey', 'choices', 'settings')
        ->and($sheets['survey']->pluck('name')->filter()->values()->all())
        ->toBe(['full_name', 'age', 'consent', 'intro_note', 'hh_member', 'member_name'])
        ->and($sheets['choices']->pluck('list_name')->unique()->values()->all())->toBe(['yn'])
        ->and($sheets['settings']->first()['form_id'])->toBe('valid_test_form');
});

it('finds no or_other questions in a valid XLSForm', function () {
    expect(XlsformValidationHelper::validateTypeOrOther(__DIR__ . '/../../fixtures/valid-form.xlsx'))->toBeEmpty();
});
