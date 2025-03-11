<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class PublishXlsformToOdkCentral implements ShouldQueue
{
    use Queueable;

    public function __construct(public Xlsform $xlsform, public string $filePath)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        ray('1');
        $this->xlsform->addMediaFromDisk($this->filePath, config('filament-odk-link.storage.xlsforms'))->toMediaCollection('xlsform_file');

        // if the odk_project is not set, set it based on the given owner:
        $this->xlsform->odk_project_id = $this->xlsform->owner->odkProject->id;
        $this->xlsform->has_latest_template = true;
        $this->xlsform->saveQuietly();

        ray('2');

        UpdateXlsformTitleInFile::dispatchSync($this->xlsform);

        $odkLinkService = app()->make(OdkLinkService::class);

        ray('3');

        $this->xlsform->deployDraft($odkLinkService, true);

        ray('4');

        if ($this->xlsform->has_draft) {
            $odkLinkService->publishForm($this->xlsform);

            ray('DONE DONE DONE DONE DONE');
        } else {
            ray('NOPE NOPE NOPE NOPE NOPE');
        }

    }
}
