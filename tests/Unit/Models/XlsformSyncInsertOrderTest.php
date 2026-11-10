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
    $this->template = makeXlsformTemplate();
});

function insertFormWithOrders(XlsformTemplate $template, int $teamId, array $orderPerVersionId): Xlsform
{
    $formId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $teamId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($orderPerVersionId as $versionId => $order) {
        DB::table('selected_xlsform_module_versions')->insert([
            'xlsform_id' => $formId,
            'xlsform_module_version_id' => $versionId,
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return Xlsform::find($formId);
}

function selectedNamesInOrder(Xlsform $xlsform): array
{
    return $xlsform->xlsformModuleVersions()->get()->pluck('name')->all();
}

function selectedOrders(Xlsform $xlsform): array
{
    return $xlsform->xlsformModuleVersions()->get()->pluck('pivot.order')->all();
}

it('slots a module added to the template after the module that precedes it, not on top of the next one', function () {
    $metadata = addModuleVersion($this->template, 'metadata', ['default_order' => 1]);
    addModuleVersion($this->template, 'introduction', ['default_order' => 2]);
    $locations = addModuleVersion($this->template, 'locations', ['default_order' => 3]);
    $farmInfo = addModuleVersion($this->template, 'farm info', ['default_order' => 4]);

    $xlsform = insertFormWithOrders($this->template, $this->teamId, [$metadata->id => 1, $locations->id => 2, $farmInfo->id => 3]);

    $xlsform->syncWithTemplate();

    expect(selectedNamesInOrder($xlsform))->toBe(['Global metadata', 'Global introduction', 'Global locations', 'Global farm info'])
        ->and(selectedOrders($xlsform))->toBe([1, 2, 3, 4]);
});

it('keeps the relative order of a form the team has re-ordered', function () {
    $metadata = addModuleVersion($this->template, 'metadata', ['default_order' => 1]);
    addModuleVersion($this->template, 'introduction', ['default_order' => 2]);
    $locations = addModuleVersion($this->template, 'locations', ['default_order' => 3]);
    $farmInfo = addModuleVersion($this->template, 'farm info', ['default_order' => 4]);

    $xlsform = insertFormWithOrders($this->template, $this->teamId, [$farmInfo->id => 0, $metadata->id => 1, $locations->id => 2]);

    $xlsform->syncWithTemplate();

    expect(selectedNamesInOrder($xlsform))->toBe(['Global farm info', 'Global metadata', 'Global introduction', 'Global locations']);
});

it('leaves room for the local version of an extendable module that is added', function () {
    $metadata = addModuleVersion($this->template, 'metadata', ['default_order' => 1]);
    addModuleVersion($this->template, 'hdds', ['default_order' => 2, 'can_be_extended' => true]);
    $locations = addModuleVersion($this->template, 'locations', ['default_order' => 4]);

    $xlsform = insertFormWithOrders($this->template, $this->teamId, [$metadata->id => 1, $locations->id => 2]);

    $xlsform->syncWithTemplate();

    expect(selectedNamesInOrder($xlsform))->toBe(['Global metadata', 'Global hdds', 'Local hdds', 'Global locations'])
        ->and(selectedOrders($xlsform))->toBe([1, 2, 3, 4]);
});

it('places a new module after the local version of the module that precedes it', function () {
    $hdds = addModuleVersion($this->template, 'hdds', ['default_order' => 1, 'can_be_extended' => true]);
    addModuleVersion($this->template, 'introduction', ['default_order' => 3]);
    $locations = addModuleVersion($this->template, 'locations', ['default_order' => 4]);

    $localHddsId = DB::table('xlsform_module_versions')->insertGetId([
        'owner_id' => $this->teamId,
        'name' => 'Local hdds',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xlsform = insertFormWithOrders($this->template, $this->teamId, [$hdds->id => 1, $localHddsId => 2, $locations->id => 3]);

    $xlsform->syncWithTemplate();

    expect(selectedNamesInOrder($xlsform))->toBe(['Global hdds', 'Local hdds', 'Global introduction', 'Global locations']);
});

it('puts a module that comes first in the template before the modules the form already has', function () {
    addModuleVersion($this->template, 'metadata', ['default_order' => 1]);
    $locations = addModuleVersion($this->template, 'locations', ['default_order' => 2]);
    $farmInfo = addModuleVersion($this->template, 'farm info', ['default_order' => 3]);

    $xlsform = insertFormWithOrders($this->template, $this->teamId, [$locations->id => 2, $farmInfo->id => 3]);

    $xlsform->syncWithTemplate();

    expect(selectedNamesInOrder($xlsform))->toBe(['Global metadata', 'Global locations', 'Global farm info']);
});

it('does not move a module the form already has, including a team-owned version of it', function () {
    $metadata = addModuleVersion($this->template, 'metadata', ['default_order' => 1]);
    $introduction = addModuleVersion($this->template, 'introduction', ['default_order' => 2]);

    $clonedIntroductionId = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $introduction->xlsform_module_id,
        'owner_id' => $this->teamId,
        'name' => 'introduction - Team',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xlsform = insertFormWithOrders($this->template, $this->teamId, [$metadata->id => 5, $clonedIntroductionId => 9]);

    $xlsform->syncWithTemplate();

    expect(selectedNamesInOrder($xlsform))->toBe(['Global metadata', 'introduction - Team'])
        ->and(selectedOrders($xlsform))->toBe([5, 9])
        ->and(XlsformModuleVersion::find($introduction->id)->is_default)->toBeTrue();
});
