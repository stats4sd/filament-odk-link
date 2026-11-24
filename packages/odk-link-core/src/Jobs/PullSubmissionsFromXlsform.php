<?php

namespace Stats4sd\OdkLink\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stats4sd\OdkLink\Core\Concerns\NotifiesOnJobFailure;
use Stats4sd\OdkLink\Core\Models\OdkLink\Xlsform;
use Stats4sd\OdkLink\Core\Services\OdkLinkService;
use Throwable;

class PullSubmissionsFromXlsform implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use NotifiesOnJobFailure;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Xlsform $xlsform)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $odkLinkService = app()->make(OdkLinkService::class);

        $odkLinkService->getSubmissions($this->xlsform);
    }

    public function failed(?Throwable $exception = null): void
    {
        $this->notifyJobFailure(
            "Submission pull failed: {$this->xlsform->title} ({$this->xlsform->owner->name})",
            $exception,
        );
    }
}
