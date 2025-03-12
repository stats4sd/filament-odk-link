<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Throwable;

class DeployDraftXlsformToOdkCentral implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform | XlsformTemplate $xlsform, public bool $withMedia) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->xlsform->saveQuietly();

        UpdateXlsformTitleInFile::dispatchSync($this->xlsform);

        $odkLinkService = app()->make(OdkLinkService::class);

       // try {
            $odkXlsFormDetails = $odkLinkService->createDraftForm($this->xlsform, $this->withMedia);

            $this->xlsform->updateQuietly([
                'odk_id' => $odkXlsFormDetails['xmlFormId'],
                'odk_draft_token' => $odkXlsFormDetails['draftToken'],
                'odk_version_id' => $odkXlsFormDetails['version'],
                'has_draft' => true,
                'enketo_draft_id' => $odkXlsFormDetails['enketoId'],
            ]);
//
//        } catch (Throwable $e) {
//
//            Notification::make('draft-form-failed')
//                ->title('There is an error in the XLS Form')
//                ->body($e->getMessage())
//                ->danger()
//                ->persistent()
//                ->send();
//        }

    }
}
