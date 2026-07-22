<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use function Laravel\Prompts\search;

class PublishXlsformOnOdkCentral implements ShouldQueue
{
    use Queueable;

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

        if ($this->user) {
            Notification::make('xlsform_file_deployment_failed')
                ->title('Form "'.$this->xlsform->title.'" failed to publish')
                ->body(new HtmlString('The message below was returned from the ODK Server. It may indicate an issue with the Xlsform definition being used: <br/><br/>' . $exception->getmessage()))
                ->danger()
                ->persistent()
                ->broadcast($this->user);
        }
    }
}
