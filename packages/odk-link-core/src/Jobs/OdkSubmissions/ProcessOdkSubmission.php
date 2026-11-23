<?php

namespace Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Contracts\SubmissionProcessor;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Stats4sd\FilamentOdkLink\Support\ConfiguredContracts;
use Throwable;

class ProcessOdkSubmission implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    public function __construct(public Submission $submission, public array $entry, public XlsformVersion $xlsformVersion) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Ingestion creates entity rows on every attempt, so integration misconfiguration must fail before it runs.
        app(ConfiguredContracts::class)->validateSubmissionConfiguration();

        $submissionProcessor = app(SubmissionProcessor::class);

        $odkLinkService = app()->make(OdkLinkService::class);

        $odkLinkService->processSubmission($this->submission, $this->entry, $this->xlsformVersion);

        $submissionProcessor->process($this->submission);
    }

    public function failed(?Throwable $exception = null): void
    {
        $formTitle = $this->submission->xlsform->title ?? 'unknown form';

        $this->notifyJobFailure(
            "Submission processing failed: {$this->submission->odk_id} ({$formTitle})",
            $exception,
        );
    }
}
