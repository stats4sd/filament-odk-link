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
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\AppUser;
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

        // upddate the stored schema with the new draft;
        $schema = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/draft/fields?odata=true")
            ->throw()
            ->json();

        $xlsform->updateQuietly(['schema' => $schema]);

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

        if($requiredDataMedia && count($requiredDataMedia)  > 0) {
            foreach ($requiredDataMedia as $requiredMediaItem) {

                // if there is a static upload, use it;
                $media = $requiredDataMedia->getFirstMedia();
                if($media) {
                    $this->uploadSingleMediaFile($xlsform, $requiredMediaItem);
                }

                else {
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

        //        // create a new version locally
        //        $version = 1;
        //
        //        // if there is an existing version; increment the version number;
        //        if ($xlsform->xlsformVersions()->count() > 0) {
        //            $version = $xlsform->xlsformVersions()->orderBy('version', 'desc')->first()->version + 1;
        //        }

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

    /**
     * @param  mixed  $version
     * @return Model
     */
    public function createNewVersion(Xlsform $xlsform, array $versionDetails): XlsformVersion
    {
        $token = $this->authenticate();

        // base xlsfile name
        $fileName = collect(explode('/', $xlsform->xlsfile))->last();
        $versionSlug = Str::slug($versionDetails['version']);

        // copy xlsform file to store linked to this version forever
        Storage::disk(config('filament-odk-link.storage.xlsforms'))
            ->copy(
                $xlsform->xlsfile,
                "xlsforms/{$xlsform->id}/versions/{$versionSlug}/{$fileName}"
            );

        // get schema from ODK Central;
        $schema = Http::withToken($token)
            ->get("{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/versions/{$versionDetails['version']}/fields?odata=true")
            ->throw()
            ->json();

        // create new active version with latest version number;
        return $xlsform->xlsformVersions()->create([
            'version' => $versionDetails['version'],
            'xlsfile' => "xlsforms/{$xlsform->id}/versions/{$versionSlug}/{$fileName}",
            'odk_version' => $versionDetails['version'],
            'active' => true,
            'schema' => $schema,
        ]);
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

            ray($entry);

            $xlsformVersion = $xlsform->xlsformVersions()->firstWhere('version', $entry['__system']['formVersion']);

            // Question: For column submission.content, should we store the original $entry instead of the return value of processEntry()?
            $submission = $xlsformVersion?->submissions()->create([
                'odk_id' => $entry['__id'],
                'submitted_at' => (new Carbon($entry['__system']['submissionDate']))->toDateTimeString(),
                'submitted_by' => $entry['__system']['submitterName'],
                'content' => $entry,
            ]);


            // GET schema information for the specific version
            // TODO: hook this into the select variables work from the other branch...

            $schema = collect($xlsformVersion->schema);


            // pass 0 as mainSurveyEntityId at the very beginning

            $sections = $xlsform->xlsformTemplate->xlsformTemplateSections;

            foreach($sections as $section) {
                $this->processEntryFromSection($entry, $section);
            }

//            $entryToStore = $this->processEntry($xlsform, $entry, $schema, $submission->id, 'root', null);


            // if app developer has defined a method of processing submission content, call that method:
            $class = config('filament-odk-link.submission.process_method.class');
            $method = config('filament-odk-link.submission.process_method.method');

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

            if ($class && $method) {
                $class::$method($submission);
            }

        }

    }


    // WIP
    public function processEntry($xlsform, $entry, $schema, $submissionId, $entryName, $entityId)
    {
        // dump('     ***** $entryName: ' . $entryName);
        // dump('     ***** $entityId: ' . $entityId);
        // dump('     ***** $entry: ');
        // dump($entry);
        // dump('     ***** $schema: ');
        // dump($schema);

        // find xlsform template section for root and repeat group
        $xlsformTemplateSection = $xlsform->xlsformTemplate->xlsformTemplateSections->firstWhere('structure_item', $entryName);

        if ($xlsformTemplateSection != null) {
            // dump('Xlsform template section found, it is either root or repeat group');

            // for repeat group (i.e. non root section), use schema of xlsform template section
            if ($entryName != 'root') {
                $schema = $xlsformTemplateSection->schema;
            }

        } else {
            // dump('Xlsform template section not found, it is not root or repeat group');
            $xlsformTemplateSection = $xlsform->xlsformTemplate->xlsformTemplateSections->firstWhere('is_repeat', 0);
        }


        // prepare entity
        if (($entryName == 'root') || ($xlsformTemplateSection->is_repeat == 1)) {

            // create new entity for root or repeat group
            $entity = Entity::create([
                'dataset_id' => $xlsformTemplateSection->dataset->id,
                'submission_id' => $submissionId,
            ]);

            // add polymorphic relationship
            $entity->owner()->associate($xlsform->owner)->save();

            $entityId = $entity->id;

        } else {

            // This is not root or repeat group. It can be:
            // 1. Structure item under root
            // 2. Structture item under repeat group

            // Get existing entity. It is either root entity or repeat group entity
            $entity = Entity::find($entityId);

        }


        // store attributes as entity_value record, call processEntry() for repeat group or structure item
        foreach ($entry as $key => $value) {

            // if (is_array($value)) {
            //     dump($key . ' is an array');
            // }

            // check whether attribute key exist in schema
            $schemaEntry = $schema->firstWhere('name', '=', $key);

            // create entity_values record for key value pair
            if ($schemaEntry != null &&
                $schemaEntry['type'] != 'structure' &&
                $schemaEntry['type'] != 'repeat' &&
                $key != null &&
                $value != null &&
                !is_array($value)) {

                // dump('==> create entity_value record for ' . '(' . $key . ' => ' . $value . ') with entity_id ' . $entityId);

                EntityValue::create([
                    'entity_id' => $entityId,
                    'dataset_variable_id' => $key,
                    'value' => $value,
                ]);
            }

            // do not need this checking anymore. We use the schema of a xlsform template section instead of the schema of the entity xlsform
            // need this checking when we use schema of the entire xlsform
            if (! $schemaEntry) {
                continue;
            }

            if ($schemaEntry['type'] === 'structure' || is_array($value)) {
                // dump('     SSSSS ' . $key . ' is a structure item or array, call processEntry() to handle');
                $entry = array_merge($this->processEntry($xlsform, $value, $schema, $submissionId, $key, $entityId), $entry);
                unset($entry[$key]);
            }

            if ($schemaEntry['type'] === 'repeat') {
                // dump('     RRRRR ' . $key . ' is a repeat group, call processEntry() to handle');

                // $entry[$key] = collect($entry[$key])->map(function ($repeatEntry) use ($schema, $xlsform, $datasets) {
                //     return $this->processEntry($repeatEntry, $schema, $xlsform, $datasets);
                // })->toArray();

                // handle each array element of repeating group
                foreach ($entry as $arrayElement) {
                    if ($arrayElement != null && is_array($arrayElement)) {
                        // dump('arrayElement');
                        // dump($arrayElement);

                        $this->processEntry($xlsform, $arrayElement, $schema, $submissionId, $key, $entityId);
                    }
                }
            }

        }


        // dump('     ///// $entityId: ' . $entityId);
        // dump('     ///// $entryName: ' . $entryName);

        return $entry;
    }


    public function exportAsExcelFile(Xlsform $xlsform)
    {
        return Excel::download(new SurveyExport($xlsform), $xlsform->title . '-' . now()->toDateTimeString() . '.xlsx');
    }


    //
    //    public function processEntryNOPE(array $entryToStore, array $entry, Collection $schema, array $repeatPath = []): array
    //    {
    //        // get reference to correct nested part of the $entryToStore (e.g. if we are inside a repeat, we will want to add keys/values to the current level in the repeat;
    //
    //
    //        if (count($repeatPath) > 0) {
    //            $ref = &$entryToStore;
    //            foreach ($repeatPath as $path) {
    //
    //                // check if there is already a path to here
    //                if (!isset($ref[$path])) {
    //                    $ref[$path] = [];
    //                }
    //                $ref = &$ref[$path];
    //            }
    //            dump($ref, $repeatPath);
    //        }
    //
    //
    //        foreach ($entry as $key => $value) {
    //            $schemaEntry = $schema->firstWhere('name', '=', $key);
    //
    //            if (!$schemaEntry) {
    //                $ref[$key] = $value;
    //                continue;
    //            }
    //
    //            switch ($schemaEntry['type']) {
    //                case 'repeat':
    //
    //                    $repeatPath[] = $key;
    //                    $loop = 0;
    //
    //                    foreach ($value as $repeatItem) {
    //                        array_pop($repeatPath);
    //                        $repeatPath[] = $loop;
    //                        $ref = $this->processEntry($entryToStore, $repeatItem, $schema, $repeatPath);
    //
    //                        $loop++;
    //                    }
    //                    break;
    //
    //                case 'structure':
    //                    $ref = $this->processEntry($entryToStore, $value, $schema, $repeatPath);
    //                    break;
    //
    //                default:
    //                    $ref[$key] = $value;
    //
    //                    break;
    //            }
    //
    //        }
    //        return $entryToStore;
    //    }


    private function processEntryFromSection($entry, XlsformTemplateSection $section)
    {
        // get the section schema and the dataset it is linked to;

        // create new dataset entity;

        // use the schema to populate the entity with variables from the $entry (flattened entry);

    }

}
