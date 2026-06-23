<?php

use Illuminate\Database\QueryException;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateChoiceListImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;

beforeEach(function () {
    $this->template = makeXlsformTemplate();
    $this->version = addModuleVersion($this->template, 'demographics');
});

it('ignores non-select rows', function () {
    $import = new XlsformTemplateChoiceListImport($this->version);

    expect($import->model(['type' => 'text', 'name' => 'q']))->toBeNull();
});

it('builds a ChoiceList for a select_one row, deriving the list name from the type', function () {
    $import = new XlsformTemplateChoiceListImport($this->version);

    $choiceList = $import->model(['type' => 'select_one yn', 'name' => 'consent']);

    expect($choiceList)->toBeInstanceOf(ChoiceList::class)
        ->and($choiceList->list_name)->toBe('yn')
        ->and($choiceList->xlsform_module_version_id)->toBe($this->version->id)
        ->and($choiceList->is_localisable)->toBeFalse();
});

it('marks the choice list localisable when the localisable column is truthy', function (mixed $input, bool $expected) {
    $import = new XlsformTemplateChoiceListImport($this->version);

    $choiceList = $import->model([
        'type' => 'select_multiple drinks',
        'name' => 'q',
        'localisable' => $input,
    ]);

    expect($choiceList->is_localisable)->toBe($expected);
})->with([
    ['yes', true],
    ['true', true],
    ['1', true],
    ['no', false],
    ['', false],
]);

// select_from_file rows take the RequiredMedia branch. This currently throws on
// sqlite because the importer's updateOrCreate omits the NOT-NULL `type` column.
// Documented in docs/issues.md. The from_file branch resolves the template from
// the model directly, so use the template as the import target here.
it('throws when recording RequiredMedia for select_from_file rows because type is never set', function () {
    $import = new XlsformTemplateChoiceListImport($this->template);

    expect(fn () => $import->model(['type' => 'select_one_from_file households.csv', 'name' => 'household']))
        ->toThrow(QueryException::class, 'required_media.type');
});

it('declares the composite upsert key and empty-row rule', function () {
    $import = new XlsformTemplateChoiceListImport($this->version);

    expect($import->uniqueBy())->toBe(['xlsform_module_version_id', 'list_name'])
        ->and($import->isEmptyWhen(['type' => '']))->toBeTrue()
        ->and($import->isEmptyWhen(['type' => 'select_one yn']))->toBeFalse();
});
