<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\SurveyExport;
use Stats4sd\FilamentOdkLink\Imports\XlsImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Abstracts\HasXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsformDrafts;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * All ODK Aggregation services should be able to handle ODK forms, so this interface should always be used.
 */
class OdkLinkService
{
    public function __construct(protected string $endpoint) {}

    /**
     * Creates a new session + auth token for communication with the ODK Central server
     *
     * @return string $token
     */
    public function authenticate(): string
    {
        // if a token exists in the cache, return it. Otherwise, create a new session and store the token.
        return Cache::remember('odk-token', now()->addHours(20), function () {

            $response = Http::post("{$this->endpoint}/sessions", [
                'email' => config('filament-odk-link.odk.username'),
                'password' => config('filament-odk-link.odk.password'),
            ])
                ->throw()
                ->json();

            return $response['token'];
        });
    }

    /**
     * Creates a new project in ODK Central
     *
     * @return array $projectInfo
     *
     * @throws RequestException|ConnectionException
     */
    public function createProject(string $name): array
    {
        $token = $this->authenticate();

        // prepend platform identifier to project name;
        $name = (config('app.short_name') ?? config('app.name')) . '- ' . $name;

        // leave 7 characters for the "all " prefix and number suffix for the app user;
        if (Str::length($name) > 57) {
            $name = Str::squish($name);
        }

        if (Str::length($name) > 57) {
            $name = Str::limit($name, limit: 57, end: '');
        }

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects", [
                'name' => $name,
            ])
            ->throw()
            ->json();
    }

    public function createProjectAppUser(OdkProject $odkProject): array
    {
        $token = $this->authenticate();

        // truncate name to 64 characters
        $displayName = Str::limit('All ' . $odkProject->name . ' ' . $odkProject->appUsers()->count() + 1, limit: 64, end: '');

        // create new app-user
        $userResponse = Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/app-users", [
                'displayName' => $displayName,
            ])
            ->throw()
            ->json();

        // assign user to all the forms in the project
        Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/assignments/manager/{$userResponse['id']}")
            ->throw()
            ->json();

        return $userResponse;
    }

    /**
     * Updates a project name
     *
     * @return array $projectInfo
     *
     * @throws RequestException|ConnectionException
     */
    public function updateProject(OdkProject $odkProject, string $newName): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/$odkProject->id", [
                'name' => $newName,
            ])
            ->throw()
            ->json();
    }

    /**
     * Archives a project
     *
     * @return array $success
     *
     * @throws RequestException|ConnectionException
     */
    public function archiveProject(OdkProject $odkProject): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/$odkProject->id", [
                'name' => $odkProject->name,
                'archived' => true,
            ])
            ->throw()
            ->json();
    }

    /**
     * Creates a new (draft) form.
     * If the form is not already deployed, it will create a new form instance on ODK Central.
     * If the form is already deployed, it will push the current XLSfile as a new draft to the existing form.
     *
     * @return array $xlsformDetails
     *
     * @throws RequestException|ConnectionException
     */
    public function createDraftForm(HasXlsformDrafts $xlsform, bool $withMedia = true): array
    {

        ray('hi');
        $token = $this->authenticate();

        $filePath = $xlsform->getFirstMedia('xlsform_file')?->getPath();

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

    // ########################################################
    // ## FORM MEDIA ATTACHMENTS
    // ########################################################

    /**
     * Uploads all media files for an XLSform to ODK Central - both static files and dyncsv files
     *
     * @throws RequestException|ConnectionException
     */
    public function uploadMediaFileAttachments(XLsform|XlsformTemplate $xlsform): bool
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
    public function uploadSingleMediaFile(Xlsform|XlsformTemplate $xlsform, string $filePath): array
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
     * Publishes the current draft form so it is available for live data collection
     *
     * @return XlsformVersion $xlsformVersion
     *
     * @throws RequestException
     */
    public function publishForm(Xlsform $xlsform): XlsformVersion
    {

        $token = $this->authenticate();

        ray("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/publish?version=" . Carbon::now()->toDateTimeString());

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
        ]);
        $xlsform->save();

        return $xlsformVersion;
    }

    /**
     * Archives a form to prevent further data collection
     *
     * @return array $xlsformDetails
     */
    public function archiveForm(Xlsform $xlsform): array
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

    /**
     * @throws RequestException
     */
    public function deleteForm(Xlsform|XlsformTemplate $xlsform): bool
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

    public function getAttachedMedia($entry, string $token, Xlsform $xlsform, ?Submission $submission): void
    {
        // ******** PROCESS MEDIA ******** //
        // check if media is expected
        if ($entry['__system']['attachmentsPresent'] > 0) {
            $mediaPresent = Http::withToken($token)
                ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/submissions/${entry['__id']}/attachments")
                ->throw()
                ->json();

            foreach ($mediaPresent as $mediaItem) {

                // download the attachment
                $result = Http::withToken($token)
                    ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/submissions/${entry['__id']}/attachments/${mediaItem['name']}")
                    ->throw();

                // store the attachment locally
                Storage::disk(config('filament-odk-link.storage.media'))
                    ->put($mediaItem['name'], $result->body());

                // link it to the submission via Media Library
                $submission->addMediaFromDisk($mediaItem['name'], config('filament-odk-link.storage.media'))
                    ->toMediaLibrary();
            }
        }
    }

    // update the schema of a template for xlsform from the latest draft version on ODK Central
    public function updateSchema(Xlsform|XlsformTemplate $xlsform): void
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
                $row->each(function ($value, $key) {
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

        $xlsform->updateQuietly(['schema' => $schema]);
    }

    public function unArchiveForm(Xlsform $xlsform)
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->patch("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}", [
                'state' => 'open',
            ])
            ->throw()
            ->json();
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

    public function getSubmissionCount(Xlsform $xlsform): ?int
    {
        $token = $this->authenticate();
        $results = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/submissions");

        // simple error handling
        if (!$results->ok()) {
            return null;
        }

        return count($results->json());
    }

    // checks for new submissions for a given form and returns the count of new submissions found.
    public function getSubmissions(Xlsform $xlsform): int
    {
        $token = $this->authenticate();
        $oDataServiceUrl = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}.svc";

        $results = Http::withToken($token)
            ->get($oDataServiceUrl . '/Submissions?$expand=*')
            ->throw()
            ->json();

        // only process new submissions
        $resultsToAdd = Collect($results['value'])->whereNotIn('__id', $xlsform->submissions()->withTrashed()->pluck('odk_id')->toArray());

        foreach ($resultsToAdd as $entry) {

            // ******* CREATE SUBMISSION RECORD ******* //
            $xlsformVersion = $xlsform->xlsformVersions()->firstWhere('version', $entry['__system']['formVersion']);

            if (!$xlsformVersion) {

                $messageContent = collect([
                    'formVersion' => $entry['__system']['formVersion'],
                    'xlsformId' => $xlsform->id,
                    'xlsformTitle' => $xlsform->title,
                    'ownerName' => $xlsform->owner->name,
                ]);


                if (config('app.env') === 'local') {
                    throw new \Exception('The system tried to get submission data for a form version that does not exist. LOCAL ENVIRONMENT: if you are testing a form that may have been updated on ODK Central directly, or through another app environment, please run `php artisan app:update-xlsform-versions-from-odk-central`, and try pulling the submissions again.');
                }
                throw new \Exception('The system tried to get submission data for a form version that does not exist.  Please copy the following details and send them to the system administrator: ' . $messageContent->map(fn($item, $key) => "$key: $item")->implode(', '), 500);
            }

            $submission = $xlsformVersion->submissions()->create([
                'odk_id' => $entry['__id'],
                'submitted_at' => (new Carbon($entry['__system']['submissionDate']))->toDateTimeString(),
                'submitted_by' => $entry['__system']['submitterName'],
                'content' => $entry,
            ]);

            // old approach to process a submission, it can handle main survey section and one level of repeat group section
            // $this->processEntry($submission, $entry, $xlsformVersion);

            // new approach to process a submission, it can handle main survey section and any level of repeat group section
            $this->processSubmission($submission, $entry, $xlsformVersion);

            $this->getAttachedMedia($entry, $token, $xlsform, $submission);

            // ******** CALL APP-SPECIFIC PROCESSING ******** //

            // if app developer has defined a method of processing submission content, call that method:
            $class = config('filament-odk-link.submission.process_method.class');
            $method = config('filament-odk-link.submission.process_method.method');

            if ($class && $method) {
                $class::$method($submission);
            }
        }

        return $resultsToAdd->count();
    }


    /* ========== */


    public function processSubmission(Submission $submission, array $entry, XlsformVersion $xlsformVersion): void
    {
        // ray('OdkLinkService.processSubmission()...');

        // find xlsform via xlsform version
        $xlsform = $xlsformVersion->xlsform;

        // add $entry into array, to retrieve a value from a deeply nested array using "dot" notation
        $rootEntry = ['root' => $entry];

        // find the main survey section (root section) of this xlsform, which is the starting point of a submission
        $rootSection = $xlsformVersion->xlsform->xlsformTemplate->rootSection;

        // process the main survey section
        $this->processRootSection($xlsform, $rootEntry, $rootSection, $submission);
    }


    private function processRootSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, Submission $submission)
    {
        // ray('OdkLinkService.processRootSection()...');
        // ray('section: ' . $section->structure_item);

        // TODO: create entities records, fill in $entityId as parent_id
        $newEntityId = null;

        // extract data from main survey section (root section)
        if ($section->is_repeat == 0) {

            // exclude structure items from section schema, as there is no value to be stored for a structure item
            $schema = $section->schema->where('type', '!=', 'structure');

            // create entity record for main survey (root)
            $entity = Entity::create([
                'dataset_id' => $section->dataset->id,
                'submission_id' => $submission->id,
                'owner_id' => $submission->owner->getKey(),
                'model_type' => $section->dataset->entity_model,
            ]);

            $newEntityId = $entity->id;

            // add polymorphic relationship
            $entity->owner()->associate($xlsform->owner)->save();

            // create entity_values records
            // access the value of each ODK variable from a deeply nested array using "dot" notation
            foreach ($schema as $schemaItem) {
                $itemPath = 'root' . Str::replace('/', '.', $schemaItem['path']);
                $value = Arr::get($entry, $itemPath);

                if ($schemaItem['type'] != 'repeat' && $value !== null && $value != '' && !is_array($value)) {
                    // store ODK variable value as entity value record

                    // TODO: get label from correct language String entry.
                    $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
                        'name'  => $schemaItem['name'],
                        'label' => $schemaItem['name'],
                    ]);

                    EntityValue::create([
                        'entity_id' => $entity->id,
                        'dataset_variable_name' => $datasetVariable->name,
                        'value' => $value,
                    ]);
                }
            }

            // find all child sections of this section
            $childSections = $xlsform->xlsformTemplate->repeatingSections
                ->where('parent_id', $section->id);

            // ray($childSections);

            // process child sections one by one recursively
            foreach ($childSections as $childSection) {
                $this->processRepeatGroupSection($xlsform, $entry, $childSection, $submission, $newEntityId);
            }
        }
    }


    private function processRepeatGroupSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, Submission $submission, $entityId)
    {
        // ray('OdkLinkService.processRepeatGroupSection()...');
        // ray('section: ' . $section->structure_item);
        // ray('entityId: ' . $entityId);

        // TODO: create entities records, fill in $entityId as parent_id
        $newEntityId = null;

        // extract data from repeat group section
        if ($section->is_repeat == 1) {
            // exclude structure items from section schema, as there is no value to be stored for a structure item
            $schema = $section->schema->where('type', '!=', 'structure');

            // find the path of repeat group first item
            $schemaPaths = $schema->pluck('path')->toArray();
            // ray($schemaPaths);

            $position = Str::position($schemaPaths[0], '/' . $section->structure_item . '/');
            // ray($position);

            // construct the path for getting an array of repeat group
            $repeatGroupArrayPath = 'root' . Str::replace('/', '.', Str::substr($schemaPaths[0], 0, $position)) . '.' . $section->structure_item;
            // ray($repeatGroupArrayPath);

            // get the array for repeat group
            $repeatGroupArray = Arr::get($entry, $repeatGroupArrayPath);
            // ray($repeatGroupArray);

            // it should be an array containing records for a repeat group
            if (is_array($repeatGroupArray)) {

                // handle each record in repeat group
                foreach ($repeatGroupArray as $repeatGroupRecord) {

                    // ray('repeatGroupRecord:');
                    // ray($repeatGroupRecord);

                    // create entity record for each repeat group record

                    // if the section is not linked to a dataset, move on;
                    if (!$section->dataset) {
                        continue;
                    }

                    $entity = Entity::create([
                        'dataset_id' => $section->dataset->id,
                        'submission_id' => $submission->id,
                        'owner_id' => $submission->owner->getKey(),
                        'parent_id' => $entityId,
                        'model_type' => $section->dataset->entity_model,
                    ]);

                    $newEntityId = $entity->id;

                    // add polymorphic relationship
                    $entity->owner()->associate($xlsform->owner)->save();

                    // get array element as record
                    $repeatGroupEntry = ['rg' => $repeatGroupRecord];

                    foreach ($schema as $schemaItem) {

                        $pathLength = Str::length($schemaItem['path']);
                        $position = Str::position($schemaItem['path'], '/' . $section->structure_item . '/');
                        $lengthToCut = $pathLength - $position;

                        $itemPath = Str::substr($schemaItem['path'], ($position + 1) + Str::length($section->structure_item), $lengthToCut);

                        $fullItemPath = 'rg' . Str::replace('/', '.', $itemPath);

                        $value = Arr::get($repeatGroupEntry, $fullItemPath);

                        if ($schemaItem['type'] != 'repeat' && $value != null && $value != '' && !is_array($value)) {

                            // TODO: get label from correct language String entry.
                            $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
                                'name' => $schemaItem['name'],
                                'label' => $schemaItem['name'],
                            ]);

                            // store ODK variable value as entity value record
                            EntityValue::create([
                                'entity_id' => $entity->id,
                                'dataset_variable_name' => $datasetVariable->name,
                                'value' => $value,
                            ]);
                        }
                    }



                    // use repeatGroupRecord to construct a new entry, so that child section data inside different repeatGroupRecord can be extracted by path properly.
                    // In theory, we will be able to handle nested repeat groups with any level.
                    // P.S. In real life, we would recommend to have maximum two levels of nested repeat groups in an ODK form
                    //
                    // I understand that it is not desired to have a large section of comments in program source code.
                    // But it would be easier to illustrate the idea with a real example submission here.
                    //
                    // if we construct each drinks_rpt record as a new entry,
                    // drink_comment_rpt data inside each drinks_rpt record can be accessed by path "root\drinks_rpt\drinks_rpt_grp\drink_comment_rpt" properly
                    //
                    //     "drinks_rpt": [
                    //         {
                    //             "drink_id": "green_tea",
                    //             "drinks_rpt_grp": {
                    //                 "drink_comment_rpt": [{
                    //                         "drink_comment": "r1",
                    //                         "__id": "336a6d87d55031ca67e527490efdbc0a7cfdcef6"
                    //                     }, {
                    //                         "drink_comment": "r2",
                    //                         "__id": "51523676c56ea5a1df8c6868a1414ff2d18a74f6"
                    //                     }, {
                    //                         "drink_comment": "r3",
                    //                         "__id": "882715f50b92ba9b1d4fa0a6a5b406e5b40203ca"
                    //                     }
                    //                 ]
                    //             },
                    //         },
                    //         {
                    //             "drink_id": "cola",
                    //             "drinks_rpt_grp": {
                    //                 "drink_comment_rpt": [{
                    //                         "drink_comment": "c1",
                    //                         "__id": "2fc9c39b7627fe1d82844017bee2b61a6e73e226"
                    //                     }, {
                    //                         "drink_comment": "c2",
                    //                         "__id": "740134d3b7878b5883a3d5936c006df26dbb0e2d"
                    //                     }
                    //                 ]
                    //             },
                    //         }



                    // extract path into an array for constructing a new entry
                    $arrayNames = explode('.', $repeatGroupArrayPath);
                    $arraySize = count($arrayNames);

                    $newEntry = [];

                    // assign repeat group record to last array element
                    $newEntry[$arrayNames[$arraySize - 1]] = $repeatGroupRecord;

                    for ($i = $arraySize - 2; $i >= 0; $i--) {
                        // assign data array to upper level array element
                        $newEntry[$arrayNames[$i]] = $newEntry;

                        // unset previous data array as it is no longer necessary
                        unset($newEntry[$arrayNames[$i + 1]]);
                    }

                    // find all child sections of this section
                    $childSections = $xlsform->xlsformTemplate->repeatingSections
                        ->where('parent_id', $section->id);

                    // process child sections one by one recursively
                    foreach ($childSections as $childSection) {
                        // ray('newEntityId: ' . $newEntityId);
                        // ray('newEntry:');
                        // ray($newEntry);

                        $this->processRepeatGroupSection($xlsform, $newEntry, $childSection, $submission, $newEntityId);
                    }
                }
            }
        }
    }



    /* ========== */



    // re-handle the updated submission content (submission content updated by user in front end)
    public function handleUpdatedSubmissionContent(Submission $submission)
    {
        // Note:
        // 1. It is necessary to call processEntry() to store submission data as entities, entity_values and custom table records
        // 1. It is not necessary to call getAttachedMedia() function again, as media files of a submission were associated with submission.
        // 2. It is not necessary to call app-specific processing again, as it should be triggered when submission is retrieved at first time

        $entry = $submission->content;

        $xlsformVersion = $submission->xlsformVersion;

        $this->processEntry($submission, $entry, $xlsformVersion);
    }

    public function processEntry(Submission $submission, array $entry, XlsformVersion $xlsformVersion): void
    {
        // ******** PROCESS DATA INTO DATASETS ******** //
        $sections = $xlsformVersion->xlsform->xlsformTemplate->xlsformTemplateSections;

        // add $entry into array, to retrieve a value from a deeply nested array using "dot" notation
        $rootEntry = ['root' => $entry];

        $xlsform = $xlsformVersion->xlsform;

        // one submissions should contain one main survey only.
        // this associative array stores the user-specified foreign key column name and created record id.
        // it will be populated when storing repeat groups data in custom tables.
        $mainSurveyId = [];

        foreach ($sections as $section) {
            $mainSurveyId = $this->processEntryFromSection($xlsform, $rootEntry, $section, $submission->id, $mainSurveyId);
        }
    }

    private function processEntryFromSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, $submissionId, $mainSurveyId)
    {
        // get the section schema and the dataset it is linked to;

        // create new dataset entity;

        // use the schema to populate the entity with variables from the $entry (flattened entry);

        if ($section->is_repeat == 0) {
            // handle main survey (root)
            $this->storeMainSurveyToEntity($xlsform, $entry, $section, $submissionId);

            $mainSurveyId = $this->storeMainSurveyToCustomTable($xlsform, $entry, $section, $submissionId);
        } else {
            // handle repeat group
            $this->storeRepeatGroupToEntity($xlsform, $entry, $section, $submissionId);

            $this->storeRepeatGroupToCustomTable($xlsform, $entry, $section, $submissionId, $mainSurveyId);
        }

        // assumption: main survey section should be processed first, therefore main survey Id will be available when processing repeat groups
        return $mainSurveyId;
    }

    // store main survey to entities and entity_value tables
    private function storeMainSurveyToEntity(Xlsform $xlsform, $entry, XlsformTemplateSection $section, $submissionId)
    {
        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        // create entity record for main survey (root)
        $entity = Entity::create([
            'dataset_id' => $section->dataset->id,
            'submission_id' => $submissionId,
            'model_type' => $section->dataset->entity_model,
        ]);

        // add polymorphic relationship
        $entity->owner()->associate($xlsform->owner)->save();

        // access the value of each ODK variable from a deeply nested array using "dot" notation
        foreach ($schema as $schemaItem) {
            $itemPath = 'root' . Str::replace('/', '.', $schemaItem['path']);
            $value = Arr::get($entry, $itemPath);

            if ($schemaItem['type'] != 'repeat' && $value !== null && $value != '' && !is_array($value)) {
                // store ODK variable value as entity value record

                // TODO: get label from correct language String entry.
                $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
                    'name'  => $schemaItem['name'],
                    'label' => $schemaItem['name'],
                ]);

                EntityValue::create([
                    'entity_id' => $entity->id,
                    'dataset_variable_name' => $datasetVariable->name,
                    'value' => $value,
                ]);
            }
        }
    }

    // store main survey to custom table (if any)
    // TODO: why does this return an array? Probably needs refactoring.
    private function storeMainSurveyToCustomTable(Xlsform $xlsform, $entry, XlsformTemplateSection $section, $submissionId): ?array
    {
        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        $mainSurveyId = [];

        // P.S. When deleting submission in application, we must delete related records for both generic approach and custom table approach

        // check whether this xlsform template section has a related database table
        $class = $section->dataset?->entity_model;

        if ($class) {
            $model = new $class;

            // check database table existence
            if (!Schema::hasTable($model->getTable())) {
                return null;
            }

            // delete previously stored records in this table (if any)
            $class::where('submission_id', $submissionId)->delete();

            // get data array from main survey
            $dataArray = $this->prepareDataArray($xlsform, $entry, $section, $schema, $model, $submissionId, $mainSurveyId);

            // to prevent saving empty record to database table
            $isEmptyRecord = true;

            foreach ($dataArray as $key => $value) {

                // skip item "submission_id" as it must contain a value
                if ($key == 'submission_id') {
                    continue;
                }

                // indicate this is not an empty record if any item contains value
                if ($value != null) {
                    $isEmptyRecord = false;

                    break;
                }
            }

            // if database table has column "properties", prepare it as JSON content with all attribute values
            $dataArray = $this->getArr($model, $xlsform, $entry, $section, $schema, $submissionId, $dataArray);


            // create a new database record
            if (!$isEmptyRecord) {
                $record = $class::create($dataArray);

                // if there is a user-specified foreign key column name in model class, store the main survey id into array
                if ($model->foreignKeyIdColumnName != '') {
                    $mainSurveyId[$model->foreignKeyIdColumnName] = $record->id;
                }
            }
        }

        return $mainSurveyId;
    }

    // a generic function to extract values from main survey and repeat group entry, returns an array for further processing
    private function prepareDataArray($xlsform, $entry, $section, $schema, $model, $submissionId, $mainSurveyId): array
    {
        // initialise array
        $result = [];

        // add submission Id to array
        $result['submission_id'] = $submissionId;

        // get all column names of a table
        $columnNames = Schema::getColumnListing($model->getTable());

        // get all foreign key details of a table
        $foreignKeyDetails = Schema::getForeignKeys($model->getTable());

        // store foreign key column name and foreign key table name in associative array
        // TODO: find Laravel array helper function to do the same in a simpler way
        $foreignKeyColumnNames = [];
        foreach ($foreignKeyDetails as $foreignKey) {
            foreach ($foreignKey['columns'] as $foreignKeyColumn) {
                $foreignKeyColumnNames[$foreignKeyColumn] = $foreignKey['foreign_table'];
            }
        }

        // access the value of each ODK variable from a deeply nested array using "dot" notation
        foreach ($schema as $schemaItem) {

            // extract value from main survey
            if ($section->is_repeat == 0) {
                $itemPath = 'root' . Str::replace('/', '.', $schemaItem['path']);
                $value = Arr::get($entry, $itemPath);

                // extract value from repeat group
            } else {
                $pathLength = Str::length($schemaItem['path']);
                $position = Str::position($schemaItem['path'], '/' . $section->structure_item . '/');
                $lengthToCut = $pathLength - $position;

                $itemPath = Str::substr($schemaItem['path'], ($position + 1) + Str::length($section->structure_item), $lengthToCut);

                $fullItemPath = 'rg' . Str::replace('/', '.', $itemPath);

                $value = Arr::get($entry, $fullItemPath);
            }

            // hardcode temporary as a quick workaround for area_xxx_ha ODK variables
            if (!is_array($value)) {
                if ($value == 'NaN') {
                    $value = null;
                }
            }

            // if app developer has defined a method of creating foreign key record in submission content, call that method:
            $class = config('filament-odk-link.submission.foreign_key_process_method.class');
            $method = config('filament-odk-link.submission.foreign_key_process_method.method');

            if (array_key_exists($schemaItem['name'], $foreignKeyColumnNames) && $class && $method) {
                $newRecordId = $class::$method($entry, $xlsform->owner, $schemaItem['name'], $value, $foreignKeyColumnNames[$schemaItem['name']]);

                if ($newRecordId != -1) {
                    // created new record in foreign key table, use newly created record ID
                    $result[$schemaItem['name']] = $newRecordId;
                } else {
                    // it is not necessary to create new record in foreign key table, use ID defined in submission
                    $result[$schemaItem['name']] = $value;
                }

                // foreign key ODK attribute handling is completed, contine to handle next ODK variable
                continue;
            }

            // handle different kind of data value
            if ($schemaItem['type'] === 'geopoint') {
                $gpsData = $this->extractGpsData($schemaItem, $columnNames, $value);
                $result = array_merge($result, $gpsData);
            } elseif (in_array($schemaItem['name'], $columnNames)) {
                $result[$schemaItem['name']] = $value;
            }
        }

        // if main survey Id exists in table's foreign key list, populate it to $result array
        foreach ($mainSurveyId as $key => $value) {
            if (array_key_exists($key, $foreignKeyColumnNames)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    // a generic function to extract values from main survey and repeat group entry, returns an array for properties column
    private function preparePropertiesArray($xlsform, $entry, $section, $schema, $model, $submissionId): array
    {
        // initialise array
        $result = [];

        // get all column names of a table
        $columnNames = Schema::getColumnListing($model->getTable());

        // these variables are ODK form specific, which are not necessary to store in properties column
        $odkVariablesToIgnore =
            [
                '__id',
                'instanceID',
                'meta',
                'deviceid',
                'start_time',
                'end_time',
                '_id',
                'uuid',
                '__version__',
                '_xform_id_string',
                '_uuid',
                '_attachments',
                '_status',
                '_geolocation',
                '_submission_time',
                '_tags',
                '_notes',
                '_validation_status',
                '_submitted_by',
            ];

        // access the value of each ODK variable from a deeply nested array using "dot" notation
        foreach ($schema as $schemaItem) {

            // extract value from main survey
            if ($section->is_repeat == 0) {
                $itemPath = 'root' . Str::replace('/', '.', $schemaItem['path']);
                $value = Arr::get($entry, $itemPath);

                // extract value from repeat group
            } else {
                $pathLength = Str::length($schemaItem['path']);
                $position = Str::position($schemaItem['path'], $section->structure_item);
                $lengthToCut = $pathLength - $position;

                $itemPath = Str::substr($schemaItem['path'], $position + Str::length($section->structure_item), $lengthToCut);

                $fullItemPath = 'rg' . Str::replace('/', '.', $itemPath);

                $value = Arr::get($entry, $fullItemPath);
            }

            // put this item into $result if
            // 1. it is not a geopoint
            // 2. it is not a ODK variable to ignore
            // 3. it's value is not null
            if (
                $schemaItem['type'] != 'geopoint' &&
                !in_array($schemaItem['name'], $odkVariablesToIgnore) &&
                $value != null
            ) {
                $result[$schemaItem['name']] = $value;
            }
        }

        return $result;
    }

    // a generic function to extract GPS data, returns an array
    private function extractGpsData($schemaItem, $columnNames, $value): array
    {
        $result = [];

        // handle GPS data
        // We expect the data model to have columns for latitude, longitude, altitude and accuracy
        // P.S. It would be more intuitive and generic to directly use column names latitude, longitude, altitude and accuracy
        if ($value != null) {
            if (in_array('latitude', $columnNames, true)) {
                $result['latitude'] = $value['coordinates'][1];
            }

            if (in_array('longitude', $columnNames, true)) {
                $result['longitude'] = $value['coordinates'][0];
            }

            if (in_array('altitude', $columnNames, true)) {
                $result['altitude'] = $value['coordinates'][2];
            }

            if (in_array('accuracy', $columnNames, true)) {
                $result['accuracy'] = $value['properties']['accuracy'];
            }
        }

        return $result;
    }

    // store repeat group to entities and entity_value tables
    private function storeRepeatGroupToEntity(Xlsform $xlsform, $entry, XlsformTemplateSection $section, $submissionId)
    {
        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        // find the path of repeat group first item
        $schemaPaths = $schema->pluck('path')->toArray();

        $position = Str::position($schemaPaths[0], '/' . $section->structure_item . '/');

        // construct the path for getting an array of repeat group
        $repeatGroupArrayPath = 'root' . Str::replace('/', '.', Str::substr($schemaPaths[0], 0, $position)) . '.' . $section->structure_item;

        // get the array for repeat group
        $repeatGroupArray = Arr::get($entry, $repeatGroupArrayPath);

        // it should be an array containing records for a repeat group
        if (is_array($repeatGroupArray)) {

            // handle each record in repeat group
            foreach ($repeatGroupArray as $repeatGroupRecord) {

                // create entity record for each repeat group record

                // if the section is not linked to a dataset, move on;
                if (!$section->dataset) {
                    continue;
                }

                $entity = Entity::create([
                    'dataset_id' => $section->dataset->id,
                    'submission_id' => $submissionId,
                    'parent_id' => Entity::where('submission_id', $submissionId)->where('dataset_id', $section->parent?->dataset->id)->first()->id ?? null,
                    'model_type' => $section->dataset->entity_model,
                ]);

                // add polymorphic relationship
                $entity->owner()->associate($xlsform->owner)->save();

                // get array element as record
                $repeatGroupEntry = ['rg' => $repeatGroupRecord];

                foreach ($schema as $schemaItem) {

                    $pathLength = Str::length($schemaItem['path']);
                    $position = Str::position($schemaItem['path'], '/' . $section->structure_item . '/');
                    $lengthToCut = $pathLength - $position;

                    $itemPath = Str::substr($schemaItem['path'], ($position + 1) + Str::length($section->structure_item), $lengthToCut);

                    $fullItemPath = 'rg' . Str::replace('/', '.', $itemPath);

                    $value = Arr::get($repeatGroupEntry, $fullItemPath);

                    if ($schemaItem['type'] != 'repeat' && $value != null && $value != '' && !is_array($value)) {

                        // TODO: get label from correct language String entry.
                        $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
                            'name' => $schemaItem['name'],
                            'label' => $schemaItem['name'],
                        ]);

                        // store ODK variable value as entity value record
                        EntityValue::create([
                            'entity_id' => $entity->id,
                            'dataset_variable_name' => $datasetVariable->name,
                            'value' => $value,
                        ]);
                    }
                }
            }
        }
    }

    // store repeat group to custom table (if any)
    private function storeRepeatGroupToCustomTable(Xlsform $xlsform, $entry, XlsformTemplateSection $section, $submissionId, $mainSurveyId): void
    {
        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        // find the path of repeat group first item
        $schemaPaths = $schema->pluck('path')->toArray();

        $position = Str::position($schemaPaths[0], '/' . $section->structure_item . '/');

        // construct the path for getting an array of repeat group
        $repeatGroupArrayPath = 'root' . Str::replace('/', '.', Str::substr($schemaPaths[0], 0, $position)) . '.' . $section->structure_item;

        // get the array for repeat group
        $repeatGroupArray = Arr::get($entry, $repeatGroupArrayPath);

        // it should be an array containing records for a repeat group
        if (is_array($repeatGroupArray)) {

            // P.S. When deleting submission in application, we must delete related records for both generic approach and custom table approach

            // check whether this xlsform template section has a related database table
            $class = $section->dataset?->entity_model;

            if ($class) {
                $model = new $class;

                // check database table existence
                if (!Schema::hasTable($model->getTable())) {
                    return;
                }

                // delete previously stored records in this table (if any)
                $class::where('submission_id', $submissionId)->delete();

                // handle each record in repeat group
                foreach ($repeatGroupArray as $repeatGroupRecord) {
                    // find the parent (if exists)
                    if ($parentDataset = $section->dataset?->parent) {
                        $parentClass = $section->dataset?->entity_model;
                    }

                    // get array element as record
                    $repeatGroupEntry = ['rg' => $repeatGroupRecord];

                    // get data array from repeat group entry
                    $dataArray = $this->prepareDataArray($xlsform, $repeatGroupEntry, $section, $schema, $model, $submissionId, $mainSurveyId);

                    // to prevent saving empty record to database table
                    $isEmptyRecord = true;

                    foreach ($dataArray as $key => $value) {

                        // for soils database table nutrient_balances, it does not have columns for individual attribute.
                        // the return value from function prepareDataArray() will contain submission_id only.
                        // let it create nutrient_balances, all attribute values will be fill in to JSON column nutrient_balances.properties afterwards

                        // skip item "submission_id" as it must contain a value
                        // if ($key == 'submission_id') {
                        //     dump('skip item submission_id as it must contain a value');
                        //     continue;
                        // }

                        // indicate this is not an empty record if any item contains value
                        if ($value != null) {
                            $isEmptyRecord = false;

                            break;
                        }
                    }

                    // if database table has column "properties", prepare it as JSON content with all attribute values
                    $dataArray = $this->getArr($model, $xlsform, $repeatGroupEntry, $section, $schema, $submissionId, $dataArray);


                    // create a new database record
                    if (!$isEmptyRecord) {
                        $class::create($dataArray);
                    }
                }
            }
        }
    }

    public function exportAsExcelFile(Xlsform $xlsform): BinaryFileResponse
    {
        return Excel::download(new SurveyExport($xlsform), $xlsform->title . '-' . now()->toDateTimeString() . '.xlsx');
    }

    private function getArr(mixed $model, Xlsform $xlsform, $entry, XlsformTemplateSection $section, mixed $schema, $submissionId, array $dataArray): array
    {
        if (Schema::hasColumn($model->getTable(), 'properties')) {
            $properties = $this->preparePropertiesArray($xlsform, $entry, $section, $schema, $model, $submissionId);
            $dataArray['properties'] = $properties;
        }

        // if database table has column "team_id", get owner id of xlsform, set it as team_id
        if (Schema::hasColumn($model->getTable(), 'team_id')) {

            $teamId = $xlsform->owner->getKey();

            $dataArray['team_id'] = $teamId;
        }

        return $dataArray;
    }
}
