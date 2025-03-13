<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Imports\XlsImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait OdkFormService
{
    /**
     * Creates a new (draft) form.
     * If the form is not already deployed, it will create a new form instance on ODK Central.
     * If the form is already deployed, it will push the current XLSfile as a new draft to the existing form.
     *
     * @return array $xlsformDetails
     *
     * @throws RequestException|ConnectionException
     */
    public function createDraftForm(HasXlsformDrafts $xlsform, ?UploadedFile $file = null, bool $withMedia = true): array
    {

        $token = $this->authenticate();

        if ($file) {
            $filePath = $file->getRealPath();
        } else {
            $filePath = $xlsform->getFirstMedia('xlsform_file')?->getPath();
        }

        if (!$filePath) {
            throw new \Exception('The XLSForm file is missing. Please upload the file again and try to deploy the form again.', 500);
        }

        $file = file_get_contents($filePath);

        $url = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms?ignoreWarnings=true&publish=false";

        // if the form is already on ODK Central, post to /forms/{id}/draft endpoint. Otherwise, post to /forms endpoint to create an entirely new form.
        if ($xlsform->odk_id) {
            $url = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft?ignoreWarnings=true";
        }

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-XlsForm-FormId-Fallback' => Str::slug($xlsform->title),
            ])
            ->withBody($file, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->post($url);

        $responseBody = $response->json();
        // if the xlsform file is not valid, throw an error
        if (isset($responseBody['message']) && Str::startsWith($responseBody['message'], 'The given XLSForm file was not valid')) {
            throw new \Exception($response->json()['details']['error'], 500);
        } elseif ($response->status() !== 200) {

            abort(500, 'An error occurred while creating the draft form. The error is not an XLSForm file validation issue, but something else that might require further investigation. Please try again later or contact support if the problem persists');
        }

        // when creating a new draft for an existing form, the full form details are not returned. In this case, the $xlsform record can remain unchanged
        if (isset($responseBody['xmlFormId'])) {
            $xlsform->update(['odk_id' => $responseBody['xmlFormId']]);
        }
        $this->updateSchema($xlsform);

        // deploy media files - only if with media is true.
        if ($withMedia) {
            $this->uploadMediaFileAttachments($xlsform);
        }

        return $this->getDraftFormDetails($xlsform);
    }

    // update the schema of a template for xlsform from the latest draft version on ODK Central
    public function updateSchema(HasXlsformDrafts $xlsform): void
    {
        $token = $this->authenticate();

        // upddate the stored schema with the new draft;
        $schema = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/fields?odata=true")
            ->throw()
            ->json();

        // get the xlsform and merge in specific details to the schema returned from ODK Central
        $surveyExcel = (new XlsImport)->toCollection($xlsform->getMedia('xlsform_file')->first()->getPathRelativeToRoot(), config('filament-odk-link.storage.xlsforms'), \Maatwebsite\Excel\Excel::XLSX)[0];

        $schema = collect($schema)->map(function (array $item) use ($surveyExcel): array {

            if ($row = $surveyExcel->where('name', $item['name'])->first()) {
                $item['value_type'] = $row['type'];

                // find the label and hint for all languages
                $row->each(function ($value, $key) use (&$item) {
                    if (Str::startsWith($key, 'label')) {
                        $item[$key] = $value;
                    }
                    if (Str::startsWith($key, 'hint')) {
                        $item[$key] = $value;
                    }
                });
            }

            return $item;
        })->toArray();

        $xlsform->update(['schema' => $schema]);
    }

    /**
     * Gets the draft form details for a given xlsform
     *
     * @throws RequestException|ConnectionException
     */
    public function getDraftFormDetails(HasXlsformDrafts $xlsform): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft")
            ->throw()
            ->json();
    }

    /**
     * Publishes the current draft form so it is available for live data collection
     *
     * @return XlsformVersion $xlsformVersion
     *
     * @throws RequestException
     */
    public function publishForm(Xlsform $xlsform): XlsformVersion
    {

        $token = $this->authenticate();

        Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/publish?version=" . Carbon::now()->toDateTimeString())
            ->throw()
            ->json();

        // Get the version information;
        $formDetails = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}")
            ->throw()
            ->json();

        if ($formDetails['state'] !== 'open') {
            $formDetails = $this->unArchiveForm($xlsform);
        }

        // TODO: move all of this into some form of XlsformVersion handler!
        // deactivate all other versions;
        $xlsform->xlsformVersions()->update([
            'active' => false,
        ]);

        $xlsformVersion = $this->createNewVersion($xlsform, $formDetails);

        $xlsform->update([
            'has_draft' => false,
            'is_active' => true,
            'odk_version_id' => $xlsformVersion->version,
            'odk_published_at' => Carbon::make($formDetails['publishedAt']),
        ]);
        $xlsform->save();

        return $xlsformVersion;
    }

    // create a new xlsformVersion from an existing xlsform.
    public function createNewVersion(Xlsform $xlsform, array $versionDetails): XlsformVersion
    {
        $token = $this->authenticate();

        $versionSlug = Str::slug($versionDetails['version']);

        // create new active version with latest version number;
        $xlsformVersion = $xlsform->xlsformVersions()->create([
            'version' => $versionDetails['version'],
            'odk_version' => $versionDetails['version'],
            'active' => true,
            'schema' => $xlsform->schema,
        ]);

        // copy xlsform file to store linked to this version forever
        $xlsform->getMedia('xlsform_file')->first()->copy($xlsformVersion, 'xlsform_file');

        // copy any attached media
        $xlsform->getMedia('attached_media')->each(fn($media) => $media->copy($xlsformVersion, 'attached_media'));

        return $xlsformVersion;
    }

    /**
     * Archives a form to prevent further data collection
     *
     * @return array $xlsformDetails
     */
    public function archiveForm(HasXlsformDrafts $xlsform): array
    {
        $token = $this->authenticate();

        $result = Http::withToken($token)
            ->patch("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}", [
                'state' => 'closed',
            ])
            ->throw()
            ->json();

        $xlsform->update([
            'is_active' => false,
        ]);

        return $result;
    }

    public function unArchiveForm(HasXlsformDrafts $xlsform)
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->patch("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}", [
                'state' => 'open',
            ])
            ->throw()
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function deleteForm(HasXlsformDrafts $xlsform): bool
    {
        $token = $this->authenticate();

        try {

            $result = Http::withToken($token)
                ->delete("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            if ($exception->getCode() === 404) {
                // this is fine; the form does not exist and so has already been deleted.

                return true;
            }

            throw ($exception);
        }

        return true;
    }
}
