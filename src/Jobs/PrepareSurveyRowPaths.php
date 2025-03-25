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
        $repeatPaths = collect(); // for nested repeats

        foreach ($surveyRows as $surveyRow) {
            // if begin group or begin repeat; append to path


            switch ($surveyRow->type) {
                case 'begin group':
                case 'begin_group':
                    $path .= $surveyRow->name . '/';
                    $surveyRow->update([
                        'path' => $path,
                        'repeat_group_path' => $repeatPaths->last() ?? null,
                    ]);
                    break;

                case 'end group':
                case 'end_group':
                    $path = substr($path, 0, -1);
                    $path = substr($path, 0, strrpos($path, '/') + 1);
                    $surveyRow->update([
                        'path' => $path,
                        'repeat_group_path' => $repeatPaths->last() ?? null,
                    ]);
                    break;

                case 'begin repeat':
                case 'begin_repeat':
                    $repeatPaths->push($path . $surveyRow->name . '/');
                    $path = '/';
                    $surveyRow->update([
                        'path' => $path,
                        'repeat_group_path' => $repeatPaths->last() ?? null,
                    ]);
                    break;

                case 'end repeat':
                case 'end_repeat':
                    $path = $repeatPaths->pop();

                    $path = substr($path, 0, -1);
                    $path = substr($path, 0, strrpos($path, '/') + 1);

                    $surveyRow->update([
                        'path' => $path,
                        'repeat_group_path' => $repeatPaths->last() ?? null,
                    ]);
                    break;

                default:
                    $surveyRow->update([
                        'path' => $path . $surveyRow->name,
                        'repeat_group_path' => $repeatPaths->last() ?? null,
                    ]);
                    break;
            }


        }


    }
}
