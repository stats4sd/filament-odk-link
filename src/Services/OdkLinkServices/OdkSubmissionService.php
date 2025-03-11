<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;


use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\SurveyExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


trait OdkSubmissionService
{

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
                        'name' => $schemaItem['name'],
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
                    'name' => $schemaItem['name'],
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
