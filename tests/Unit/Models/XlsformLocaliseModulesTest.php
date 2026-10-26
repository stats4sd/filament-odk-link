<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->teamId = DB::table('teams')->insertGetId(['name' => 'Team', 'created_at' => now(), 'updated_at' => now()]);
});

function addLocalVersion(int $teamId, XlsformModuleVersion $globalVersion, string $name): XlsformModuleVersion
{
    $id = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $globalVersion->xlsform_module_id,
        'owner_id' => $teamId,
        'name' => $name,
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return XlsformModuleVersion::find($id);
}

function makeFormWithGlobalVersions(XlsformTemplate $template, int $teamId, XlsformModuleVersion ...$versions): Xlsform
{
    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $teamId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($versions as $order => $version) {
        DB::table('selected_xlsform_module_versions')->insert([
            'xlsform_id' => $xlsformId,
            'xlsform_module_version_id' => $version->id,
            'order' => $order + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return Xlsform::find($xlsformId);
}

it('swaps in the local version that belongs to the form\'s own module, not one with the same name from another template', function () {
    $firstTemplate = makeXlsformTemplate('First');
    $secondTemplate = makeXlsformTemplate('Second');

    $firstGlobal = addModuleVersion($firstTemplate, 'locations', ['default_order' => 1, 'can_be_replaced' => true]);
    $secondGlobal = addModuleVersion($secondTemplate, 'locations', ['default_order' => 1, 'can_be_replaced' => true]);

    $firstLocal = addLocalVersion($this->teamId, $firstGlobal, 'Local locations');
    $secondLocal = addLocalVersion($this->teamId, $secondGlobal, 'Local locations');

    $xlsform = makeFormWithGlobalVersions($secondTemplate, $this->teamId, $secondGlobal);

    $xlsform->load('xlsformModuleVersions');
    $xlsform->localiseModules();

    $selectedIds = $xlsform->xlsformModuleVersions()->pluck('xlsform_module_versions.id')->all();

    expect($selectedIds)->toBe([$secondLocal->id]);
    expect($selectedIds)->not->toContain($firstLocal->id);
    expect($xlsform->xlsformModuleVersions()->first()->pivot->order)->toBe(1);
});

it('leaves a module that cannot be replaced on its global version', function () {
    $template = makeXlsformTemplate();
    $global = addModuleVersion($template, 'locations', ['default_order' => 1, 'can_be_replaced' => false]);
    addLocalVersion($this->teamId, $global, 'Local locations');

    $xlsform = makeFormWithGlobalVersions($template, $this->teamId, $global);

    $xlsform->load('xlsformModuleVersions');
    $xlsform->localiseModules();

    expect($xlsform->xlsformModuleVersions()->pluck('xlsform_module_versions.id')->all())->toBe([$global->id]);
});
