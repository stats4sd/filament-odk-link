<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Throwable;

class UpdateXlsformFile implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    public function __construct(public Xlsform $xlsform, public string $filePath, public ?Authenticatable $user = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->xlsform->addMediaFromDisk($this->filePath, config('filament-odk-link.storage.xlsforms'))->toMediaCollection('xlsform_file');
        } catch (FileDoesNotExist $exception) {
            Log::error('Trying to save file for Xlsform ' . $this->xlsform->id . ' that does not exist at path ' . $this->filePath);
            $this->fail($exception);
        } catch (FileIsTooBig $exception) {
            Log::error('Trying to save file for Xlsform ' . $this->xlsform->id . ' that is too big at path ' . $this->filePath);
            $this->fail($exception);
        } finally {
            $this->xlsform->updateQuietly(['processing' => false]);
        }

    }

    public function failed(?Throwable $exception = null): void
    {
        $this->xlsform->updateQuietly(['processing' => false]);

        $this->notifyJobFailure(
            "Form file update failed: {$this->xlsform->title}",
            $exception,
            $this->user ?? $this->superAdmins(),
        );
    }
}
