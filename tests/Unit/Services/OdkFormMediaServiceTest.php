<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        'name' => 'Media Test Team',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OdkProject::create([
        'id' => 99,
        'name' => 'Test ODK Project',
        'owner_type' => Team::class,
        'owner_id' => $teamId,
    ]);

    $this->templateId = DB::table('xlsform_templates')->insertGetId([
        'title' => 'Test Template',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $this->templateId,
        'owner_id' => $teamId,
        'title' => 'Test Survey',
        'odk_id' => 'test-form-001',
        'has_latest_template' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->xlsform = Xlsform::find($xlsformId);
});

function declareEntityList(int $templateId, string $listName): void
{
    DB::table('templates_entity_lists')->insert([
        'xlsform_template_id' => $templateId,
        'list_name' => $listName,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ─── linkEntityListAttachments ───────────────────────────────────────────────

it('links an unlinked file attachment matching a declared entity list to its dataset', function () {
    declareEntityList($this->templateId, 'Farm_Summary');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments/*' => Http::response(['success' => true], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments' => Http::response([
            ['name' => 'Farm_Summary.csv', 'type' => 'file', 'exists' => false, 'blobExists' => false, 'datasetExists' => false],
        ], 200),
    ]);

    app(OdkLinkService::class)->linkEntityListAttachments($this->xlsform);

    Http::assertSent(
        fn ($request) => $request->url() === 'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments/Farm_Summary.csv'
        && $request->method() === 'PATCH'
        && $request['dataset'] === true
    );
});

it('does not re-link an attachment that is already linked to a dataset', function () {
    declareEntityList($this->templateId, 'Farm_Summary');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments' => Http::response([
            ['name' => 'Farm_Summary.csv', 'type' => 'file', 'exists' => true, 'blobExists' => false, 'datasetExists' => true],
        ], 200),
    ]);

    app(OdkLinkService::class)->linkEntityListAttachments($this->xlsform);

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

it('ignores attachments that do not match a declared entity list or are not files', function () {
    declareEntityList($this->templateId, 'Farm_Summary');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments' => Http::response([
            ['name' => 'crops.csv', 'type' => 'file', 'exists' => false, 'blobExists' => false, 'datasetExists' => false],
            ['name' => 'Farm_Summary.png', 'type' => 'image', 'exists' => false, 'blobExists' => false, 'datasetExists' => false],
        ], 200),
    ]);

    app(OdkLinkService::class)->linkEntityListAttachments($this->xlsform);

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

it('logs a warning and does not throw when the dataset does not exist on ODK Central', function () {
    declareEntityList($this->templateId, 'Farm_Summary');

    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments/*' => Http::response(['message' => 'not found'], 404),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments' => Http::response([
            ['name' => 'Farm_Summary.csv', 'type' => 'file', 'exists' => false, 'blobExists' => false, 'datasetExists' => false],
        ], 200),
    ]);

    Log::spy();

    app(OdkLinkService::class)->linkEntityListAttachments($this->xlsform);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['attachment_name'] === 'Farm_Summary.csv');
});

it('makes no attachment requests when the form declares no entity lists', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
    ]);

    app(OdkLinkService::class)->linkEntityListAttachments($this->xlsform);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'draft/attachments'));
});

// ─── getDraftAttachments ─────────────────────────────────────────────────────

it('getDraftAttachments GETs the draft attachment list from ODK Central', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/draft/attachments' => Http::response([
            ['name' => 'Farm_Summary.csv', 'type' => 'file', 'exists' => false, 'blobExists' => false, 'datasetExists' => false],
        ], 200),
    ]);

    $result = app(OdkLinkService::class)->getDraftAttachments($this->xlsform);

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('Farm_Summary.csv');
});
