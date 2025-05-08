<?php

namespace Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class ProcessOdkSubmission implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission, public array $entry, public XlsformVersion $xlsformVersion)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $odkLinkService = app()->make(OdkLinkService::class);

        $odkLinkService->processSubmission($this->submission, $this->entry, $this->xlsformVersion);

        // if app developer has defined a method of processing submission content, call that method:
        $class = config('filament-odk-link.submission.process_method.class');
        $method = config('filament-odk-link.submission.process_method.method');

        if ($class && $method) {
            $class::$method($this->submission);
        }

    }
}
