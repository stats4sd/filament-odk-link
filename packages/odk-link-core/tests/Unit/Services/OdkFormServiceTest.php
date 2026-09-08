<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

// Build a minimal Xlsform graph via DB-level inserts to skip the complex booted() hooks
// (XlsformTemplate::created → OdkLinkService calls; Xlsform::created → setup() dispatch).
// The PanelRegistry mock stops the 'owned' global scope from throwing when Filament is queried.

beforeEach(function () {
    Cache::flush();
    Queue::fake();

    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $teamId = DB::table('teams')->insertGetId([
        'name' => 'Form Test Team',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OdkProject::create([
        'id' => 99,
        'name' => 'Test ODK Project',
        'owner_type' => Team::class,
        'owner_id' => $teamId,
    ]);

    $templateId = DB::table('xlsform_templates')->insertGetId([
        'title' => 'Test Template',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $templateId,
        'owner_id' => $teamId,
        'title' => 'Test Survey',
        'odk_id' => 'test-form-001',
        'has_latest_template' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->xlsform = Xlsform::find($xlsformId);
});

// ─── getXlsformDraftDetails ───────────────────────────────────────────────────

it('getXlsformDraftDetails GETs the draft from ODK Central and returns the decoded JSON', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft' => Http::response([
            'xmlFormId' => 'test-form-001',
            'draftToken' => 'tok123',
            'version' => '2024-01-01',
            'enketoId' => 'enketo-abc',
            'updatedAt' => '2024-01-01T00:00:00Z',
        ], 200),
    ]);

    $result = app(OdkLinkService::class)->getXlsformDraftDetails($this->xlsform);

    expect($result['xmlFormId'])->toBe('test-form-001')
        ->and($result['draftToken'])->toBe('tok123')
        ->and($result['enketoId'])->toBe('enketo-abc');

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects/99/forms/test-form-001/draft'
    );
});

// ─── archiveForm ─────────────────────────────────────────────────────────────

it('archiveForm PATCHes state=closed to the form URL', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001' => Http::response(['state' => 'closed'], 200),
    ]);

    app(OdkLinkService::class)->archiveForm($this->xlsform);

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects/99/forms/test-form-001'
        && $req['state'] === 'closed'
    );
});

it('archiveForm sets xlsform.is_active to false in the database', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001' => Http::response(['state' => 'closed'], 200),
    ]);

    app(OdkLinkService::class)->archiveForm($this->xlsform);

    $this->assertDatabaseHas('xlsforms', [
        'id' => $this->xlsform->id,
        'is_active' => 0,
    ]);
});

// ─── unArchiveForm ────────────────────────────────────────────────────────────

it('unArchiveForm PATCHes state=open to the form URL', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001' => Http::response(['state' => 'open'], 200),
    ]);

    app(OdkLinkService::class)->unArchiveForm($this->xlsform);

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects/99/forms/test-form-001'
        && $req['state'] === 'open'
    );
});

// ─── deleteForm ───────────────────────────────────────────────────────────────

it('deleteForm DELETEs the form on ODK Central and returns true', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001' => Http::response([], 200),
    ]);

    $result = app(OdkLinkService::class)->deleteForm($this->xlsform);

    expect($result)->toBeTrue();
    Http::assertSent(fn ($req) =>
        $req->url() === 'https://odk.test/v1/projects/99/forms/test-form-001'
        && $req->method() === 'DELETE'
    );
});

it('deleteForm returns true without re-throwing when ODK Central responds 404', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001' => Http::response(['message' => 'not found'], 404),
    ]);

    $result = app(OdkLinkService::class)->deleteForm($this->xlsform);

    expect($result)->toBeTrue();
});
