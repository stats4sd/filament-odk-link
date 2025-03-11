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

    /** Get all attached media for a given submission + save into application storage (photos, videos, audio, etc captured during the survey) */
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

    /** Get the total count of live submissions for a given Xlsform  */
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

    /** Retrieve and process all new submissions for a given Xlsform */
    public function getSubmissions(Xlsform $xlsform): int
    {
        ray('getSubmissions');

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

    /** Process a single submission using the 'XlsformTemplateSections' schema */
    public function processSubmission(Submission $submission, array $entry, XlsformVersion $xlsformVersion): void
    {
        ray('processSubmission: ' . $submission->id);

        $xlsform = $xlsformVersion->xlsform;

        // add $entry into array, to retrieve a value from a deeply nested array using "dot" notation
        $rootEntry = ['root' => $entry];

        $rootSection = $xlsformVersion->xlsform->xlsformTemplate->rootSection;

        $this->processRootSection($xlsform, $rootEntry, $rootSection, $submission);
    }

    /** Process the 'root' section of the survey based on the root XlsformTemplateSection schema */
    private function processRootSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, Submission $submission)
    {
        ray('processRootSection For Submission: ' . $submission->id);
        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        // create entity record for main survey (root)
        $entity = Entity::create([
            'dataset_id' => $section->dataset->id,
            'submission_id' => $submission->id,
            'owner_id' => $submission->owner->getKey(),
            'model_type' => $section->dataset->entity_model,
        ]);

        // create entity_values records
        // access the value of each ODK variable from a deeply nested array using "dot" notation
        $entityValues = [];

        foreach ($schema as $schemaItem) {
            $itemPath = 'root' . Str::replace('/', '.', $schemaItem['path']);
            $value = Arr::get($entry, $itemPath);

            if ($schemaItem['type'] != 'repeat' && $value !== null && $value != '' && !is_array($value)) {
                // store ODK variable value as entity value record

//                // TODO: get label from correct language String entry.
//                $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
//                    'name' => $schemaItem['name'],
//                    'label' => $schemaItem['name'],
//                ]);

                $entityValues[] = [
                    'entity_id' => $entity->id,
                    'dataset_variable_name' => $schemaItem['name'],
                    'value' => $value,
                ];
            }
        }

        $entity->values()->insert($entityValues);

        // find all child sections of this section
        $childSections = $xlsform->xlsformTemplate->repeatingSections
            ->where('parent_id', $section->id);

        // process child sections one by one recursively
        foreach ($childSections as $childSection) {
            $this->processRepeatGroupSection($xlsform, $entry, $childSection, $submission, $entity->id);
        }
    }

    /** Recursive function to process each repeat group section using the specific XlsformTemplateSection schema */
    private function processRepeatGroupSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, Submission $submission, $entityId)
    {
        ray('processRepeatGroupSection for submission : ' . $submission->id . ' - section: ' . $section->id);

        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        // find the path of repeat group first item
        $schemaPaths = $schema->pluck('path')->toArray();

        $position = Str::position($schemaPaths[0], '/' . $section->structure_item . '/');

        // construct the path for getting an array of repeat group
        $repeatGroupArrayPath = 'root' . Str::replace('/', '.', Str::substr($schemaPaths[0], 0, $position)) . '.' . $section->structure_item;

        // get the array for repeat group
        $repeatGroupArray = Arr::get($entry, $repeatGroupArrayPath);

        // if $repeatGroupArray is null, it means this section has no entries and so does not exist in the submission data
        if(!$repeatGroupArray) {
            return;
        }

        // handle each record in repeat group
        foreach ($repeatGroupArray as $repeatGroupRecord) {

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

            // add polymorphic relationship
            $entity->owner()->associate($xlsform->owner)->save();

            // get array element as record
            $repeatGroupEntry = ['rg' => $repeatGroupRecord];

            $entityValues = [];

            foreach ($schema as $schemaItem) {

                $pathLength = Str::length($schemaItem['path']);
                $position = Str::position($schemaItem['path'], '/' . $section->structure_item . '/');
                $lengthToCut = $pathLength - $position;

                $itemPath = Str::substr($schemaItem['path'], ($position + 1) + Str::length($section->structure_item), $lengthToCut);

                $fullItemPath = 'rg' . Str::replace('/', '.', $itemPath);

                $value = Arr::get($repeatGroupEntry, $fullItemPath);

                if ($schemaItem['type'] != 'repeat' && $value != null && $value != '' && !is_array($value)) {

//                    // TODO: get label from correct language String entry.
//                    $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
//                        'name' => $schemaItem['name'],
//                        'label' => $schemaItem['name'],
//                    ]);

                    // store ODK variable value as entity value record
                    $entityValues[] = [
                        'entity_id' => $entity->id,
                        'dataset_variable_name' => $schemaItem['name'],
                        'value' => $value,
                    ];
                }
            }

            $entity->values()->insert($entityValues);

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
                $this->processRepeatGroupSection($xlsform, $newEntry, $childSection, $submission, $entity->id);
            }
        }
    }

    /** If a submission is manually edited by the user in Laravel, trigger the process submission to re-create the entities + values. */
    public function handleUpdatedSubmissionContent(Submission $submission)
    {
        // Note:
        // 1. It is necessary to call processSubmission() to store submission data as entities, entity_values and custom table records
        // 1. It is not necessary to call getAttachedMedia() function again, as media files of a submission were associated with submission.
        // 2. It is not necessary to call app-specific processing again, as it should be triggered when submission is retrieved at first time

        $entry = $submission->content;

        $xlsformVersion = $submission->xlsformVersion;

        $this->processSubmission($submission, $entry, $xlsformVersion);
    }

    /** Export all submission data for a specific Xlsform. Gives one worksheet for the main survey and one worksheet per repeat group, similar to Kobotoolbox, Ona etc. */
    public function exportAsExcelFile(Xlsform $xlsform): BinaryFileResponse
    {
        return Excel::download(new SurveyExport($xlsform), $xlsform->title . '-' . now()->toDateTimeString() . '.xlsx');
    }
}
