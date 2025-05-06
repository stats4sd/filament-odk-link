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

        $odkXlsFormDetails = $odkLinkService->createDraftForm($this->xlsform, $this->xlsform->xlsfile->getPath(), $this->withMedia);

        $this->xlsform->update([
            'odk_id' => $odkXlsFormDetails['xmlFormId'],
            'odk_draft_token' => $odkXlsFormDetails['draftToken'],
            'odk_version_id' => $odkXlsFormDetails['version'],
            'has_draft' => true,
            'enketo_draft_id' => $odkXlsFormDetails['enketoId'],
            'odk_draft_updated_at' => new Carbon($odkXlsFormDetails['updatedAt']),
            'draft_needs_update' => false,
            'live_needs_update' => !$this->published, //if this draft was created immediately after the form was published, it will be identical to the published version. Otherwise, mark the form so users know the draft is different to the live.
        ]);

        // only ever keep 1 "draft" version; we don't need to store all iterations of drafts as we don't keep the old submissions either
        $this->xlsform->xlsformVersions()->updateOrCreate(
            [
                'is_draft' => true,
            ],
            [
                'version' => $odkXlsFormDetails['version'],
                'odk_version' => $odkXlsFormDetails['version'],
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
