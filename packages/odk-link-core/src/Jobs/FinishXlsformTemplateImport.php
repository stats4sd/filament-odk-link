<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure;
use Stats4sd\FilamentOdkLink\Events\XlsformModuleVersionWasImported;
use Stats4sd\FilamentOdkLink\Events\XlsformTemplateWasImported;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class FinishXlsformTemplateImport implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;

    public function __construct(public XlsformModuleVersion|XlsformTemplate $model) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // mark model as ready
        $this->model->updateQuietly(['processing' => false]);

        if ($this->model instanceof XlsformTemplate) {

            XlsformTemplateWasImported::dispatch($this->model->id);

            Notification::make('xlsform_template_imported')
                ->title('Xlsform Template Imported')
                ->body('The Xlsform Template '.$this->model->title.' belonging to '.$this->model->owner->name.' has been imported.')
                ->success()
                ->broadcast(Role::findByName('Super Admin')->users);
        }

        if ($this->model instanceof XlsformModuleVersion) {

            XlsformModuleVersionWasImported::dispatch($this->model->id);

            Notification::make('xlsform_module_version_imported')
                ->title("Questions for Module: {$this->model->name} successfully imported")
                ->body("The Questions for module {$this->model->name} have been successfully updated.")
                ->success()
                ->broadcast(Role::findByName('Super Admin')->users);

        }

    }
}
