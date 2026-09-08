<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Throwable;

class PublishXlsformOnOdkCentral implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60];

    public function __construct(public Xlsform|XlsformTemplate $xlsform, public ?Authenticatable $user)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $odkLinkService = app()->make(OdkLinkService::class);

        $odkLinkService->publishForm($this->xlsform);

        // Now that the form is published, re-deploy the draft to ensure there is always a draft version available for testing.
        // pass the published flag to indicate this is a redeployment after publishing, so no new xlsform file is needed.
        $this->xlsform->live_needs_update = false;
        $this->xlsform->save();

        $this->xlsform->deployDraft(published: true);

    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('Xlsform Publishing Failed', ['exception' => $exception]);

        $this->xlsform->updateQuietly(['processing' => false]);

        $message = $exception?->getMessage() ?? 'No error message was returned. Please check the application logs for details.';

        $recipients = $this->user
            ? collect([$this->user])
            : $this->superAdmins();

        $notification = Notification::make('xlsform_file_deployment_failed')
            ->title('Form "' . $this->xlsform->title . '" failed to publish')
            ->body(new HtmlString('The message below was returned from the ODK Server. It may indicate an issue with the Xlsform definition being used: <br/><br/>' . $message))
            ->danger()
            ->persistent();

        foreach ($recipients as $recipient) {
            $notification
                ->sendToDatabase($recipient, isEventDispatched: true)
                ->broadcast($recipient);
        }
    }
}
