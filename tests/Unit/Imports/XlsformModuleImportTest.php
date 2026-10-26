<?php

use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformModuleImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;

it('creates a generic "Unspecified Module" for rows with no module, preserving order', function () {
    $template = makeXlsformTemplate('My Form');

    (new XlsformModuleImport($template))->collection(collect([
        ['module' => 'intro', 'type' => 'text', 'name' => 'q1'],
        ['module' => 'intro', 'type' => 'text', 'name' => 'q2'],
        ['type' => 'note', 'name' => 'n1'],                              // no module → generic
        ['module' => 'demographics', 'type' => 'integer', 'name' => 'age'],
    ]));

    $modules = $template->xlsformModules()->orderBy('default_order')->get();

    expect($modules->pluck('name')->all())->toBe([
        'intro',
        'My Form - Unspecified Module 3',
        'demographics',
    ])
        ->and($modules->pluck('default_order')->all())->toBe([1, 2, 3]);
});

it('stores the type_name combos of each module in row_names', function () {
    $template = makeXlsformTemplate('My Form');

    (new XlsformModuleImport($template))->collection(collect([
        ['module' => 'intro', 'type' => 'text', 'name' => 'q1'],
        ['module' => 'intro', 'type' => 'select_one yn', 'name' => 'q2'],
    ]));

    $intro = $template->xlsformModules()->firstWhere('name', 'intro');

    expect($intro->row_names->all())->toBe(['text_q1', 'select_one yn_q2']);
});

it('leaves a gap in the ordering after a module that can be extended', function () {
    $template = makeXlsformTemplate('My Form');

    (new XlsformModuleImport($template))->collection(collect([
        ['module' => 'base', 'type' => 'text', 'name' => 'q1', 'localisable_module' => 'extend'],
        ['module' => 'next', 'type' => 'text', 'name' => 'q2'],
    ]));

    $base = $template->xlsformModules()->firstWhere('name', 'base');
    $next = $template->xlsformModules()->firstWhere('name', 'next');

    // 'base' takes order 1; because it can be extended, order jumps to 3 for 'next'.
    expect($base->can_be_extended)->toBeTrue()
        ->and($base->default_order)->toBe(1)
        ->and($next->default_order)->toBe(3);
});

it('parses the modules out of a real XLSForm fixture', function () {
    $template = makeXlsformTemplate('Valid Test Form');

    (new XlsformModuleImport($template))->import(__DIR__ . '/../../fixtures/valid-form.xlsx');

    $modules = $template->xlsformModules()->get();

    expect($modules)->toHaveCount(3)
        ->and($modules->pluck('name'))->toContain('demographics', 'repeats')
        ->and($modules->first(fn (XlsformModule $m) => str_contains($m->name, 'Unspecified Module')))->not->toBeNull();
});

it('leaves existing can_be_replaced and can_be_extended flags untouched when the file has no localisable_module column', function () {
    $template = makeXlsformTemplate('My Form');
    addModuleVersion($template, 'locations', ['can_be_replaced' => true]);
    addModuleVersion($template, 'hdds', ['can_be_extended' => true]);

    (new XlsformModuleImport($template))->collection(collect([
        ['module' => 'locations', 'type' => 'text', 'name' => 'q1'],
        ['module' => 'hdds', 'type' => 'text', 'name' => 'q2'],
        ['module' => 'brand_new', 'type' => 'text', 'name' => 'q3'],
    ]));

    $modules = $template->xlsformModules()->get()->keyBy('name');

    expect($modules['locations']->can_be_replaced)->toBeTrue()
        ->and($modules['hdds']->can_be_extended)->toBeTrue()
        ->and($modules['brand_new']->can_be_replaced)->toBeFalse()
        ->and($modules['brand_new']->can_be_extended)->toBeFalse();
});

it('sets the flags from the localisable_module column when the file has one', function () {
    $template = makeXlsformTemplate('My Form');
    addModuleVersion($template, 'locations', ['can_be_replaced' => true]);
    addModuleVersion($template, 'stale', ['can_be_replaced' => true]);

    (new XlsformModuleImport($template))->collection(collect([
        ['module' => 'locations', 'type' => 'text', 'name' => 'q1', 'localisable_module' => 'extend'],
        ['module' => 'stale', 'type' => 'text', 'name' => 'q2', 'localisable_module' => null],
        ['module' => 'farm_info', 'type' => 'text', 'name' => 'q3', 'localisable_module' => 'replace'],
    ]));

    $modules = $template->xlsformModules()->get()->keyBy('name');

    expect($modules['locations']->can_be_extended)->toBeTrue()
        ->and($modules['locations']->can_be_replaced)->toBeFalse()
        ->and($modules['stale']->can_be_replaced)->toBeFalse()
        ->and($modules['farm_info']->can_be_replaced)->toBeTrue();
});

it('treats rows read from a spreadsheet as carrying the localisable_module column when its header is present', function () {
    $template = makeXlsformTemplate('My Form');
    addModuleVersion($template, 'locations', ['can_be_replaced' => true]);

    (new XlsformModuleImport($template))->collection(collect([
        collect(['module' => 'locations', 'type' => 'text', 'name' => 'q1', 'localisable_module' => null]),
    ]));

    expect($template->xlsformModules()->firstWhere('name', 'locations')->can_be_replaced)->toBeFalse();
});
