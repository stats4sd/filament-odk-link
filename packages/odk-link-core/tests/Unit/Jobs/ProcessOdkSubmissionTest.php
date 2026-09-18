<?php

use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class SubmissionProcessingRecorder
{
    public array $calls = [];
}

class InjectedSubmissionProcessor implements SubmissionProcessor
{
    public function __construct(private SubmissionProcessingRecorder $recorder) {}

    public function process(Submission $submission): void
    {
        $this->recorder->calls[] = [$submission->id, $submission->content];
    }
}

beforeEach(function () {
    $this->service = Mockery::mock(OdkLinkService::class);
    app()->instance(OdkLinkService::class, $this->service);
    $this->submission = new Submission;
    $this->submission->id = 7;
    $this->entry = ['__id' => 'uuid-abc'];
    $this->version = new XlsformVersion;
});

it('ingests successfully with the default null processor', function () {
    $this->service->shouldReceive('processSubmission')->once()->with($this->submission, $this->entry, $this->version);
    (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle();
});

it('resolves an injected processor after ingestion and passes the processed submission', function () {
    $recorder = new SubmissionProcessingRecorder;
    app()->instance(SubmissionProcessingRecorder::class, $recorder);
    config()->set('filament-odk-link.contracts.submission_processor', InjectedSubmissionProcessor::class);
    $this->service->shouldReceive('processSubmission')->once()->andReturnUsing(function (Submission $submission) {
        $submission->content = ['processed' => true];
    });
    (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle();
    expect($recorder->calls)->toBe([[7, ['processed' => true]]]);
});

it('propagates processor failures after ingestion', function () {
    $this->service->shouldReceive('processSubmission')->once();
    $processor = Mockery::mock(SubmissionProcessor::class);
    $processor->shouldReceive('process')->once()->with($this->submission)->andThrow(new RuntimeException('Processor failed'));
    app()->instance(SubmissionProcessor::class, $processor);
    expect(fn () => (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle())->toThrow(RuntimeException::class, 'Processor failed');
});

it('does not invoke host post processing if core ingestion fails', function () {
    $this->service->shouldReceive('processSubmission')->once()->andThrow(new RuntimeException('Ingestion failed'));
    $processor = Mockery::mock(SubmissionProcessor::class);
    $processor->shouldNotReceive('process');
    app()->instance(SubmissionProcessor::class, $processor);
    expect(fn () => (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle())->toThrow(RuntimeException::class, 'Ingestion failed');
});

it('rejects populated legacy callbacks instead of silently skipping host logic', function (string $legacy, array $value) {
    $this->service->shouldReceive('processSubmission')->once();
    config()->set("filament-odk-link.submission.{$legacy}", $value);
    expect(fn () => (new ProcessOdkSubmission($this->submission, $this->entry, $this->version))->handle())->toThrow(InvalidArgumentException::class, "filament-odk-link.submission.{$legacy}");
})->with([
    ['process_method', ['class' => 'LegacyProcessor', 'method' => 'run']],
    ['process_method', ['class' => 'LegacyProcessor', 'method' => null]],
    ['foreign_key_process_method', ['class' => null, 'method' => 'run']],
]);
