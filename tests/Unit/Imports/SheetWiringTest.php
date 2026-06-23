<?php

use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateChoicesImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateHeadingRowImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateSurveyImport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateValidator;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateWorkbookImport;
use Stats4sd\FilamentOdkLink\Imports\XlsImport;

it('the generic XlsImport reads survey, choices and settings sheets', function () {
    expect(array_keys((new XlsImport)->sheets()))->toBe(['survey', 'choices', 'settings']);
});

it('the validator reads the survey and choices sheets', function () {
    expect(array_keys((new XlsformTemplateValidator)->sheets()))->toBe(['survey', 'choices']);
});

it('the heading-row import reads the survey and choices sheets', function () {
    expect(array_keys((new XlsformTemplateHeadingRowImport)->sheets()))->toBe(['survey', 'choices']);
});

it('the workbook import wires each sheet to its dedicated importer', function () {
    $version = addModuleVersion(makeXlsformTemplate(), 'demographics');

    $translatableHeadings = collect([
        'survey' => collect(['label::English (en)']),
        'choices' => collect(['label::English (en)']),
    ]);

    $sheets = (new XlsformTemplateWorkbookImport($version, $translatableHeadings))->sheets();

    expect($sheets['survey'])->toBeInstanceOf(XlsformTemplateSurveyImport::class)
        ->and($sheets['choices'])->toBeInstanceOf(XlsformTemplateChoicesImport::class);
});
