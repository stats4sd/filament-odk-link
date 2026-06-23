<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

// Submission's 'owned' global scope calls Filament::hasTenancy() → PanelRegistry::getDefault(),
// which throws when no panel is registered. Stub the registry so the scope is a no-op.
beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $teamId = DB::table('teams')->insertGetId([
        'name' => 'Controller Test Team',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $templateId = DB::table('xlsform_templates')->insertGetId([
        'title' => 'Template',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $templateId,
        'owner_id' => $teamId,
        'title' => 'Survey',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $versionId = DB::table('xlsform_versions')->insertGetId([
        'xlsform_id' => $xlsformId,
        'version' => '1',
        'odk_version' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->submissionId = DB::table('submissions')->insertGetId([
        'odk_id' => 'uuid-submission-1',
        'xlsform_version_id' => $versionId,
        'submitted_at' => now(),
        'content' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('calls updateSubmission for the bound submission and redirects to the default return url', function () {
    $service = Mockery::mock(OdkLinkService::class);
    $service->shouldReceive('updateSubmission')
        ->once()
        ->with(Mockery::on(fn (Submission $s) => $s->id === $this->submissionId));
    app()->instance(OdkLinkService::class, $service);

    $this->get(route('submission.update', ['submission' => $this->submissionId]))
        ->assertRedirect('/');
});

it('redirects to the stored submission_return_url when one is set in the session', function () {
    $service = Mockery::mock(OdkLinkService::class);
    $service->shouldReceive('updateSubmission')->once();
    app()->instance(OdkLinkService::class, $service);

    $this->withSession(['submission_return_url' => '/back/here'])
        ->get(route('submission.update', ['submission' => $this->submissionId]))
        ->assertRedirect('/back/here');
});
