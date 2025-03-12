<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Throwable;

class DeployDraftXlsformToOdkCentral implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform $xlsform, public bool $withMedia)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        // if the odk_project is not set, set it based on the given owner:
        $this->xlsform->odk_project_id = $this->xlsform->owner->odkProject->id;
        $this->xlsform->has_latest_template = true;
        $this->xlsform->saveQuietly();

        UpdateXlsformTitleInFile::dispatchSync($this->xlsform);

        $odkLinkService = app()->make(OdkLinkService::class);

               try {
            $odkXlsFormDetails = $odkLinkService->createDraftForm($this->xlsform, $this->withMedia);

        } catch (Throwable $e) {

            Notification::make('draft-form-failed')
                ->title('There is an error in the XLS Form')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }

        $this->xlsform->updateQuietly([
            'odk_id' => $odkXlsFormDetails['xmlFormId'],
            'odk_draft_token' => $odkXlsFormDetails['draftToken'],
            'odk_version_id' => $odkXlsFormDetails['version'],
            'has_draft' => true,
            'enketo_draft_id' => $odkXlsFormDetails['enketoId'],
        ]);

    }
}
