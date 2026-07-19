<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

// Xlsform's global scope calls Filament::hasTenancy(), which throws
// NoDefaultPanelSetException with no panel registered in this package's
// isolated Testbench environment - mock the registry so it returns false,
// matching the same pattern used in tests/Unit/Models/HasXlsformsTest.php.
beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->team = Team::factory()->create();
});

// Inserted at the DB level (not Xlsform::create()) to skip the model's heavy
// booted() hooks (ODK Central calls, media, auto-syncWithTemplate on save) -
// same convention as makeXlsformTemplate()/addModuleVersion() in tests/Pest.php.
function makeXlsformFor(XlsformTemplate $template, Team $team): Xlsform
{
    $id = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Xlsform::find($id);
}

it('attaches only the global default version for a plain module', function () {
    $template = makeXlsformTemplate();
    addModuleVersion($template, 'Metadata', ['default_order' => 1]);

    $xlsform = makeXlsformFor($template, $this->team);
    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(1);
    expect($versions->first()->name)->toBe('Global Metadata');
    expect($versions->first()->pivot->order)->toBe(1);
});

it('attaches both the global and a local version when can_be_extended is true', function () {
    $template = makeXlsformTemplate();
    addModuleVersion($template, 'HDDS', ['default_order' => 1, 'can_be_extended' => true]);

    $xlsform = makeXlsformFor($template, $this->team);
    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(2);
    expect($versions[0]->name)->toBe('Global HDDS');
    expect($versions[0]->pivot->order)->toBe(1);
    expect($versions[1]->name)->toBe('Local HDDS');
    expect($versions[1]->pivot->order)->toBe(2);
    expect($versions[1]->owner_id)->toBe($this->team->id);
});

it('attaches a local version if exists when can_be_replaced is true, and falls back to the global default if a local version doesnt exist', function () {
    $template = makeXlsformTemplate();
    $globalVersion = addModuleVersion($template, 'locations', ['default_order' => 1, 'can_be_replaced' => true]);

    $xlsform = makeXlsformFor($template, $this->team);


    // without a local version of the module to replace it with, check that the Xlsform defaults to the Global module.
    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(1);
    expect($versions->first()->name)->toBe('Global locations');
    expect($versions->first()->owner_id)->toBe(null);
    expect($versions->first()->pivot->order)->toBe(1);

    XlsformModuleVersion::create([
        'xlsform_module_id' => $globalVersion->id,
        'name' => 'Local locations',
        'owner_id' => $this->team->id,
    ]);

    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(1);
    expect($versions->first()->name)->toBe('Local locations');
    expect($versions->first()->owner_id)->toBe($this->team->id);
    expect($versions->first()->pivot->order)->toBe(1);
});
