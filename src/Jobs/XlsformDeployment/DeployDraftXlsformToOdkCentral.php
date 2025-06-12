<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
                'version' => $this->xlsform->current_version,
                'odk_version' => $this->xlsform->current_version,
                'active' => true,
            ]
        );

    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('Xlsform Deployment Failed', ['exception' => $exception]);

        if ($this->user) {
            Notification::make('xlsform_file_deployment_failed')
                ->title('Draft Form Failed to Deploy')
                ->body('The Xlsform ' . $this->xlsform->title . ' belonging to ' . $this->xlsform->owner->name . ' failed to upload to ODK Central. Please check other error messages and review the form to confirm it is a valid ODK form.')
                ->danger()
                ->broadcast($this->user);
        }
    }
}
