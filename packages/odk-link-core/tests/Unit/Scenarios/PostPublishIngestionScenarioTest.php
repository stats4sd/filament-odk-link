<?php

/*
 * Post-publish scenario, part B3 of docs/plans/2026-11-27-package-review-and-redesign.md.
 *
 * Each test states the CORRECT behaviour for one step a real user or a real
 * ODK Central can produce slightly off the main path. ODK Central is faked at
 * the HTTP layer; everything from the pull onwards is the real code, with the
 * queue running inline so the whole pull -> store -> project chain executes.
 *
 * Tests that fail today are skipped with the review finding they confirm
 * (docs/code-reviews/2026-11-27-package-structure-review.md, part 2). Remove
 * the skip when the finding is fixed. Run them as they are with:
 *
 *   ODK_SCENARIO_RUN_KNOWN_DEFECTS=1 vendor/bin/pest tests/Unit/Scenarios
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Stats4sd\OdkLink\Core\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\OdkLink\Core\Models\OdkLink\DatasetVariable;
use Stats4sd\OdkLink\Core\Models\OdkLink\Entity;
use Stats4sd\OdkLink\Core\Models\OdkLink\EntityValue;
use Stats4sd\OdkLink\Core\Models\OdkLink\OdkProject;
use Stats4sd\OdkLink\Core\Models\OdkLink\Submission;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;
use Stats4sd\OdkLink\Core\Models\OdkLink\XlsformVersion;
use Stats4sd\OdkLink\Core\Services\OdkLinkService;
use Stats4sd\OdkLink\Core\Tests\Models\Team;

const SCENARIO_FORM_VERSION = '2026010101';

/** Skip a test that documents a known defect, unless the run asks for them. */
function knownDefect(string $finding): bool
{
    return ! getenv('ODK_SCENARIO_RUN_KNOWN_DEFECTS');
}

/**
 * One submission as ODK Central's two endpoints describe it.
 *
 * @param  array<string, mixed>  $answers
 * @return array{meta: array<string, mixed>, odata: array<string, mixed>}
 */
function centralSubmission(string $instanceId, array $answers, ?string $revisionId = null, string $formVersion = SCENARIO_FORM_VERSION): array
{
    return [
        'meta' => ['instanceId' => $instanceId, 'currentVersion' => ['instanceId' => $revisionId ?? $instanceId]],
        'odata' => array_merge([
            '__id' => $instanceId,
            '__system' => [
                'formVersion' => $formVersion,
                'submissionDate' => '2026-06-01T10:00:00.000Z',
                'submitterName' => 'Field Worker',
                'attachmentsPresent' => 0,
            ],
        ], $answers),
    ];
}

/** Point the faked ODK Central at a new set of submissions. */
function centralNowHolds(array $submissions): void
{
    test()->central->submissions = $submissions;
}

function pollForm(): int
{
    return app(OdkLinkService::class)->getSubmissions(test()->xlsform->fresh());
}

function entityCount(): int
{
    return Entity::query()->count();
}

function storedValue(string $variable): ?string
{
    return EntityValue::query()->where('dataset_variable_name', $variable)->value('value');
}

beforeEach(function () {
    Cache::flush();
    config()->set('queue.default', 'sync');

    $team = Team::createQuietly(['name' => 'Scenario Team']);

    OdkProject::create(['id' => 99, 'name' => 'Scenario Project', 'owner_type' => Team::class, 'owner_id' => $team->id]);

    $template = makeXlsformTemplate('Scenario Template');

    $this->datasetId = DB::table('datasets')->insertGetId(['name' => 'scenario-households']);

    foreach (['age', 'livestock_count', 'location'] as $variable) {
        DB::table('dataset_variables')->insert([
            'dataset_id' => $this->datasetId, 'name' => $variable, 'label' => $variable, 'type' => 'text',
        ]);
    }

    DB::table('xlsform_template_sections')->insert([
        'xlsform_template_id' => $template->id,
        'dataset_id' => $this->datasetId,
        'structure_item' => 'root',
        'schema' => json_encode([
            ['name' => 'age', 'path' => '/age', 'type' => 'integer', 'value_type' => 'integer'],
            ['name' => 'livestock_count', 'path' => '/livestock_count', 'type' => 'integer', 'value_type' => 'integer'],
            ['name' => 'location', 'path' => '/location', 'type' => 'geopoint', 'value_type' => 'geopoint'],
        ]),
    ]);

    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => 'Scenario Survey',
        'odk_id' => 'scenario-form',
        'has_latest_template' => 1,
        'is_active' => 1,
    ]);

    $this->versionId = DB::table('xlsform_versions')->insertGetId([
        'xlsform_id' => $xlsformId,
        'version' => SCENARIO_FORM_VERSION,
        'odk_version' => SCENARIO_FORM_VERSION,
        'active' => 1,
    ]);

    $this->team = $team;
    $this->xlsform = Xlsform::findOrFail($xlsformId);

    // A stateful fake: tests change what Central "holds" between polls.
    $this->central = new class
    {
        /** @var array<int, array{meta: array<string, mixed>, odata: array<string, mixed>}> */
        public array $submissions = [];
    };

    $central = $this->central;

    Http::fake(function ($request) use ($central) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/sessions') => Http::response(['token' => 'fake'], 200),
            str_contains($url, '.svc/Submissions') => Http::response(['value' => array_column($central->submissions, 'odata')], 200),
            str_ends_with($url, '/forms/scenario-form/submissions') => Http::response(array_column($central->submissions, 'meta'), 200),
            default => Http::response(['message' => 'not faked: ' . $url], 404),
        };
    });
});

// ─── Baseline: the main path ─────────────────────────────────────────────────

it('step 1: stores a new submission and projects it into one entity', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);

    expect(pollForm())->toBe(1)
        ->and(Submission::query()->count())->toBe(1)
        ->and(entityCount())->toBe(1)
        ->and(storedValue('age'))->toBe('34');
});

it('step 2: does nothing when the same poll runs again', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);

    pollForm();

    expect(pollForm())->toBe(0)
        ->and(entityCount())->toBe(1);
});

it('step 3: keeps an answer of zero', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34', 'livestock_count' => 0])]);

    pollForm();

    expect(EntityValue::query()->where('dataset_variable_name', 'livestock_count')->exists())->toBeTrue();
});

// ─── Slightly off the main path ──────────────────────────────────────────────

it('step 4: replaces the entity when a submission is edited on Central', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);
    pollForm();

    centralNowHolds([centralSubmission('uuid-1', ['age' => '35'], revisionId: 'uuid-1-rev2')]);
    pollForm();

    expect(Submission::query()->count())->toBe(1)
        ->and(entityCount())->toBe(1)
        ->and(storedValue('age'))->toBe('35');
})->skip(fn () => knownDefect('P8'), 'Known defect P8: an edited submission is projected twice.');

it('step 5: projects once when the processing job is retried', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);
    pollForm();

    $submission = Submission::query()->sole();

    // What a queue worker does after a failed or interrupted attempt.
    (new ProcessOdkSubmission($submission, $submission->content, XlsformVersion::findOrFail($this->versionId)))->handle();

    expect(entityCount())->toBe(1);
})->skip(fn () => knownDefect('P8'), 'Known defect P8: a retried job adds a second entity tree.');

it('step 6: still ingests other submissions when one has an unknown form version', function () {
    centralNowHolds([
        centralSubmission('uuid-edited-on-central', ['age' => '20'], formVersion: 'published-directly-on-central'),
        centralSubmission('uuid-good', ['age' => '34']),
    ]);

    try {
        pollForm();
    } catch (Throwable) {
        // The batch may report the problem, but it must not lose the good submission.
    }

    expect(Submission::query()->where('odk_id', 'uuid-good')->exists())->toBeTrue();
})->skip(fn () => knownDefect('P13'), 'Known defect P13: one unknown form version blocks every later submission, on every poll.');

it('step 7: stores a location answer', function () {
    // ODK Central's OData feed returns geopoints as GeoJSON objects unless $wkt=true is asked for.
    centralNowHolds([centralSubmission('uuid-1', [
        'age' => '34',
        'location' => ['type' => 'Point', 'coordinates' => [36.8219, -1.2921, 1661]],
    ])]);

    pollForm();

    expect(EntityValue::query()->where('dataset_variable_name', 'location')->exists())->toBeTrue();
})->skip(fn () => knownDefect('P11'), 'Known defect P11: location answers are dropped from entities.');

it('step 8: removes a submission that was deleted on Central', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34']), centralSubmission('uuid-2', ['age' => '50'])]);
    pollForm();

    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);
    pollForm();

    expect(Submission::query()->where('odk_id', 'uuid-2')->exists())->toBeFalse()
        ->and(entityCount())->toBe(1);
})->skip(fn () => knownDefect('P15'), 'Known defect P15: submissions deleted on Central stay on the platform.');

it('step 9: keeps polling after a locally deleted submission is edited on Central', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);
    pollForm();

    Submission::query()->sole()->delete();

    centralNowHolds([
        centralSubmission('uuid-1', ['age' => '35'], revisionId: 'uuid-1-rev2'),
        centralSubmission('uuid-2', ['age' => '50']),
    ]);

    try {
        pollForm();
    } catch (Throwable) {
        // Whatever the policy for the deleted one, the new submission must arrive.
    }

    expect(Submission::query()->where('odk_id', 'uuid-2')->exists())->toBeTrue();
})->skip(fn () => knownDefect('P14'), 'Known defect P14: a soft-deleted submission edited on Central makes every poll fail.');

it('step 10: does not project into entities when the root section has no dataset, and says why', function () {
    DB::table('xlsform_template_sections')->update(['dataset_id' => null]);
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);

    $thrown = null;

    try {
        pollForm();
    } catch (Throwable $e) {
        $thrown = $e;
    }

    // A missing mapping is a configuration problem to report, not a PHP error.
    expect($thrown)->not->toBeInstanceOf(Error::class)
        ->and($thrown)->not->toBeInstanceOf(ErrorException::class);
})->skip(fn () => knownDefect('P9'), 'Known defect P9: an unmapped root section fails every submission with a null dereference.');

// ─── Two owners ──────────────────────────────────────────────────────────────

it('step 11: keeps the values of two datasets apart when they share a variable name', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);
    pollForm();

    $otherDatasetId = DB::table('datasets')->insertGetId(['name' => 'another-teams-dataset']);
    $otherVariableId = DB::table('dataset_variables')->insertGetId([
        'dataset_id' => $otherDatasetId, 'name' => 'age', 'label' => 'Age', 'type' => 'text',
    ]);

    // The other dataset has no entities at all, so its "age" variable has no values.
    expect(DatasetVariable::findOrFail($otherVariableId)->values()->count())->toBe(0);
})->skip(fn () => knownDefect('P3'), 'Known defect P3: values are joined on variable name only, across datasets and owners.');

// ─── The Enketo return route ─────────────────────────────────────────────────

it('step 12: refuses the submission update route to a guest without calling Central', function () {
    centralNowHolds([centralSubmission('uuid-1', ['age' => '34'])]);
    pollForm();

    $requestsBefore = count(Http::recorded());

    $response = $this->get(route('submission.update', ['submission' => Submission::query()->sole()->id]));

    expect($response->status())->toBeIn([401, 403, 302])
        ->and(count(Http::recorded()))->toBe($requestsBefore);
})->skip(fn () => knownDefect('P2'), 'Known defect P2: a guest can trigger a call to Central with platform credentials.');
