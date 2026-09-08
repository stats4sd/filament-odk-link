<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\ChoiceListAsMediaAttachmentExport;
use Stats4sd\FilamentOdkLink\Exports\DatasetAsMediaAttachmentExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

trait OdkFormMediaService
{
    /**
     * Gets the expected media items for a given draft form template
     *
     * @throws RequestException|ConnectionException
     */
    public function getRequiredMedia(HasXlsformDrafts $xlsformTemplate): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsformTemplate->owner->odkProject->id}/forms/{$xlsformTemplate->odk_id}/attachments")
            ->throw()
            ->json();
    }

    /**
     * Uploads all media files for an XLSform to ODK Central - both static files and dyncsv files
     *
     * @throws RequestException|ConnectionException
     */
    public function uploadMediaFileAttachments(HasXlsformDrafts $xlsform): bool
    {

        // static files
        $requiredFixedMedia = $xlsform->attachedFixedMedia()->get();

        if (count($requiredFixedMedia) > 0) {

            foreach ($requiredFixedMedia as $requiredMediaItem) {
                $this->uploadSingleMediaFile($xlsform, $requiredMediaItem->getFirstMedia()->getPath());
            }
        }

        // dynamic files — skip entity list CSVs (these are linked to ODK Central datasets via linkEntityListAttachments)
        $entityCsvNames = $this->entityListCsvNames($xlsform);

        $requiredDataMedia = $xlsform->requiredDataMedia()->get();

        if (count($requiredDataMedia) > 0) {
            foreach ($requiredDataMedia as $requiredMediaItem) {

                if (in_array($requiredMediaItem->name, $entityCsvNames)) {
                    continue;
                }

                // if there is a static upload, use it;
                // TODO: work out how to handle xlsforms where we might have a static media file for TESTING the template...
                $media = $requiredMediaItem->getFirstMedia();
                if ($media) {
                    $this->uploadSingleMediaFile($xlsform, $media->getPath());

                    continue;
                }

                $filePath = $this->prepareCsvFile($xlsform, $requiredMediaItem);

                $this->uploadSingleMediaFile($xlsform, Storage::disk(config('filament-odk-link.storage.media'))->path($filePath));
            }
        }

        return true;
    }

    /**
     * Gets the attachments expected by the current draft of the given form
     *
     * @throws RequestException|ConnectionException
     */
    public function getDraftAttachments(HasXlsformDrafts $xlsform): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/attachments")
            ->throw()
            ->json();
    }

    /**
     * Links every draft attachment that matches a declared entity list ({list_name}.csv) to the
     * corresponding dataset on ODK Central. Central only auto-links these when the first draft of a
     * brand-new form is created, so re-deployed drafts must be linked explicitly.
     *
     * @throws RequestException|ConnectionException
     */
    public function linkEntityListAttachments(HasXlsformDrafts $xlsform): void
    {
        $entityCsvNames = $this->entityListCsvNames($xlsform);

        if (count($entityCsvNames) === 0) {
            return;
        }

        foreach ($this->getDraftAttachments($xlsform) as $attachment) {
            if ($attachment['type'] !== 'file') {
                continue;
            }

            if (! in_array($attachment['name'], $entityCsvNames)) {
                continue;
            }

            if ($attachment['datasetExists'] ?? false) {
                continue;
            }

            $this->linkDatasetToDraftAttachment($xlsform, $attachment['name']);
        }
    }

    /**
     * @throws RequestException|ConnectionException
     */
    public function linkDatasetToDraftAttachment(HasXlsformDrafts $xlsform, string $attachmentName): void
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->patch("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/attachments/{$attachmentName}", [
                'dataset' => true,
            ]);

        // 404 means the dataset does not exist on ODK Central yet; the link will be made on the
        // next deployment after the dataset is created, so warn instead of failing the deployment.
        if ($response->status() === 404) {
            Log::warning('Could not link entity list to draft form attachment: no matching dataset on ODK Central', [
                'xlsform_id' => $xlsform->getKey(),
                'xlsform_type' => $xlsform::class,
                'odk_form_id' => $xlsform->odk_id,
                'attachment_name' => $attachmentName,
            ]);

            return;
        }

        $response->throw();
    }

    /** @return array<int, string> */
    private function entityListCsvNames(HasXlsformDrafts $xlsform): array
    {
        $listNames = match (true) {
            $xlsform instanceof XlsformTemplate => $xlsform->templateEntityLists->pluck('list_name'),
            $xlsform instanceof Xlsform => $xlsform->xlsformTemplate->templateEntityLists->pluck('list_name'),
            default => collect(),
        };

        return $listNames->map(fn (string $listName) => "{$listName}.csv")->toArray();
    }

    /**
     * Uploads a single media file to the given xlsform
     *
     * @throws RequestException|ConnectionException
     */
    public function uploadSingleMediaFile(HasXlsformDrafts $xlsform, string $filePath): array
    {
        $token = $this->authenticate();
        $file = file_get_contents($filePath);

        $mimeType = mime_content_type($filePath);
        $fileName = collect(explode('/', $filePath))->last();

        try {

            return Http::withToken($token)
                ->contentType($mimeType)
                ->withBody($file, $mimeType)
                ->post("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/attachments/{$fileName}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            if ($exception->getCode() === 404) {
                abort(500, 'The file '.$fileName.' is not an expected file name for this ODK form template. Please review the form and check which media files are expected');
            }

            throw ($exception);
        }
    }

    /**
     * Prepares a media attachment csv file based on a requiredDataMedia item for a specific xlsform.
     * Assumes that the ChoiceList containing the localised choices is named the same as the csv file.
     * */
    public function prepareCsvFile(HasXlsformDrafts $xlsform, RequiredMedia $requiredMediaItem): string
    {
        // create folder structure if not exists
        // Storage::disk(config('filament-odk-link.storage.media'))->makeDirectory('xlsforms');

        $filePath = 'xlsforms/'.$xlsform->id.'/'.$requiredMediaItem->name;

        // check if the requiredMedia is linked to a choice list or dataset
        if ($requiredMediaItem->links_to_dataset) {

            if ($requiredMediaItem->dataset === null) {
                abort(500, 'The dataset for the required media item '.$requiredMediaItem->name.' is not set. Please check the form template and ensure all required data media items are linked to a dataset or choice list.');
            }

            Excel::store(
                export: new DatasetAsMediaAttachmentExport($xlsform, $requiredMediaItem->dataset),
                filePath: $filePath,
                diskName: config('filament-odk-link.storage.media')
            );
        } else {

            if ($requiredMediaItem->choiceList === null) {
                abort(500, 'The choice list for the required media item '.$requiredMediaItem->name.' is not set. Please check the form template and ensure all required data media items are linked to a dataset or choice list.');
            }

            Excel::store(
                export: new ChoiceListAsMediaAttachmentExport($xlsform, $requiredMediaItem->choiceList),
                filePath: $filePath,
                diskName: config('filament-odk-link.storage.media')
            );
        }

        return $filePath;
    }
}
