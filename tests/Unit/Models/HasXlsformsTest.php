<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

// Build the minimum FK chain: template → module → module_version → choice_list.
// We insert at the DB level to skip the complex booted() hooks on XlsformTemplate
// (which call OdkLinkService on created/saved).
//
// All model global scopes that call Filament::hasTenancy() (ChoiceListEntry, Xlsform)
// throw NoDefaultPanelSetException when no panel is configured. Mock the PanelRegistry
// so hasTenancy() returns false and getCurrentOwner() returns null throughout.
beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->team = Team::factory()->create();

    $templateId = DB::table('xlsform_templates')->insertGetId([
        'title' => 'Test Template',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moduleId = DB::table('xlsform_modules')->insertGetId([
        'xlsform_template_id' => $templateId,
        'label' => 'Mod',
        'name' => 'mod',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moduleVersionId = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $moduleId,
        'name' => 'default',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $choiceListId = DB::table('choice_lists')->insertGetId([
        'xlsform_module_version_id' => $moduleVersionId,
        'list_name' => 'testlist',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->choiceList = ChoiceList::find($choiceListId);
});

// ─── markLookupListAsComplete ─────────────────────────────────────────────────

it('markLookupListAsComplete writes the pivot row and returns true', function () {
    expect($this->team->markLookupListAsComplete($this->choiceList))->toBeTrue();

    $this->assertDatabaseHas('choice_list_owner', [
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
        'is_complete' => 1,
    ]);
});

it('markLookupListAsComplete is idempotent: a second call does not duplicate the pivot row', function () {
    $this->team->markLookupListAsComplete($this->choiceList);
    $this->team->markLookupListAsComplete($this->choiceList);

    $count = DB::table('choice_list_owner')
        ->where('owner_id', $this->team->id)
        ->where('choice_list_id', $this->choiceList->id)
        ->count();

    expect($count)->toBe(1);
});

// ─── markLookupListAsInComplete ───────────────────────────────────────────────

it('markLookupListAsInComplete removes the pivot row and returns null', function () {
    DB::table('choice_list_owner')->insert([
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
        'is_complete' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($this->team->markLookupListAsInComplete($this->choiceList))->toBeNull();

    $this->assertDatabaseMissing('choice_list_owner', [
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
    ]);
});

it('markLookupListAsInComplete is safe when no pivot row exists', function () {
    expect($this->team->markLookupListAsInComplete($this->choiceList))->toBeNull();

    $this->assertDatabaseMissing('choice_list_owner', [
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
    ]);
});

// ─── hasCompletedLookupList ───────────────────────────────────────────────────

it('hasCompletedLookupList returns true once the list is marked complete', function () {
    $this->team->markLookupListAsComplete($this->choiceList);

    expect($this->team->hasCompletedLookupList($this->choiceList))->toBeTrue();
});

it('hasCompletedLookupList returns null when the list has no pivot row', function () {
    expect($this->team->hasCompletedLookupList($this->choiceList))->toBeNull();
});

// ─── relationship wiring ──────────────────────────────────────────────────────

it('HasXlsforms exposes a datasets HasMany relationship', function () {
    expect($this->team->datasets())->toBeInstanceOf(HasMany::class);
});

it('HasXlsforms exposes a xlsforms HasMany relationship', function () {
    expect($this->team->xlsforms())->toBeInstanceOf(HasMany::class);
});

it('HasXlsforms exposes an odkProject MorphOne relationship', function () {
    expect($this->team->odkProject())->toBeInstanceOf(MorphOne::class);
});
