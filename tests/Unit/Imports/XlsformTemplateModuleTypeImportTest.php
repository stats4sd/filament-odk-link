<?php

use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateModuleTypeImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;

it('maps a row to an XlsformModule using the module column for name and label', function () {
    $module = (new XlsformTemplateModuleTypeImport)->model(['module' => 'demographics']);

    expect($module)->toBeInstanceOf(XlsformModule::class)
        ->and($module->name)->toBe('demographics')
        ->and($module->label)->toBe('demographics')
        ->and($module->updated_during_import)->toBeTrue();
});

it('declares name as the upsert key', function () {
    expect((new XlsformTemplateModuleTypeImport)->uniqueBy())->toBe(['name']);
});
