<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\PrepareSurveyRowPaths;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

// Builds the survey-row paths and repeat-group paths from the row ordering.
beforeEach(function () {
    // Each saved survey row fires a hook that touches the Xlsform global scope,
    // which calls Filament::hasTenancy(). Stub the registry so it resolves.
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);
    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);
    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->version = addModuleVersion(makeXlsformTemplate(), 'mod');

    $rows = [
        ['row_number' => 1, 'name' => 'g1', 'type' => 'begin_group'],
        ['row_number' => 2, 'name' => 'q1', 'type' => 'text'],
        ['row_number' => 3, 'name' => 'eg', 'type' => 'end_group'],
        ['row_number' => 4, 'name' => 'r1', 'type' => 'begin_repeat'],
        ['row_number' => 5, 'name' => 'q2', 'type' => 'text'],
        ['row_number' => 6, 'name' => 'er', 'type' => 'end_repeat'],
    ];

    foreach ($rows as $row) {
        DB::table('survey_rows')->insert([
            ...$row,
            'xlsform_module_version_id' => $this->version->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    (new PrepareSurveyRowPaths($this->version))->handle();

    $this->rows = SurveyRow::where('xlsform_module_version_id', $this->version->id)
        ->get()
        ->keyBy('name');
});

it('nests group children under the group path and unwinds at end_group', function () {
    expect($this->rows['g1']->path)->toBe('/g1/')
        ->and($this->rows['q1']->path)->toBe('/g1/q1')
        ->and($this->rows['eg']->path)->toBe('/');
});

it('resets the path inside a repeat and records the repeat_group_path', function () {
    expect($this->rows['r1']->path)->toBe('/')
        ->and($this->rows['r1']->repeat_group_path)->toBe('/r1/')
        ->and($this->rows['q2']->path)->toBe('/q2')
        ->and($this->rows['q2']->repeat_group_path)->toBe('/r1/');
});

it('clears the repeat_group_path after the repeat closes', function () {
    expect($this->rows['er']->path)->toBe('/')
        ->and($this->rows['er']->repeat_group_path)->toBeNull();
});
