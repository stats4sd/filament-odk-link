<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

uses(RefreshDatabase::class);

class PersistedSubmissionEvidence
{
    public int $calls = 0;

    public function __construct(public int $submissionId, public int $ownerId) {}
}

class PersistedSubmissionProcessor implements SubmissionProcessor
{
    public function __construct(private PersistedSubmissionEvidence $evidence) {}

    public function process(Submission $submission): void
    {
        expect($submission->exists)->toBeTrue()
            ->and($submission->getKey())->toBe($this->evidence->submissionId)
            ->and($submission->fresh()->content['age'])->toBe('34');
        $entity = Entity::where('submission_id', $submission->getKey())->sole();
        expect($entity->owner_id)->toBe($this->evidence->ownerId)
            ->and($entity->values()->where('dataset_variable_name', 'age')->sole()->value)->toBe('34');
        $this->evidence->calls++;
    }
}

it('invokes an injected processor after real submission entities and values are persisted', function () {
    Http::preventStrayRequests();
    $owner = Team::createQuietly(['name' => 'Headless owner']);
    $template = makeXlsformTemplate();
    $formId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $owner->id,
        'title' => 'Submitted survey',
    ]);
    $versionId = DB::table('xlsform_versions')->insertGetId([
        'xlsform_id' => $formId,
        'version' => '2026091801',
        'odk_version' => '2026091801',
        'active' => true,
    ]);
    $datasetId = DB::table('datasets')->insertGetId(['name' => 'headless-submissions']);
    DB::table('dataset_variables')->insert([
        'dataset_id' => $datasetId,
        'name' => 'age',
        'label' => 'Age',
        'type' => 'integer',
    ]);
    DB::table('xlsform_template_sections')->insert([
        'xlsform_template_id' => $template->id,
        'dataset_id' => $datasetId,
        'structure_item' => 'root',
        'schema' => json_encode([['name' => 'age', 'path' => '/age', 'type' => 'integer', 'value_type' => 'integer']]),
    ]);
    $entry = ['__id' => 'headless-submission', 'age' => '34'];
    $submissionId = DB::table('submissions')->insertGetId([
        'xlsform_version_id' => $versionId,
        'odk_id' => 'headless-submission',
        'submitted_at' => now(),
        'content' => json_encode($entry),
    ]);
    $evidence = new PersistedSubmissionEvidence($submissionId, $owner->id);
    app()->instance(PersistedSubmissionEvidence::class, $evidence);
    config()->set('filament-odk-link.contracts.submission_processor', PersistedSubmissionProcessor::class);

    (new ProcessOdkSubmission(Submission::findOrFail($submissionId), $entry, XlsformVersion::findOrFail($versionId)))->handle();

    expect($evidence->calls)->toBe(1)
        ->and(Entity::where('submission_id', $submissionId)->count())->toBe(1);
    Http::assertNothingSent();
});
