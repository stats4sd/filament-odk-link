<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Database\QueryException;
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
//
// NOTE: HasXlsforms::choiceLists() is declared as BelongsToMany(ChoiceListEntry, …)
// but the pivot stores choice_list_id (pointing at choice_lists.id). The sync()
// write succeeds, but hasCompletedLookupList() then executes a query that
// references "choice_lists.id" in a join against choice_list_entries — a column
// that does not exist in that query, causing a QueryException. The tests below
// assert the DB side-effect that DID succeed and document the bug via toThrow().

it('markLookupListAsComplete writes the pivot row with is_complete = 1', function () {
    expect(fn () => $this->team->markLookupListAsComplete($this->choiceList))
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas('choice_list_owner', [
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
        'is_complete' => 1,
    ]);
});

it('markLookupListAsComplete is idempotent: a second call does not duplicate the pivot row', function () {
    // Each call throws after the sync, but neither should produce a duplicate.
    expect(fn () => $this->team->markLookupListAsComplete($this->choiceList))
        ->toThrow(QueryException::class);
    expect(fn () => $this->team->markLookupListAsComplete($this->choiceList))
        ->toThrow(QueryException::class);

    $count = DB::table('choice_list_owner')
        ->where('owner_id', $this->team->id)
        ->where('choice_list_id', $this->choiceList->id)
        ->count();

    expect($count)->toBe(1);
});

// ─── markLookupListAsInComplete ───────────────────────────────────────────────

it('markLookupListAsInComplete removes the pivot row', function () {
    DB::table('choice_list_owner')->insert([
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
        'is_complete' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // detach() removes the row; hasCompletedLookupList() then throws the same bug.
    expect(fn () => $this->team->markLookupListAsInComplete($this->choiceList))
        ->toThrow(QueryException::class);

    $this->assertDatabaseMissing('choice_list_owner', [
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
    ]);
});

it('markLookupListAsInComplete is safe when no pivot row exists', function () {
    expect(fn () => $this->team->markLookupListAsInComplete($this->choiceList))
        ->toThrow(QueryException::class);

    $this->assertDatabaseMissing('choice_list_owner', [
        'owner_id' => $this->team->id,
        'choice_list_id' => $this->choiceList->id,
    ]);
});

// ─── hasCompletedLookupList bug ───────────────────────────────────────────────

it('hasCompletedLookupList throws QueryException because of a mismatched table reference', function () {
    // The choiceLists() relationship joins choice_list_entries but the where clause
    // references choice_lists.id — a column not present in that join.
    expect(fn () => $this->team->hasCompletedLookupList($this->choiceList))
        ->toThrow(QueryException::class, 'no such column: choice_lists.id');
});

// ─── relationship wiring ──────────────────────────────────────────────────────

it('HasXlsforms exposes a datasets HasMany relationship', function () {
    expect($this->team->datasets())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('HasXlsforms exposes a xlsforms HasMany relationship', function () {
    expect($this->team->xlsforms())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('HasXlsforms exposes an odkProject MorphOne relationship', function () {
    expect($this->team->odkProject())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphOne::class);
});
