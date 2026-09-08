<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

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
        'name' => 'Submission Test Team',
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

    DB::table('xlsform_versions')->insert([
        'xlsform_id' => $xlsformId,
        'version' => '2026010101',
        'odk_version' => '2026010101',
        'active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->xlsform = Xlsform::find($xlsformId);
});

// ─── getSubmissionCount ───────────────────────────────────────────────────────

it('getSubmissionCount returns the count of submissions returned by ODK Central', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/submissions' => Http::response([
            ['instanceId' => 'uuid1'],
            ['instanceId' => 'uuid2'],
            ['instanceId' => 'uuid3'],
        ], 200),
    ]);

    $count = app(OdkLinkService::class)->getSubmissionCount($this->xlsform);

    expect($count)->toBe(3);
});

it('getSubmissionCount returns null when ODK Central responds with a non-200 status', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/submissions' => Http::response([], 503),
    ]);

    $count = app(OdkLinkService::class)->getSubmissionCount($this->xlsform);

    expect($count)->toBeNull();
});

it('getSubmissionCount returns 0 when the form has no submissions', function () {
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/submissions' => Http::response([], 200),
    ]);

    $count = app(OdkLinkService::class)->getSubmissionCount($this->xlsform);

    expect($count)->toBe(0);
});

// ─── makeMultiSelectBooleans ──────────────────────────────────────────────────

it('makeMultiSelectBooleans returns an empty array for a non-select_multiple schema item', function () {
    $entity = new Entity;
    $schemaItem = ['name' => 'age', 'value_type' => 'integer'];

    $result = app(OdkLinkService::class)->makeMultiSelectBooleans($entity, $schemaItem, new EloquentCollection, '25');

    expect($result)->toBe([]);
});

it('makeMultiSelectBooleans returns an empty array when value_type key is absent', function () {
    $entity = new Entity;
    $schemaItem = ['name' => 'notes'];

    $result = app(OdkLinkService::class)->makeMultiSelectBooleans($entity, $schemaItem, new EloquentCollection, 'some text');

    expect($result)->toBe([]);
});

// ─── getSubmissions ───────────────────────────────────────────────────────────

// A new submission whose form version matches a stored XlsformVersion is
// persisted and queued for processing.
function fakeOdkSubmissions(string $instanceId, string $version): void
{
    Http::fake([
        'https://odk.test/v1/sessions' => Http::response(['token' => 'fake'], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001/submissions' => Http::response([
            ['instanceId' => $instanceId, 'currentVersion' => ['instanceId' => $instanceId]],
        ], 200),
        'https://odk.test/v1/projects/99/forms/test-form-001.svc/Submissions*' => Http::response([
            'value' => [
                [
                    '__id' => $instanceId,
                    '__system' => [
                        'formVersion' => $version,
                        'submissionDate' => '2026-06-01T10:00:00.000Z',
                        'submitterName' => 'Field Worker',
                        'attachmentsPresent' => 0,
                    ],
                    'age' => '34',
                ],
            ],
        ], 200),
    ]);
}

it('getSubmissions persists a new submission and dispatches ProcessOdkSubmission', function () {
    fakeOdkSubmissions('uuid-new-1', '2026010101');

    $added = app(OdkLinkService::class)->getSubmissions($this->xlsform);

    expect($added)->toBe(1);

    $submission = Submission::withoutGlobalScopes()->where('odk_id', 'uuid-new-1')->first();
    expect($submission)->not->toBeNull()
        ->and($submission->submitted_by)->toBe('Field Worker');

    Queue::assertPushed(ProcessOdkSubmission::class, 1);
});

it('getSubmissions skips submissions already ingested', function () {
    // Seed an existing submission with the same odk_id ODK Central will return.
    DB::table('submissions')->insert([
        'odk_id' => 'uuid-existing-1',
        // Match the currentVersion ODK Central will report, so the submission
        // is treated as already-ingested rather than edited-since.
        'odk_latest_version_id' => 'uuid-existing-1',
        'xlsform_version_id' => $this->xlsform->xlsformVersions()->first()->id,
        'submitted_at' => now(),
        'content' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    fakeOdkSubmissions('uuid-existing-1', '2026010101');

    $added = app(OdkLinkService::class)->getSubmissions($this->xlsform);

    expect($added)->toBe(0);
    Queue::assertNotPushed(ProcessOdkSubmission::class);
});

it('getSubmissions throws when the submission references an unknown form version', function () {
    fakeOdkSubmissions('uuid-bad-version', '9999999999');

    expect(fn () => app(OdkLinkService::class)->getSubmissions($this->xlsform))
        ->toThrow(Exception::class);
});
