<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\PullSubmissionsFromXlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $teamId = DB::table('teams')->insertGetId([
        'name' => 'Pull Test Team',
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
        'odk_id' => 'test-form-001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->xlsform = Xlsform::find($xlsformId);
});

it('delegates to OdkLinkService::getSubmissions for its xlsform', function () {
    $service = Mockery::mock(OdkLinkService::class);
    $service->shouldReceive('getSubmissions')
        ->once()
        ->with(Mockery::on(fn (Xlsform $form) => $form->id === $this->xlsform->id));
    app()->instance(OdkLinkService::class, $service);

    (new PullSubmissionsFromXlsform($this->xlsform))->handle();
});
