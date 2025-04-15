<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class FinishChoiceListEntryImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public XlsformModuleVersion | XlsformTemplate $model)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->model->choiceListEntries()
            ->update(['updated_during_import' => false]);

    }
}
