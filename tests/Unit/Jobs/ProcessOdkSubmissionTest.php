<?php

use Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

// A test double standing in for the host app's configured post-processor.
class SubmissionProcessSpy
{
    /** @var list<int|null> */
    public static array $calls = [];

    public static function record(Submission $submission): void
    {
        self::$calls[] = $submission->id;
    }
}

beforeEach(function () {
    SubmissionProcessSpy::$calls = [];

    // The job delegates the heavy lifting to OdkLinkService::processSubmission;
    // mock it so we test only the job's own orchestration.
    $this->service = Mockery::mock(OdkLinkService::class);
    app()->instance(OdkLinkService::class, $this->service);

    // Bare, unsaved instances — the mocked service never queries them, and
    // `new` does not trigger Submission's global scopes.
    $this->submission = new Submission;
    $this->submission->id = 7;
    $this->entry = ['__id' => 'uuid-abc'];
    $this->version = new XlsformVersion;
});

it('delegates submission processing to OdkLinkService', function () {
    $this->service->shouldReceive('processSubmission')
        ->once()
        ->with($this->submission, $this->entry, $this->version);

    config()->set('filament-odk-link.submission.process_method.class', null);
    config()->set('filament-odk-link.submission.process_method.method', null);

    (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle();

    expect(SubmissionProcessSpy::$calls)->toBe([]);
});

it('invokes the host-app process_method when one is configured', function () {
    $this->service->shouldReceive('processSubmission')->once();

    config()->set('filament-odk-link.submission.process_method.class', SubmissionProcessSpy::class);
    config()->set('filament-odk-link.submission.process_method.method', 'record');

    (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle();

    expect(SubmissionProcessSpy::$calls)->toBe([7]);
});

it('skips the host-app callback when only the class is configured', function () {
    $this->service->shouldReceive('processSubmission')->once();

    config()->set('filament-odk-link.submission.process_method.class', SubmissionProcessSpy::class);
    config()->set('filament-odk-link.submission.process_method.method', null);

    (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle();

    expect(SubmissionProcessSpy::$calls)->toBe([]);
});
