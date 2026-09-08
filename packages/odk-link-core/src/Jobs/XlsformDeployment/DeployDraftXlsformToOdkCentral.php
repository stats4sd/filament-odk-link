<?php

namespace Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment;

use Exception;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Exceptions\OdkCentralRequestException;
use Stats4sd\FilamentOdkLink\Exceptions\XlsformValidationException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;
use Throwable;

class DeployDraftXlsformToOdkCentral implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60];

    public function __construct(public Xlsform | XlsformTemplate $xlsform, public bool $withMedia, public ?Authenticatable $user) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->xlsform->save();

        $odkLinkService = app()->make(OdkLinkService::class);

        $xlsfile = $this->xlsform->getFirstMedia('xlsform_file');

        if (! $xlsfile) {
            $this->fail(new Exception('There is no XLSForm file attached to this form. Please upload the file again and try to deploy the form again.'));

            return;
        }

        Log::info('Deploying draft xlsform to ODK Central', $this->logContext());

        try {
            $this->xlsform = $odkLinkService->createDraftForm($this->xlsform, $xlsfile->getPath(), $this->withMedia);
        } catch (XlsformValidationException $exception) {
            // the form definition itself is broken; retrying cannot help.
            $this->fail($exception);

            return;
        } catch (OdkCentralRequestException $exception) {
            if (! $exception->isRetryable()) {
                $this->fail($exception);

                return;
            }

            Log::warning('Draft xlsform deployment hit a retryable ODK Central error', [
                ...$this->logContext(),
                'response_status' => $exception->status,
                'odk_message' => $exception->odkMessage,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            throw $exception;
        }

        if ($this->xlsform instanceof Xlsform) {
            $this->xlsform->live_needs_update = ! $this->xlsform->live_needs_update;
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

        Log::info('Draft xlsform deployed to ODK Central', [
            ...$this->logContext(),
            'odk_version_id' => $this->xlsform->odk_version_id,
        ]);
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('Xlsform Deployment Failed', [
            ...$this->logContext(),
            'attempts' => $this->attempts(),
            'exception_class' => $exception ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
            'exception_location' => $exception ? "{$exception->getFile()}:{$exception->getLine()}" : null,
            'exception' => $exception,
        ]);

        $this->xlsform->updateQuietly(['processing' => false]);

        $message = $exception?->getMessage() ?? 'No error message was returned. Please check the application logs for details.';

        $recipients = $this->user
            ? collect([$this->user])
            : $this->superAdmins();

        $notification = Notification::make('xlsform_file_deployment_failed')
            ->title('Draft Form "' . $this->xlsform->title . '" failed to deploy')
            ->body(new HtmlString('The message below was returned from the ODK Server. It may indicate an issue with the Xlsform definition being used: <br/><br/>' . $message))
            ->danger()
            ->persistent();

        foreach ($recipients as $recipient) {
            $notification
                ->sendToDatabase($recipient, isEventDispatched: true)
                ->broadcast($recipient);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function logContext(): array
    {
        return [
            'xlsform_id' => $this->xlsform->id,
            'xlsform_type' => $this->xlsform::class,
            'xlsform_title' => $this->xlsform->title,
            'owner_id' => $this->xlsform->owner_id,
            'odk_project_id' => data_get($this->xlsform, 'owner.odkProject.id'),
            'odk_form_id' => $this->xlsform->odk_id,
            'with_media' => $this->withMedia,
        ];
    }
}
