<?php

namespace Stats4sd\FilamentOdkLink\Jobs;

use Stats4sd\FilamentOdkLink\Events\XlsformModuleVersionWasImported;
use Stats4sd\FilamentOdkLink\Events\XlsformTemplateWasImported;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentTeamManagement\Models\User;

class FinishXlsformTemplateImport implements ShouldQueue
{
    use Queueable;
    use ResetsProcessingOnFailure;

    public function __construct(public XlsformModuleVersion|XlsformTemplate $model)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // mark model as ready
        $this->model->updateQuietly(['processing' => false]);


        if ($this->model instanceof XlsformTemplate) {

            $this->syncTeamFormsWithTemplate($this->model);
            $this->logTeamOwnedVersions($this->model);

            XlsformTemplateWasImported::dispatch($this->model->id);

            Notification::make('xlsform_template_imported')
                ->title('Xlsform Template Imported')
                ->body('The Xlsform Template ' . $this->model->title . ' belonging to ' . $this->model->owner->name . ' has been imported.')
                ->success()
                ->broadcast(Role::findByName('Super Admin')->users);
        }

        if($this->model instanceof XlsformModuleVersion) {


            XlsformModuleVersionWasImported::dispatch($this->model->id);

            Notification::make('xlsform_module_version_imported')
                ->title("Questions for Module: {$this->model->name} successfully imported")
                ->body("The Questions for module {$this->model->name} have been successfully updated.")
                ->success()
                ->broadcast(Role::findByName('Super Admin')->users);


        }

    }

    // The new file may have added modules. A team form only learns about them through syncWithTemplate(), which
    // nothing else triggers after a Replace, so do it here and flag the forms so the new content gets published.
    private function syncTeamFormsWithTemplate(XlsformTemplate $template): void
    {
        $template->load('xlsformModules.defaultXlsformVersion');

        $template->xlsforms()
            ->withoutGlobalScopes()
            ->get()
            ->each(function (Xlsform $xlsform) use ($template) {
                $xlsform->setRelation('xlsformTemplate', $template);
                $xlsform->syncWithTemplate();
            });

        $template->xlsforms()
            ->withoutGlobalScopes()
            ->update(['draft_needs_update' => true]);
    }

    // Team-owned versions (local and cloned) are not part of the file import and are left as they are.
    // Log them so it is clear which teams hold their own copy, and so an emptied copy is noticed at once.
    private function logTeamOwnedVersions(XlsformTemplate $template): void
    {
        $teamVersions = XlsformModuleVersion::query()
            ->whereNotNull('owner_id')
            ->where('is_default', false)
            ->whereHas('xlsformModule', fn (Builder $query) => $query->where('xlsform_template_id', $template->id))
            ->with('xlsformModule:id,name')
            ->withCount('surveyRows')
            ->get();

        if ($teamVersions->isEmpty()) {
            return;
        }

        $summary = $teamVersions
            ->map(fn (XlsformModuleVersion $version) => [
                'version_id' => $version->id,
                'name' => $version->name,
                'owner_id' => $version->owner_id,
                'module' => $version->xlsformModule?->name,
                'survey_rows' => $version->survey_rows_count,
            ])
            ->all();

        Log::info("Xlsform template {$template->id} import finished: team-owned module versions were left as they are", ['versions' => $summary]);

        $emptyVersions = collect($summary)->where('survey_rows', 0);

        if ($emptyVersions->isNotEmpty()) {
            Log::warning("Xlsform template {$template->id} has team-owned module versions with no survey rows", ['versions' => $emptyVersions->values()->all()]);
        }
    }
}
