<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class FinishSurveyRowImport implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;

    public function __construct(public XlsformModuleVersion | XlsformTemplate $model)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->model->surveyRows()
            ->update(['updated_during_import' => false]);

    }
}
