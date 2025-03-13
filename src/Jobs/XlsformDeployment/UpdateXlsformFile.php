<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Stats4sd\FilamentOdkLink\Jobs\UpdateXlsformTitleInFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class UpdateXlsformFile implements ShouldQueue
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
        try {
            $this->xlsform->addMediaFromDisk($this->filePath, config('filament-odk-link.storage.xlsforms'))->toMediaCollection('xlsform_file');

            $this->xlsform->updateQuietly(['processing' => false]);

        } catch (FileDoesNotExist $e) {
            Log::error('Trying to save file for Xlsform '.  $this->xlsform->id . ' that does not exist at path ' . $this->filePath);
        } catch (FileIsTooBig $e) {
            Log::error('Trying to save file for Xlsform '.  $this->xlsform->id . ' that is too big at path ' . $this->filePath);
        }

    }
}
