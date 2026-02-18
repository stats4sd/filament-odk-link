<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;

use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Exports\DatasetAsMediaAttachmentExport;
use Stats4sd\FilamentOdkLink\Exports\ChoiceListAsMediaAttachmentExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;

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

        // dynamic files
        $requiredDataMedia = $xlsform->requiredDataMedia()->get();

        if (count($requiredDataMedia) > 0) {
            foreach ($requiredDataMedia as $requiredMediaItem) {

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
                abort(500, 'The file ' . $fileName . ' is not an expected file name for this ODK form template. Please review the form and check which media files are expected');
            }

            throw ($exception);
        }
    }

    /**
     * Prepares a media attachment csv file based on a requiredDataMedia item for a specific xlsform.
     * Assumes that the ChoiceList containing the localised choices is named the same as the csv file.
     * @return string
     * */
    public function prepareCsvFile(HasXlsformDrafts $xlsform, RequiredMedia $requiredMediaItem): string
    {
        // create folder structure if not exists
        //Storage::disk(config('filament-odk-link.storage.media'))->makeDirectory('xlsforms');

        $filePath = 'xlsforms/' . $xlsform->id . '/' . $requiredMediaItem->name;

        // check if the requiredMedia is linked to a choice list or dataset
        if ($requiredMediaItem->links_to_dataset) {

            if($requiredMediaItem->dataset === null) {
                abort(500, 'The dataset for the required media item ' . $requiredMediaItem->name . ' is not set. Please check the form template and ensure all required data media items are linked to a dataset or choice list.');
            }

            Excel::store(
                export: new DatasetAsMediaAttachmentExport($xlsform, $requiredMediaItem->dataset),
                filePath: $filePath,
                diskName: config('filament-odk-link.storage.media')
            );
        } else {

            if($requiredMediaItem->choiceList === null) {
                abort(500, 'The choice list for the required media item ' . $requiredMediaItem->name . ' is not set. Please check the form template and ensure all required data media items are linked to a dataset or choice list.');
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
