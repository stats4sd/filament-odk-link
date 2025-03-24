<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class PrepareSurveyRowPaths implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public XlsformModuleVersion|XlsformTemplate $model)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var Collection<SurveyRow> $surveyRows */
        $surveyRows = $this->model->surveyRows()->orderBy('row_number')->get();
        $path = '/';

        foreach ($surveyRows as $surveyRow) {
            // if begin group or begin repeat; append to path

            if ($surveyRow->type === 'begin group' || $surveyRow->type === 'begin repeat'
                || $surveyRow->type === 'begin_group' || $surveyRow->type === 'begin_repeat'
            ) {
                $path .= $surveyRow->name . '/';
                $surveyRow->update(['path' => substr($path, 0, -1)]);
                continue;
            }

            if ($surveyRow->type === 'end group' || $surveyRow->type === 'end repeat'
                || $surveyRow->type === 'end_group' || $surveyRow->type === 'end_repeat'
            ) {
                // path is '/one/two/three/latest/'
                // want to remove 'latest/'.

                // delete the last '/' character from $path
                $path = substr($path, 0, -1);

                // delete the last segment from $path
                $path = substr($path, 0, strrpos($path, '/') + 1);
                $surveyRow->update(['path' => substr($path, 0, -1)]);
                continue;
            }

            $surveyRow->update(['path' => $path . $surveyRow->name]);


        }


    }
}
