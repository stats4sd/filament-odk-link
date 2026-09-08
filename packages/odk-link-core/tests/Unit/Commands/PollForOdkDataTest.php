<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Stats4sd\FilamentOdkLink\Jobs\PullSubmissionsFromXlsform;

// Xlsform's 'owned' global scope calls Filament::hasTenancy() → PanelRegistry::getDefault()
// which throws NoDefaultPanelSetException when no Filament panel is configured.
// Mock the registry so hasTenancy() returns false and the scope is a no-op.
beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    Queue::fake();

    $teamId = DB::table('teams')->insertGetId([
        'name' => 'Test Team',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $templateId = DB::table('xlsform_templates')->insertGetId([
        'title' => 'Survey',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->teamId = $teamId;
    $this->templateId = $templateId;
});

it('dispatches PullSubmissionsFromXlsform for every active xlsform', function () {
    $activeId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $this->templateId,
        'owner_id' => $this->teamId,
        'is_active' => 1,
        'title' => 'Active Survey',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Reset fake so only the artisan command's dispatches count.
    Queue::fake();

    $this->artisan('odk:poll-for-odk-data')->assertSuccessful();

    Queue::assertPushed(PullSubmissionsFromXlsform::class, 1);
    Queue::assertPushed(
        PullSubmissionsFromXlsform::class,
        fn ($job) => $job->xlsform->id === $activeId
    );
});

it('does not dispatch PullSubmissionsFromXlsform for inactive xlsforms', function () {
    DB::table('xlsforms')->insert([
        'xlsform_template_id' => $this->templateId,
        'owner_id' => $this->teamId,
        'is_active' => 0,
        'title' => 'Inactive Survey',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Queue::fake();

    $this->artisan('odk:poll-for-odk-data')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dispatches jobs only for active forms when both exist', function () {
    $activeId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $this->templateId,
        'owner_id' => $this->teamId,
        'is_active' => 1,
        'title' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('xlsforms')->insert([
        'xlsform_template_id' => $this->templateId,
        'owner_id' => $this->teamId,
        'is_active' => 0,
        'title' => 'Inactive',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Queue::fake();

    $this->artisan('odk:poll-for-odk-data')->assertSuccessful();

    Queue::assertPushed(PullSubmissionsFromXlsform::class, 1);
    Queue::assertPushed(
        PullSubmissionsFromXlsform::class,
        fn ($job) => $job->xlsform->id === $activeId
    );
});
