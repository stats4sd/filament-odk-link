<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\RequestException;
use Stats4sd\FilamentOdkLink\Exports\SqlViewExport;
use Stats4sd\FilamentOdkLink\Imports\XlsImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\AppUser;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsFormDrafts;
use Stats4sd\FilamentOdkLink\Exports\SurveyExport;

/**
 * All ODK Aggregation services should be able to handle ODK forms, so this interface should always be used.
 */
class OdkLinkService
{
    public function __construct(protected string $endpoint)
    {
    }

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
     * @throws RequestException
     */
    public function createProject(string $name): array
    {
        $token = $this->authenticate();

        // prepend platform identifier to project name;
        $name = config('app.name') . ' -- ' . $name;

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

        // create new app-user
        $userResponse = Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/app-users", [
                'displayName' => 'All Forms - ' . $odkProject->owner->name . ' - ' . $odkProject->appUsers()->count() + 1,
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
     * @throws RequestException
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
     * @throws RequestException
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
     * @throws RequestException
     */
    public function createDraftForm(WithXlsFormDrafts $xlsform): array
    {
        $token = $this->authenticate();

        $file = file_get_contents($xlsform->xlsfile);

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
            ->post($url)
            ->throw()
            ->json();

        // when creating a new draft for an existing form, the full form details are not returned. In this case, the $xlsform record can remain unchanged
        if (isset($response['xmlFormId'])) {
            $xlsform->update(['odk_id' => $response['xmlFormId']]);
        }
        $this->updateSchema($xlsform);

        // deploy media files
        $this->uploadMediaFileAttachments($xlsform);

        return $this->getDraftFormDetails($xlsform);
    }

    /**
     * Gets the draft form details for a given xlsform
     *
     * @throws RequestException
     */
    public function getDraftFormDetails(WithXlsFormDrafts $xlsform): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft")
            ->throw()
            ->json();
    }

    /**
     * Gets the expected media items for a given draft form template
     * @throws RequestException
     */
    public function getRequiredMedia(WithXlsFormDrafts $xlsformTemplate): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsformTemplate->owner->odkProject->id}/forms/{$xlsformTemplate->odk_id}/attachments")
            ->throw()
            ->json();

    }


    #########################################################
    ### FORM MEDIA ATTACHMENTS
    #########################################################

    /**
     * Uploads all media files for an XLSform to ODK Central - both static files and dyncsv files
     * @throws RequestException
     */
    public function uploadMediaFileAttachments(WithXlsFormDrafts $xlsform): bool
    {
        // static files
        $requiredFixedMedia = $xlsform->attachedFixedMedia()->get();

        if ($requiredFixedMedia && count($requiredFixedMedia) > 0) {

            foreach ($requiredFixedMedia as $requiredMediaItem) {
                $this->uploadSingleMediaFile($xlsform, $requiredMediaItem);
            }

        }


        // dynamic files
        $requiredDataMedia = $xlsform->attachedDataMedia()->get();

        if ($requiredDataMedia && count($requiredDataMedia) > 0) {
            foreach ($requiredDataMedia as $requiredMediaItem) {

                // if there is a static upload, use it;
                $media = $requiredDataMedia->getFirstMedia();
                if ($media) {
                    $this->uploadSingleMediaFile($xlsform, $requiredMediaItem);
                } else {
                    // handle csv file generation...

                }

            }
        }

        return true;

    }

    /**
     * Uploads a single media file to the given xlsform
     *
     * @throws RequestException
     */
    public function uploadSingleMediaFile(Xlsform $xlsform, string $filePath): array
    {
        $token = $this->authenticate();
        $file = file_get_contents(Storage::disk(config('filament-odk-link.storage.xlsforms'))->path($filePath));

        $mimeType = mime_content_type(Storage::disk(config('filament-odk-link.storage.xlsforms'))->path($filePath));
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

    public function getAttachedMedia($entry, string $token, Xlsform $xlsform, Model|Submission|null $submission): void
    {
        // ******** PROCESS MEDIA ******** //
        //check if media is expected
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
    public function updateSchema(WithXlsFormDrafts $xlsform): void
    {
        $token = $this->authenticate();

        // upddate the stored schema with the new draft;
        $schema = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/fields?odata=true")
            ->throw()
            ->json();

        // get the xlsform and merge in specific details to the schema returned from ODK Central
        $surveyExcel = (new XlsImport)->toCollection($xlsform->getMedia('xlsform_file')->first()->getPathRelativeToRoot(), config('filament-odk-link.storage.xlsforms'), \Maatwebsite\Excel\Excel::XLSX)[0];

        // TODO: update this to work with any default language
        $schema = collect($schema)->map(function (array $item) use ($surveyExcel): array {
            if ($row = $surveyExcel->where('name', $item['name'])->first()) {
                $item['value_type'] = $row['type'];
                $item['label_english'] = $row['labelenglish'];
                $item['hint_english'] = $row['hintenglish'];
            }

            return $item;
        })->toArray();

        $xlsform->updateQuietly(['schema' => $schema]);
    }

    /**
     * Creates a new csv lookup file from the database;
     */
    private function createCsvLookupFile(Xlsform $xlsform, mixed $lookup): string
    {

        $filePath = 'xlsforms' . $xlsform->id . '/' . $lookup['csv_name'] . '.csv';

        if ($lookup['per_owner'] === '1') {
            $owner = $xlsform->owner;
        } else {
            $owner = null;
        }

        Excel::store(
            new SqlViewExport($lookup['mysql_name'], $owner, $lookup['owner_foreign_key']),
            $filePath,
            config('filament-odk-link.storage.xlsforms')
        );

        // If the csv file is used with "select_one_from_external_file" (or multiple) it must not have any enclosure characters:
        if (isset($lookup['external_file']) && $lookup['external_file'] === '1') {
            $contents = Storage::disk(config('filament-odk-link.storage.xlsforms'))->get($filePath);
            $contents = Str::of($contents)->replace('"', '');

            Storage::disk(config('filament-odk-link.storage.xlsforms'))->put($filePath, $contents);
        }

        return $filePath;
    }

    public function test(): string
    {

        $data = Http::withToken($this->authenticate())
            ->get("{$this->endpoint}/projects/24/app-users")
            ->throw()
            ->json();

        AppUser::create(
            [
                'id' => $data[0]['id'],
                'odk_project_id' => $data[0]['projectId'],
                'type' => $data[0]['type'],
                'display_name' => $data[0]['displayName'],
                'token' => $data[0]['token'],
            ]
        );

        return 'hi';
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

        // base xlsfile name
        $fileName = collect(explode('/', $xlsform->xlsfile))->last();
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

    public function getSubmissions(Xlsform $xlsform): void
    {
        $token = $this->authenticate();
        $oDataServiceUrl = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}.svc";

        $results = Http::withToken($token)
            ->get($oDataServiceUrl . '/Submissions?$expand=*')
            ->throw()
            ->json();

        // only process new submissions
        $resultsToAdd = Collect($results['value'])->whereNotIn('__id', $xlsform->submissions->pluck('odk_id')->toArray());

        foreach ($resultsToAdd as $entry) {


            // ******* CREATE SUBMISSION RECORD ******* //
            $xlsformVersion = $xlsform->xlsformVersions()->firstWhere('version', $entry['__system']['formVersion']);

            // TODO: handle case where xlsformversion is not found

            // Question: For column submission.content, should we store the original $entry instead of the return value of processEntry()?
            $submission = $xlsformVersion?->submissions()->create([
                'odk_id' => $entry['__id'],
                'submitted_at' => (new Carbon($entry['__system']['submissionDate']))->toDateTimeString(),
                'submitted_by' => $entry['__system']['submitterName'],
                'content' => $entry,
            ]);

            $this->processEntry($submission, $entry, $xlsformVersion);
            $this->getAttachedMedia($entry, $token, $xlsform, $submission);


            // GET schema information for the specific version
            // TODO: hook this into the select variables work from the other branch...

            $schema = collect($xlsformVersion->schema);


            // pass 0 as mainSurveyEntityId at the very beginning
            // $entryToStore = $this->processEntry($xlsform, $entry, $schema, $submission->id, 'root', null);

            $sections = $xlsform->xlsformTemplate->xlsformTemplateSections;

            // add $entry into array, to retrieve a value from a deeply nested array using "dot" notation
            $rootEntry = ['root' => $entry];

            foreach ($sections as $section) {
                $this->processEntryFromSection($xlsform, $rootEntry, $section, $submission->id);
            }


            // ******** CALL APP-SPECIFIC PROCESSING ******** //

            // if app developer has defined a method of processing submission content, call that method:
            $class = config('filament-odk-link.submission.process_method.class');
            $method = config('filament-odk-link.submission.process_method.method');

            if ($class && $method) {
                $class::$method($submission);
            }

        }

    }

    public function processEntry(Submission $submission, array $entry, XlsformVersion $xlsformVersion): void
    {
        // ******** PROCESS DATA INTO DATASETS ******** //
        $sections = $xlsformVersion->xlsform->xlsformTemplate->xlsformTemplateSections;

        // add $entry into array, to retrieve a value from a deeply nested array using "dot" notation
        $rootEntry = ['root' => $entry];

        $xlsform = $xlsformVersion->xlsform;

        foreach ($sections as $section) {
            $this->processEntryFromSection($xlsform, $rootEntry, $section, $submission->id);
        }
    }


    private function processEntryFromSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, $submissionId)
    {
        // get the section schema and the dataset it is linked to;

        // create new dataset entity;

        // use the schema to populate the entity with variables from the $entry (flattened entry);


        // handle main survey (root)
        if ($section->is_repeat == 0) {

            // exclude structure items from section schema, as there is no value to be stored for a structure item
            $schema = $section->schema->where('type', '!=', 'structure');

            // create entity record for main survey (root)
            $entity = Entity::create([
                'dataset_id' => $section->dataset->id,
                'submission_id' => $submissionId,
            ]);

            // add polymorphic relationship
            $entity->owner()->associate($xlsform->owner)->save();

            // access the value of each ODK variable from a deeply nested array using "dot" notation
            foreach ($schema as $schemaItem) {
                $itemPath = 'root' . Str::replace('/', '.', $schemaItem['path']);
                $value = Arr::get($entry, $itemPath);

                // dump($schemaItem['name'] . ' : ' . $value);

                if ($schemaItem['type'] != 'repeat' && $value !== null && $value != '' && !is_array($value)) {
                    // store ODK variable value as entity value record
                    EntityValue::create([
                        'entity_id' => $entity->id,
                        'dataset_variable_id' => $schemaItem['name'],
                        'value' => $value,
                    ]);
                }
            }

            // handle repeat group
        } else {

            // exclude structure items from section schema, as there is no value to be stored for a structure item
            $schema = $section->schema->where('type', '!=', 'structure');

            // find the path of repeat group first item
            $schemaPaths = $schema->pluck('path')->toArray();
            // dump($schemaPaths[0]);

            $position = Str::position($schemaPaths[0], $section->structure_item);

            // construct the path for getting an array of repeat group
            $repeatGroupArrayPath = 'root' . Str::replace('/', '.', Str::substr($schemaPaths[0], 0, $position)) . $section->structure_item;
            // dump($repeatGroupArrayPath);

            // get the array for repeat group
            $repeatGroupArray = Arr::get($entry, $repeatGroupArrayPath);
            // dump($repeatGroupArray);

            // it should be an array containing records for a repeat group
            if (is_array($repeatGroupArray)) {
                // dump("This is an array");

                // handle each record in repeat group
                foreach ($repeatGroupArray as $repeatGroupRecord) {
                    // dump($repeatGroupRecord);

                    // create entity record for each repeat group record
                    $entity = Entity::create([
                        'dataset_id' => $section->dataset->id,
                        'submission_id' => $submissionId,
                        'parent_id' => Entity::where('submission_id', $submissionId)->where('dataset_id', $section->parent->dataset->id)->first()->id,
                    ]);

                    // add polymorphic relationship
                    $entity->owner()->associate($xlsform->owner)->save();

                    // get array element as record
                    $repeatGroupEntry = ['rg' => $repeatGroupRecord];

                    foreach ($schema as $schemaItem) {
                        $pathLength = Str::length($schemaItem['path']);
                        $position = Str::position($schemaItem['path'], $section->structure_item);
                        $lengthToCut = $pathLength - $position;

                        $itemPath = Str::substr($schemaItem['path'], $position + Str::length($section->structure_item), $lengthToCut);
                        // dump('$itemPath : ' . $itemPath);

                        $fullItemPath = 'rg' . Str::replace('/', '.', $itemPath);
                        // dump('$fullItemPath : ' . $fullItemPath);

                        $value = Arr::get($repeatGroupEntry, $fullItemPath);
                        // dump($schemaItem['name'] . ' : ' . $value);

                        if ($schemaItem['type'] != 'repeat' && $value != null && $value != '' && !is_array($value)) {
                            // store ODK variable value as entity value record
                            EntityValue::create([
                                'entity_id' => $entity->id,
                                'dataset_variable_id' => $schemaItem['name'],
                                'value' => $value,
                            ]);
                        }
                    }

                }

            } else {
                // dump("This is NOT an array");
            }

        }

    }


    public function exportAsExcelFile(Xlsform $xlsform)
    {
        return Excel::download(new SurveyExport($xlsform), $xlsform->title . '-' . now()->toDateTimeString() . '.xlsx');

    }


}
