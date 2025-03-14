<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
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

        ray("{$this->endpoint}/projects/{$xlsformTemplate->owner->odkProject->id}/forms/{$xlsformTemplate->odk_id}/attachments");

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
        $requiredDataMedia = $xlsform->attachedDataMedia()->get();

        if (count($requiredDataMedia) > 0) {
            foreach ($requiredDataMedia as $requiredMediaItem) {

                // if there is a static upload, use it;
                // TODO: work out how to handle xlsforms where we might have a static media file for TESTING the template...
                $media = $requiredMediaItem->getFirstMedia();
                if ($media) {
                    $this->uploadSingleMediaFile($xlsform, $media->getPath());
                }

                // TODO: add csv media file creation;
                // $this->uploadSingleMediaFile($xlsform, $csvPath);
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

}
