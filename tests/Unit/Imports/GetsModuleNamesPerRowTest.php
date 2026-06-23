<?php

use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\GetsModuleNamesPerRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

// A bare host for the trait under test.
function moduleResolver(): object
{
    return new class
    {
        use GetsModuleNamesPerRow;
    };
}

it('returns the model unchanged when it is already an XlsformModuleVersion', function () {
    $version = new XlsformModuleVersion;
    $version->id = 99;

    $result = moduleResolver()->getModuleVersionAndNameFromRow(collect(['type' => 'text', 'name' => 'q1']), $version);

    expect($result)->toBe($version);
});

it('resolves the module version by the row module column when present', function () {
    $template = makeXlsformTemplate();
    $intro = addModuleVersion($template, 'intro');
    addModuleVersion($template, 'demographics');

    $template->load('xlsformModules.defaultXlsformVersion');

    $row = collect(['module' => 'intro', 'type' => 'text', 'name' => 'q1']);

    $result = moduleResolver()->getModuleVersionAndNameFromRow($row, $template);

    expect($result->id)->toBe($intro->id);
});

it('falls back to matching a generic module by type_name in row_names when no module column is set', function () {
    $template = makeXlsformTemplate();

    // The generic module declares it owns the "text_q1" row via row_names.
    $generic = addModuleVersion($template, 'generic', [
        'row_names' => json_encode(['text_q1', 'integer_q2']),
    ]);
    addModuleVersion($template, 'other', [
        'row_names' => json_encode(['text_other']),
    ]);

    $template->load('xlsformModules.defaultXlsformVersion');

    $row = collect(['type' => 'text', 'name' => 'q1']);

    $result = moduleResolver()->getModuleVersionAndNameFromRow($row, $template);

    expect($result->id)->toBe($generic->id);
});
