<?php

use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateSurveyImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

// Construct the importer against a real (DB-inserted) module version so the
// internal getModuleVersionAndNameFromRow() short-circuit returns it directly.
beforeEach(function () {
    $template = makeXlsformTemplate();
    $this->version = addModuleVersion($template, 'demographics');
    $this->choiceList = addChoiceList($this->version, 'yn');

    $this->translatableHeadings = collect(['label::English (en)', 'hint::English (en)']);

    $this->makeImport = fn () => new XlsformTemplateSurveyImport($this->version, $this->translatableHeadings);
});

it('maps the core XLSForm spec columns onto a SurveyRow', function () {
    $import = ($this->makeImport)();
    $import->rememberRowNumber(2);

    $row = $import->model([
        'type' => 'text',
        'name' => 'full_name',
        'relevant' => '${age} > 18',
        'appearance' => 'multiline',
        'label::English (en)' => 'Your name',
    ]);

    expect($row)->toBeInstanceOf(SurveyRow::class)
        ->and($row->name)->toBe('full_name')
        ->and($row->type)->toBe('text')
        ->and($row->relevant)->toBe('${age} > 18')
        ->and($row->appearance)->toBe('multiline')
        ->and($row->row_number)->toBe(2)
        ->and($row->xlsform_module_version_id)->toBe($this->version->id)
        ->and($row->updated_during_import)->toBeTrue();
});

it('coerces the required column to a boolean', function (string $input, bool $expected) {
    $import = ($this->makeImport)();
    $import->rememberRowNumber(1);

    $row = $import->model(['type' => 'text', 'name' => 'q', 'required' => $input]);

    expect($row->required)->toBe($expected);
})->with([
    ['yes', true],
    ['true', true],
    ['1', true],
    ['no', false],
    ['false', false],
    ['anything-else', false],
]);

it('bundles non-spec, non-translatable columns into the properties json', function () {
    $import = ($this->makeImport)();
    $import->rememberRowNumber(1);

    $row = $import->model([
        'type' => 'text',
        'name' => 'q',
        'label::English (en)' => 'Q',   // translatable → excluded
        'read_only' => 'yes',           // custom property → bundled
    ]);

    expect($row->properties->toArray())->toBe(['read_only' => 'yes']);
});

it('links the choice list for select_one questions', function () {
    $import = ($this->makeImport)();
    $import->rememberRowNumber(1);

    $row = $import->model(['type' => 'select_one yn', 'name' => 'consent']);

    expect($row->choice_list_id)->toBe($this->choiceList->id);
});

it('generates a name from the type and row number when the name is blank', function () {
    $import = ($this->makeImport)();
    $import->rememberRowNumber(9);

    $row = $import->model(['type' => 'end_group', 'name' => '']);

    expect($row->name)->toBe('end_group_9');
});

it('treats a row with no name and no type as empty', function () {
    $import = ($this->makeImport)();

    expect($import->isEmptyWhen(['name' => '', 'type' => '']))->toBeTrue()
        ->and($import->isEmptyWhen(['name' => 'q', 'type' => 'text']))->toBeFalse();
});

it('declares the composite upsert key', function () {
    expect(($this->makeImport)()->uniqueBy())->toBe(['xlsform_module_version_id', 'name', 'type']);
});
