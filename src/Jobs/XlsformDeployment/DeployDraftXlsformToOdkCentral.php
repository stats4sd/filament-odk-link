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

class DeployDraftXlsformToOdkCentral implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform|XlsformTemplate $xlsform, public bool $withMedia, public bool $published, public ?Authenticatable $user)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->xlsform->save();

        $odkLinkService = app()->make(OdkLinkService::class);

        $this->xlsform = $odkLinkService->createDraftForm($this->xlsform, $this->xlsform->xlsfile->getPath(), $this->withMedia);

        if ($this->xlsform instanceof Xlsform) {
            $this->xlsform->live_needs_update = true;
            $this->xlsform->draft_needs_update = false;
        }

        $this->xlsform->save();

        // only ever keep 1 "draft" version; we don't need to store all iterations of drafts as we don't keep the old submissions either
        $this->xlsform->xlsformVersions()->updateOrCreate(
            [
                'is_draft' => true,
            ],
            [
                'version' => $this->xlsform->odk_version_id,
                'odk_version' => $this->xlsform->odk_version_id,
                'active' => true,
            ]
        );

    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('Xlsform Deployment Failed', ['exception' => $exception]);

        if ($this->user) {
            Notification::make('xlsform_file_deployment_failed')
                ->title('Draft Form "'.$this->xlsform->title.'" failed to deploy')
                ->body(new HtmlString('The message below was returned from the ODK Server. It may indicate an issue with the Xlsform definition being used: <br/><br/>' . $exception->getmessage()))
                ->danger()
                ->persistent()
                ->broadcast($this->user);
        }
    }
}
