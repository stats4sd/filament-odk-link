<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\SurveyExport;
use Stats4sd\FilamentOdkLink\Jobs\OdkSubmissions\ProcessOdkSubmission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformVersion;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait OdkSubmissionService
{
    /** Get all attached media for a given submission + save into application storage (photos, videos, audio, etc captured during the survey) */
    public function getAttachedMedia($entry, string $token, Xlsform $xlsform, ?Submission $submission, bool $draft = false): void
    {
        $endpoint = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}";

        if ($draft) {
            $endpoint .= '/draft';
        }

        // ******** PROCESS MEDIA ******** //
        // check if media is expected
        if ($entry['__system']['attachmentsPresent'] > 0) {
            $mediaPresent = Http::withToken($token)
                ->get("{$endpoint}/submissions/{$entry['__id']}/attachments")
                ->throw()
                ->json();

            foreach ($mediaPresent as $mediaItem) {

                // download the attachment
                $result = Http::withToken($token)
                    ->get("{$endpoint}/submissions/{$entry['__id']}/attachments/{$mediaItem['name']}")
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
        if (! $results->ok()) {
            return null;
        }

        return count($results->json());
    }

    public function updateSubmission(Submission $submission)
    {
        $token = $this->authenticate();

        // get updated metadata
        $response = Http::withToken($token)
            ->withHeaders([
                'X-Extended-Metadata' => 'true',
            ])
            ->get("{$this->endpoint}/projects/{$submission->xlsform->owner->odkProject->id}/forms/{$submission->xlsform->odk_id}/submissions/{$submission->odk_id}")
            ->throw()
            ->json();

        $submission->update([
            'odk_latest_version_id' => $response['currentVersion']['instanceId'],
            'updated_at' => $response['updatedAt'],
            'updated_by' => $response['currentVersion']['submitter']['displayName'],
        ]);

        $oDataServiceUrl = "{$this->endpoint}/projects/{$submission->xlsform->owner->odkProject->id}/forms/{$submission->xlsform->odk_id}";

        $results = Http::withToken($token)
            ->get($oDataServiceUrl.'.svc/Submissions?$expand=*&$filter=__system/updatedAt ge '.$submission->updated_at->toISOString().' and __system/updatedAt le '.$submission->updated_at->addSeconds(1)->toISOString())
            ->throw()
            ->json();

        $result = collect($results['value'])
            ->filter(fn ($content) => $content['__id'] === $submission->odk_id)
            ->first();

        $submission->update([
            'content' => $result,
        ]);
    }

    /** Retrieve and process all new submissions for a given Xlsform */
    public function getSubmissions(Xlsform $xlsform, bool $draft = false): int
    {
        $currentSubmissions = $xlsform->submissions()->withoutGlobalScope('ignore_drafts')->withTrashed()->get();

        $currentSubmissionIds = $currentSubmissions->pluck('odk_id');
        $currentSubmissionLatestIds = $currentSubmissions->pluck('odk_latest_version_id');

        $token = $this->authenticate();

        $metadataUrl = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/submissions";
        $oDataServiceUrl = "{$this->endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}";

        if ($draft) {
            $metadataUrl = Str::replaceLast('/submissions', '/draft/submissions', $metadataUrl);
            $oDataServiceUrl .= '/draft';
        }

        $submissionMetadata = Http::withToken($token)
            ->get($metadataUrl)
            ->throw()
            ->json();

        $newSubmissions = collect($submissionMetadata)
            ->filter(fn (array $result) => $currentSubmissionIds->doesntContain($result['instanceId']));

        $updatedSubmissions = collect($submissionMetadata)
            ->filter(fn (array $result) => $currentSubmissionIds->contains($result['instanceId']) &&
                $currentSubmissionLatestIds->doesntContain($result['currentVersion']['instanceId'])
            );

        $results = Http::withToken($token)
            ->get($oDataServiceUrl.'.svc/Submissions?$expand=*')
            ->throw()
            ->json();

        // merge with metadata
        $resultsToAdd = collect($results['value'])
            ->filter(fn (array $result) => $newSubmissions->contains('instanceId', $result['__id']) ||
                $updatedSubmissions->contains('instanceId', $result['__id'])
            )
            ->map(function (array $result) use ($submissionMetadata) {
                $result['odk_id'] = $result['__id'];
                $result['odk_latest_version_id'] = collect($submissionMetadata)->firstWhere('instanceId', $result['__id'])['currentVersion']['instanceId'];

                return $result;
            });

        foreach ($resultsToAdd as $entry) {

            // ******* CREATE SUBMISSION RECORD ******* //
            $xlsformVersion = $xlsform->xlsformVersions()
                ->with('submissions')
                ->firstWhere('version', $entry['__system']['formVersion']);

            if (! $xlsformVersion) {

                $messageContent = collect([
                    'formVersion' => $entry['__system']['formVersion'],
                    'xlsformId' => $xlsform->id,
                    'xlsformTitle' => $xlsform->title,
                    'ownerName' => $xlsform->owner->name,
                ]);

                if (config('app.env') === 'local') {
                    throw new \Exception('The system tried to get submission data for a form version that does not exist. LOCAL ENVIRONMENT: if you are testing a form that may have been updated on ODK Central directly, or through another app environment, please run `php artisan app:update-xlsform-versions-from-odk-central`, and try pulling the submissions again.');
                }

                throw new \Exception('The system tried to get submission data for a form version that does not exist.  Please copy the following details and send them to the system administrator: '.$messageContent->map(fn ($item, $key) => "$key: $item")->implode(', '), 500);
            }

            $submission = $xlsformVersion->submissions()->updateOrCreate(
                ['odk_id' => $entry['odk_id']],
                [
                    'odk_latest_version_id' => $entry['odk_latest_version_id'],
                    'submitted_at' => (new Carbon($entry['__system']['submissionDate']))->toDateTimeString(),
                    'submitted_by' => $entry['__system']['submitterName'],
                    'content' => $entry,
                    'draft_data' => $draft,
                ]);

            // For live data, process into datasets + entities in the database
            if (! $draft) {

                ProcessOdkSubmission::dispatch($submission, $entry, $xlsformVersion);

                $this->getAttachedMedia($entry, $token, $xlsform, $submission, $draft);

            }
            // ******** CALL APP-SPECIFIC PROCESSING ******** //

            //            // if app developer has defined a method of processing submission content, call that method:
            //            $class = config('filament-odk-link.submission.process_method.class');
            //            $method = config('filament-odk-link.submission.process_method.method');
            //
            //            if ($class && $method) {
            //                $class::$method($submission);
            //            }
        }

        return $resultsToAdd->count();
    }

    /** Process a single submission using the 'XlsformTemplateSections' schema */
    public function processSubmission(Submission $submission, array $entry, XlsformVersion $xlsformVersion): void
    {
        $xlsform = $xlsformVersion->xlsform;

        // add $entry into array, to retrieve a value from a deeply nested array using "dot" notation
        $rootEntry = ['root' => $entry];

        $rootSection = $xlsformVersion->xlsform->xlsformTemplate->rootSection;

        $this->processRootSection($xlsform, $rootEntry, $rootSection, $submission);
    }

    /** Process the 'root' section of the survey based on the root XlsformTemplateSection schema */
    private function processRootSection(Xlsform $xlsform, $entry, XlsformTemplateSection $section, Submission $submission)
    {
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

        // get all choices lists once to avoid multiple db calls when preparing select_multiple values
        $choices = $xlsform->choiceLists()
            ->with('choiceListEntries', function ($query) use ($xlsform) {
                $query
                    ->whereHas('owner', function ($query) use ($xlsform) {
                        $query->where('id', $xlsform->owner->id);
                    })
                    ->orWhereNull('owner_id');
            })
            ->get();

        foreach ($schema as $schemaItem) {
            $itemPath = 'root'.Str::replace('/', '.', $schemaItem['path']);
            $value = Arr::get($entry, $itemPath);

            if ($schemaItem['type'] != 'repeat' && $value !== null && $value != '' && ! is_array($value)) {
                // store ODK variable value as entity value record

                // TODO: get label from correct language String entry.
                // $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
                //     'name' => $schemaItem['name'],
                //     'label' => $schemaItem['name'],
                // ]);

                $entityValues[] = EntityValue::make([
                    'dataset_variable_name' => $schemaItem['name'],
                    'value' => $value,
                ]);

                // for select_multiples, add binary/ boolean columns for each possible response
                $booleanEntityValues = $this->makeMultiSelectBooleans($entity, $schemaItem, $choices, $value);
                $entityValues = array_merge($entityValues, $booleanEntityValues);
            }
        }

        $entity->addValues(collect($entityValues));

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
        // exclude structure items from section schema, as there is no value to be stored for a structure item
        $schema = $section->schema->where('type', '!=', 'structure');

        // find the path of repeat group first item
        $schemaPaths = $schema->pluck('path')->toArray();

        $position = Str::position($schemaPaths[0], '/'.$section->structure_item.'/');

        // construct the path for getting an array of repeat group
        $repeatGroupArrayPath = 'root'.Str::replace('/', '.', Str::substr($schemaPaths[0], 0, $position)).'.'.$section->structure_item;

        // get the array for repeat group
        $repeatGroupArray = Arr::get($entry, $repeatGroupArrayPath);

        // if $repeatGroupArray is null, it means this section has no entries and so does not exist in the submission data
        if (! $repeatGroupArray) {
            return;
        }

        // handle each record in repeat group
        foreach ($repeatGroupArray as $repeatGroupRecord) {

            // if the section is not linked to a dataset, move on;
            if (! $section->dataset) {
                continue;
            }

            $entity = Entity::create([
                'dataset_id' => $section->dataset->id,
                'submission_id' => $submission->id,
                'owner_id' => $submission->owner->getKey(),
                'parent_id' => $entityId,
                'model_type' => $section->dataset->entity_model,
            ]);

            // get all choices lists once to avoid multiple db calls when preparing select_multiple values
            $choices = $xlsform->xlsformTemplate->choiceLists()
                ->with('choiceListEntries', function ($query) use ($xlsform) {
                    $query
                        ->whereHas('owner', function ($query) use ($xlsform) {
                            $query->where('id', $xlsform->owner->id);
                        })
                        ->orWhereNull('owner_id');
                })
                ->get();

            // get array element as record
            $repeatGroupEntry = ['rg' => $repeatGroupRecord];

            $entityValues = [];

            foreach ($schema as $schemaItem) {

                $pathLength = Str::length($schemaItem['path']);
                $position = Str::position($schemaItem['path'], '/'.$section->structure_item.'/');
                $lengthToCut = $pathLength - $position;

                $itemPath = Str::substr($schemaItem['path'], ($position + 1) + Str::length($section->structure_item), $lengthToCut);

                $fullItemPath = 'rg'.Str::replace('/', '.', $itemPath);

                $value = Arr::get($repeatGroupEntry, $fullItemPath);

                if ($schemaItem['type'] != 'repeat' && $value != null && $value != '' && ! is_array($value)) {

                    // TODO: get label from correct language String entry.
                    // $datasetVariable = $section->dataset->variables()->where('name', $schemaItem['name'])->firstOrCreate([
                    //    'name' => $schemaItem['name'],
                    //    'label' => $schemaItem['name'],
                    // ]);

                    // store ODK variable value as entity value record
                    $entityValues[] = EntityValue::make([
                        'dataset_variable_name' => $schemaItem['name'],
                        'value' => $value,
                    ]);

                    // for select_multiples, add binary/ boolean columns for each possible response
                    $booleanEntityValues = $this->makeMultiSelectBooleans($entity, $schemaItem, $choices, $value);
                    $entityValues = array_merge($entityValues, $booleanEntityValues);
                }
            }

            $entity->addValues(collect($entityValues));

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
        return Excel::download(new SurveyExport($xlsform), $xlsform->title.'-'.now()->toDateTimeString().'.xlsx');
    }

    public function makeMultiSelectBooleans(Entity $entity, mixed $schemaItem, Collection $choices, mixed $value): array
    {
        $booleanEntityValues = [];

        if (isset($schemaItem['value_type']) && Str::startsWith($schemaItem['value_type'], 'select_multiple')) {

            $choiceListName = Str::of($schemaItem['value_type'])->after('select_multiple ')->trim()->toString();

            /** @var ChoiceList $choiceList */
            $choiceList = $choices->filter(fn (ChoiceList $list) => $list->list_name === $choiceListName)->first();
            $choiceListEntries = $choiceList->choiceListEntries->unique('name');
            $choicesSelected = Str::of($value)->lower()->explode(' ');

            foreach ($choiceListEntries as $choiceListEntry) {
                $booleanEntityValues[] = EntityValue::make([
                    'dataset_variable_name' => $schemaItem['name'].'_'.Str::lower($choiceListEntry->name),
                    'value' => $choicesSelected->contains(Str::lower($choiceListEntry->name)),
                ]);
            }
        }

        return $booleanEntityValues;
    }

    // TODO: merge with the above item.
    // Currently, we have 2 ways to know the contents of an Xlsform. The schema, and the survey_rows.
    // We should harmonise and use survey_row, as it is more suited to the custom-build ODK forms that we are working towards.
    public function makeMultiSelectBooleansFromSurveyRow(Entity $entity, SurveyRow $surveyRow, mixed $value): \Illuminate\Support\Collection
    {
        $booleanEntityValues = collect();

        if (Str::startsWith(trim($surveyRow->type), 'select_multiple')) {
            $choiceListEntries = $surveyRow->choiceList->choiceListEntries;

            $choicesSelected = Str::of($value)->lower()->explode(' ');

            foreach ($choiceListEntries as $choiceListEntry) {
                $booleanEntityValues->push(
                    EntityValue::make([
                        'entity_id' => $entity['id'],
                        'dataset_variable_name' => $surveyRow->name.'_'.Str::lower($choiceListEntry->name),
                        'value' => $choicesSelected->contains(Str::lower($choiceListEntry->name)),
                    ])
                );
            }

        }

        return $booleanEntityValues;
    }
}
