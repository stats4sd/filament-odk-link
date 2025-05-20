<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Stats4sd\FilamentOdkLink\Events\XlsformTemplateWasImported;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentTeamManagement\Models\User;

class FinishXlsformTemplateImport implements ShouldQueue
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
        // mark model as ready
        $this->model->updateQuietly(['processing' => false]);

        XlsformTemplateWasImported::dispatch($this->model->id);

        Notification::make('xlsform_template_imported')
            ->title('Xlsform Template Imported')
            ->body('The Xlsform Template ' . $this->model->title . ' belonging to ' . $this->model->owner->name . ' has been imported.')
            ->success()
            ->broadcast(Role::findByName('Super Admin')->users);

    }
}
