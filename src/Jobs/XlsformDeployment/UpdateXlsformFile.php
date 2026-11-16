<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Exceptions\XlsformFileSupersededException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Throwable;

class UpdateXlsformFile implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    private const SIBLING_JOB_WINDOW_MINUTES = 5;

    private bool $fileTakenBySibling = false;

    public function __construct(public Xlsform $xlsform, public string $filePath, public ?Authenticatable $user = null) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->xlsform->addMediaFromDisk($this->filePath, config('filament-odk-link.storage.xlsforms'))->toMediaCollection('xlsform_file');
        } catch (FileDoesNotExist $exception) {
            if ($this->formAlreadyHasFreshFile()) {
                $this->fileTakenBySibling = true;
                Log::info('The generated file for Xlsform ' . $this->xlsform->id . ' was already attached by an identical job; skipping', ['path' => $this->filePath]);
                $this->fail(new XlsformFileSupersededException($exception->getMessage(), 0, $exception));

                return;
            }

            Log::error('Trying to save file for Xlsform ' . $this->xlsform->id . ' that does not exist at path ' . $this->filePath);
            $this->fail($exception);
        } catch (FileIsTooBig $exception) {
            Log::error('Trying to save file for Xlsform ' . $this->xlsform->id . ' that is too big at path ' . $this->filePath);
            $this->fail($exception);
        } finally {
            if (! $this->fileTakenBySibling) {
                $this->xlsform->updateQuietly(['processing' => false]);
            }
        }
    }

    private function formAlreadyHasFreshFile(): bool
    {
        return $this->xlsform->media()
            ->where('collection_name', 'xlsform_file')
            ->where('created_at', '>=', now()->subMinutes(self::SIBLING_JOB_WINDOW_MINUTES))
            ->exists();
    }

    public function failed(?Throwable $exception = null): void
    {
        if ($exception instanceof XlsformFileSupersededException) {
            return;
        }

        $this->xlsform->updateQuietly(['processing' => false]);

        $this->notifyJobFailure(
            "Form file update failed: {$this->xlsform->title}",
            $exception,
            $this->user ?? $this->superAdmins(),
        );
    }
}
